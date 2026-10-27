#!/usr/bin/env python3
# =============================================================================
# THE DAILY CRUST - Sinh đơn hàng MÔ PHỎNG cho giai đoạn dữ liệu bị thưa (mặc định 01/03/2026 -> 23/10/2026)
#
# Mọi đơn mô phỏng có orders.note = 'SIM-DATA' nên xóa riêng được bất cứ lúc nào:  --purge
#
# Xem thử (không ghi DB):   python seed_simulated_orders.py --env-file C:\laravel\the-daily-crust\.env --dry-run
# Ghi thật:                 python seed_simulated_orders.py --env-file C:\laravel\the-daily-crust\.env
# Sinh NỐI TIẾP (giữ cũ, chỉ thêm ngày chưa có):  ... --from 2026-10-24 --to 2026-11-30 --append
# Sinh lại (xóa cũ + ghi mới, một giao dịch):  ... --replace
# Chỉ xóa đơn mô phỏng:     ... --purge
#
# Cách sinh (xem hàm plan_days / make_orders):
#   1. HỌC "dáng" của tiệm từ chính dữ liệu thật đang có: phân bố giờ đặt hàng, số dòng/đơn, độ phổ biến từng sản phẩm.
#      Độ phổ biến = 60% từ dữ liệu thật + 40% chia đều, nên cả 37 sản phẩm đều có đơn (kể cả bánh quy trẻ em).
#   2. Số đơn mỗi ngày = --per-day x hệ số thứ trong tuần x hệ số ngày lễ x nhiễu log-normal (độ lệch --noise).
#      Thứ 6 - thứ 7 - chủ nhật cao hơn thứ 2 - thứ 4.
#   3. Hệ số theo nhóm hàng: cuối tuần tăng bánh ngọt và quà tặng; mùa nóng (tháng 5-9) tăng đồ uống; ngày quà tặng
#      (Valentine, 8/3, 20/10, 20/11, Giáng sinh) tăng quà tặng và bánh ngọt.
#   4. Ngày nào đã có đơn thật thì chỉ BỔ SUNG phần còn thiếu để đạt mức mục tiêu (không xóa, không sửa đơn thật).
#
# Đơn mô phỏng KHÔNG gắn lot_id: không trừ tồn kho thành phẩm, không trừ nguyên liệu, không tạo phiếu kho.
# Dữ liệu là mô phỏng: khi viết báo cáo phải nói rõ điều này (các quy luật trên do người sinh dữ liệu đặt ra).
# =============================================================================
import argparse
import sys
from datetime import date, datetime, timedelta

import numpy as np
import pandas as pd

from forecast_production import connect, load_env_file, query_df

SIM_FLAG = "SIM-DATA"
BUSINESS_DAY_START_HOUR = 6          # giống forecast_production.py: 00:00-05:59 thuộc ngày bán hàng hôm trước

# Hệ số lượng đơn theo thứ (0 = thứ 2 ... 6 = chủ nhật), trung bình ~1
DOW_FACTOR = np.array([0.85, 0.85, 0.90, 0.95, 1.10, 1.30, 1.20])
DOW_FACTOR = DOW_FACTOR / DOW_FACTOR.mean()

CAT_BAKERY, CAT_SWEET, CAT_SNACK, CAT_DRINK, CAT_GIFT = (
    "Bánh mì & Bánh nướng", "Bánh ngọt & Cookies", "Món mặn & Đồ ăn nhẹ", "Đồ uống", "Hàng thương mại & Quà tặng")

# Ngày lễ dương lịch làm tăng số đơn (tháng, ngày) -> hệ số; ngày quà tặng đánh dấu riêng
HOLIDAY_FACTOR = {(1, 1): 1.15, (2, 14): 1.35, (3, 8): 1.35, (4, 30): 1.20, (5, 1): 1.20, (6, 1): 1.25,
                  (9, 2): 1.15, (10, 20): 1.35, (11, 20): 1.25, (12, 24): 1.35, (12, 25): 1.25}
GIFT_DAYS = {(2, 14), (3, 8), (10, 20), (11, 20), (12, 24), (12, 25)}
MID_AUTUMN = {2024: date(2024, 9, 17), 2025: date(2025, 10, 6), 2026: date(2026, 9, 25), 2027: date(2027, 9, 15)}


def table_columns(conn, table):
    """Tên cột thật của bảng (MySQL hoặc sqlite) để chỉ ghi những cột có tồn tại."""
    cur = conn.cursor()
    try:
        cur.execute(f"SELECT * FROM {table} LIMIT 0")
        return [c[0] for c in cur.description]
    finally:
        cur.close()


# ------------------------------- HỌC TỪ DỮ LIỆU THẬT --------------------------
def learn_profile(conn):
    orders = query_df(conn, f"SELECT order_id, order_datetime FROM orders WHERE note IS NULL OR note <> '{SIM_FLAG}'")
    details = query_df(conn, "SELECT order_id, product_id, quantity FROM order_details")
    products = query_df(conn, "SELECT product_id, vi_name, price, category, status FROM products")
    products["price"] = pd.to_numeric(products["price"], errors="coerce").fillna(0).astype(int)
    products = products[(products["status"].isna()) | (products["status"] == "Active")].reset_index(drop=True)

    orders["order_datetime"] = pd.to_datetime(orders["order_datetime"])
    orders["bday"] = (orders["order_datetime"] - pd.Timedelta(hours=BUSINESS_DAY_START_HOUR)).dt.normalize()
    per_day = orders.groupby("bday")["order_id"].nunique()
    dense_days = per_day[per_day >= 0.5 * per_day.median()].index           # chỉ học từ những ngày bán bình thường
    dense = orders[orders["bday"].isin(dense_days)]
    det = details[details["order_id"].isin(dense["order_id"])].copy()
    det["quantity"] = pd.to_numeric(det["quantity"], errors="coerce").fillna(1).clip(lower=1).astype(int)

    # phân bố giờ (0-23) và số dòng mỗi đơn, số lượng mỗi dòng
    hour_p = dense["order_datetime"].dt.hour.value_counts().reindex(range(24), fill_value=0).astype(float)
    if hour_p.sum() == 0:
        hour_p[16:24] = 1.0                                                  # dự phòng: tiệm bán buổi chiều tối
    hour_p /= hour_p.sum()
    lines = det.groupby("order_id").size()
    lines_p = lines.value_counts(normalize=True).sort_index() if len(lines) else pd.Series({1: 0.5, 2: 0.3, 3: 0.2})
    qty_p = det["quantity"].value_counts(normalize=True).sort_index() if len(det) else pd.Series({1: 1.0})

    emp = det.groupby("product_id").size().reindex(products["product_id"], fill_value=0).astype(float)
    emp = emp / emp.sum() if emp.sum() > 0 else emp + 1.0 / len(emp)
    uni = np.full(len(products), 1.0 / len(products))
    base_w = 0.6 * emp.to_numpy() + 0.4 * uni
    info = {"dense_days": len(dense_days), "median_orders": float(per_day.loc[dense_days].median()) if len(dense_days) else 0.0}
    return products, base_w, hour_p, lines_p, qty_p, info


# ------------------------------- LẬP KẾ HOẠCH TỪNG NGÀY ------------------------
def day_factor(d, noise, rng):
    f = DOW_FACTOR[d.weekday()] * HOLIDAY_FACTOR.get((d.month, d.day), 1.0)
    mid = MID_AUTUMN.get(d.year)
    if mid and -3 <= (d - mid).days <= 0:
        f *= 1.25                                                            # tuần Trung thu
    return f * float(np.exp(rng.normal(0.0, noise) - noise ** 2 / 2))        # nhiễu log-normal, kỳ vọng ~1


def category_tilt(d, categories):
    """Nhân với trọng số sản phẩm: mỗi nhóm hàng có quy luật riêng theo thứ / mùa / ngày lễ."""
    t = np.ones(len(categories))
    weekend = d.weekday() >= 5
    gift_day = (d.month, d.day) in GIFT_DAYS
    mid = MID_AUTUMN.get(d.year)
    for i, c in enumerate(categories):
        if c in (CAT_SWEET, CAT_GIFT) and weekend:
            t[i] *= 1.25
        if c == CAT_DRINK and 5 <= d.month <= 9:
            t[i] *= 1.30                                                     # mùa nóng uống nhiều hơn
        if c == CAT_DRINK and d.month in (12, 1, 2):
            t[i] *= 0.90
        if gift_day and c in (CAT_GIFT, CAT_SWEET):
            t[i] *= 1.6
        if mid and -3 <= (d - mid).days <= 0 and c == CAT_GIFT:
            t[i] *= 1.8                                                      # quà tặng dịp Trung thu
    return t


def make_orders(day, n_orders, products, base_w, hour_p, lines_p, qty_p, rng):
    """Trả về list đơn: (datetime, [(product_id, qty, price, name), ...])."""
    if n_orders <= 0:
        return []
    w = base_w * category_tilt(day, products["category"].tolist())
    w = w / w.sum()
    hours = rng.choice(24, size=n_orders, p=hour_p.to_numpy())
    n_lines = rng.choice(lines_p.index.to_numpy(), size=n_orders, p=lines_p.to_numpy())
    out = []
    pid, price, name = products["product_id"].to_numpy(), products["price"].to_numpy(), products["vi_name"].to_numpy()
    for h, k in zip(hours, n_lines):
        k = int(min(k, len(products)))
        idx = rng.choice(len(products), size=k, replace=True, p=w)           # mua 3 bánh mì = 3 dòng cùng sản phẩm
        qty = np.ones(k, dtype=int)                                          # OrderService luôn lưu quantity = 1 mỗi dòng
        when = datetime.combine(day, datetime.min.time()) + timedelta(
            hours=int(h), minutes=int(rng.integers(0, 60)))
        if h < BUSINESS_DAY_START_HOUR:                                      # 00:00-05:59 thuộc ngày bán hàng hôm trước
            when += timedelta(days=1)
        out.append((when, [(int(pid[i]), int(q), int(price[i]), str(name[i])) for i, q in zip(idx, qty)]))
    return sorted(out, key=lambda o: o[0])


def period_of(dt):
    return "Morning" if 6 <= dt.hour < 12 else ("Afternoon" if 12 <= dt.hour < 18 else "Evening")


# ------------------------------- GHI DATABASE ----------------------------------
def purge(conn, ph):
    cur = conn.cursor()
    cur.execute(f"DELETE FROM order_details WHERE order_id IN (SELECT order_id FROM orders WHERE note = {ph})", (SIM_FLAG,))
    n_d = cur.rowcount
    cur.execute(f"DELETE FROM orders WHERE note = {ph}", (SIM_FLAG,))
    n_o = cur.rowcount
    cur.close()
    return n_o, n_d


def insert_rows(conn, ph, table, cols, rows, chunk=2000):
    sql = f"INSERT INTO {table} ({', '.join(cols)}) VALUES ({', '.join([ph] * len(cols))})"
    cur = conn.cursor()
    for i in range(0, len(rows), chunk):
        cur.executemany(sql, rows[i:i + chunk])
    cur.close()


def next_id(conn, table, col):
    cur = conn.cursor()
    cur.execute(f"SELECT COALESCE(MAX({col}), 0) FROM {table}")
    v = int(cur.fetchone()[0])
    cur.close()
    return v + 1


def main():
    ap = argparse.ArgumentParser(description="Sinh đơn hàng mô phỏng (gắn cờ SIM-DATA) cho giai đoạn dữ liệu thưa")
    ap.add_argument("--env-file", help="đường dẫn .env của Laravel")
    ap.add_argument("--from", dest="d_from", default="2026-03-01", help="ngày bắt đầu, YYYY-MM-DD (mặc định 2026-03-01)")
    ap.add_argument("--to", dest="d_to", default="2026-10-23", help="ngày kết thúc, YYYY-MM-DD (mặc định 2026-10-23)")
    ap.add_argument("--per-day", type=float, default=200.0, help="số đơn TRUNG BÌNH mỗi ngày (mặc định 200)")
    ap.add_argument("--noise", type=float, default=0.12, help="độ lệch nhiễu log-normal của số đơn mỗi ngày (mặc định 0.12)")
    ap.add_argument("--seed", type=int, default=2026, help="hạt giống ngẫu nhiên (cùng seed = cùng kết quả)")
    ap.add_argument("--channel", choices=["POS", "App", "none"], default="POS",
                    help="order_channel gán cho đơn mô phỏng (mặc định POS; none = để trống, Worklist không thấy)")
    ap.add_argument("--dry-run", action="store_true", help="chỉ in kế hoạch, không ghi database")
    ap.add_argument("--replace", action="store_true", help="xóa đơn mô phỏng cũ rồi sinh lại trong cùng một giao dịch")
    ap.add_argument("--append", action="store_true",
                    help="SINH NỐI TIẾP: giữ nguyên đơn mô phỏng đã có, chỉ sinh cho những ngày chưa có đơn mô phỏng (chạy lại không trùng)")
    ap.add_argument("--purge", action="store_true", help="CHỈ xóa các đơn mô phỏng (note = SIM-DATA), không sinh mới")
    args = ap.parse_args()

    if args.env_file:
        load_env_file(args.env_file)
    d_from, d_to = date.fromisoformat(args.d_from), date.fromisoformat(args.d_to)
    if d_to < d_from:
        sys.exit("--to phải sau --from")

    conn, ph = connect()
    try:
        if args.purge:
            if args.dry_run:
                cur = conn.cursor()
                cur.execute(f"SELECT COUNT(*) FROM orders WHERE note = {ph}", (SIM_FLAG,))
                print(f"[dry-run] sẽ xóa {cur.fetchone()[0]:,} đơn mô phỏng.")
                return
            n_o, n_d = purge(conn, ph)
            conn.commit()
            print(f"Đã xóa {n_o:,} đơn mô phỏng và {n_d:,} dòng chi tiết. Đơn thật không bị ảnh hưởng.")
            return

        cur = conn.cursor()
        cur.execute(f"SELECT COUNT(*) FROM orders WHERE note = {ph}", (SIM_FLAG,))
        n_existing_sim = int(cur.fetchone()[0])
        cur.close()
        if args.append and args.replace:
            sys.exit("--append và --replace không dùng chung: --append giữ dữ liệu cũ, --replace xóa rồi sinh lại.")
        if n_existing_sim and not (args.replace or args.append):
            sys.exit(f"Đã có {n_existing_sim:,} đơn mô phỏng. Dùng --append để sinh nối tiếp, --replace để sinh lại, hoặc --purge để xóa.")
        if d_to >= date.today():
            print(f"LƯU Ý: --to={d_to:%d/%m/%Y} không sớm hơn hôm nay, các đơn mang ngày chưa tới sẽ nằm sẵn trong database "
                  f"(dự báo bỏ qua chúng, nhưng chúng hiện trong danh sách đơn). Nên sinh đến HÔM QUA rồi sinh nối tiếp mỗi ngày.")

        products, base_w, hour_p, lines_p, qty_p, info = learn_profile(conn)
        print(f"Học từ dữ liệu thật: {info['dense_days']} ngày bán bình thường, trung vị {info['median_orders']:.0f} đơn/ngày | "
              f"{len(products)} sản phẩm đang bán | giờ bán chính: "
              + ", ".join(f"{h}h" for h in hour_p.sort_values(ascending=False).index[:4]))
        if info["median_orders"] and args.per_day > 2.5 * info["median_orders"]:
            print(f"LƯU Ý: --per-day={args.per_day:.0f} cao gấp {args.per_day / info['median_orders']:.1f} lần mức thật "
                  f"({info['median_orders']:.0f} đơn/ngày) nên chuỗi sẽ có một bước nhảy về quy mô. Dùng --per-day thấp hơn nếu muốn liền mạch.")

        # số đơn thật đã có theo ngày bán hàng (trừ đơn mô phỏng cũ nếu đang --replace)
        have = query_df(conn, f"SELECT order_datetime FROM orders WHERE note IS NULL OR note <> '{SIM_FLAG}'")
        have["order_datetime"] = pd.to_datetime(have["order_datetime"])
        have_by_day = ((have["order_datetime"] - pd.Timedelta(hours=BUSINESS_DAY_START_HOUR)).dt.date).value_counts().to_dict()

        sim_days = set()
        if args.append and n_existing_sim:
            sim = query_df(conn, f"SELECT order_datetime FROM orders WHERE note = '{SIM_FLAG}'")
            sim["order_datetime"] = pd.to_datetime(sim["order_datetime"])
            sim_days = set((sim["order_datetime"] - pd.Timedelta(hours=BUSINESS_DAY_START_HOUR)).dt.date)

        rng = np.random.default_rng(args.seed + int(d_from.toordinal()) % 9973 if args.append else args.seed)
        plan, total_orders, total_lines = [], 0, 0
        d = d_from
        while d <= d_to:
            target = int(round(args.per_day * day_factor(d, args.noise, rng)))
            n_new = 0 if d in sim_days else max(0, target - int(have_by_day.get(d, 0)))   # --append: ngày đã có đơn mô phỏng thì bỏ qua
            orders = make_orders(d, n_new, products, base_w, hour_p, lines_p, qty_p, rng)
            plan.append((d, target, int(have_by_day.get(d, 0)), orders))
            total_orders += len(orders)
            total_lines += sum(len(o[1]) for o in orders)
            d += timedelta(days=1)

        # tóm tắt theo tháng và theo thứ để kiểm tra bằng mắt
        summary = pd.DataFrame([(p[0], p[1], len(p[3])) for p in plan], columns=["d", "target", "new"])
        summary["m"] = pd.to_datetime(summary["d"]).dt.strftime("%Y-%m")
        summary["dow"] = pd.to_datetime(summary["d"]).dt.dayofweek
        print(f"\nKế hoạch {d_from:%d/%m/%Y} -> {d_to:%d/%m/%Y}: {len(plan)} ngày"
              + (f" ({len(sim_days & {p[0] for p in plan})} ngày đã có đơn mô phỏng, bỏ qua)" if args.append else "")
              + f", {total_orders:,} đơn mới, {total_lines:,} dòng chi tiết")
        print("Đơn mục tiêu TB theo tháng:  " + " | ".join(f"{m}: {v:.0f}" for m, v in summary.groupby("m")["target"].mean().items()))
        print("Đơn mục tiêu TB theo thứ:    " + " | ".join(
            f"{n}: {v:.0f}" for n, v in zip(["T2", "T3", "T4", "T5", "T6", "T7", "CN"], summary.groupby("dow")["target"].mean())))

        if args.dry_run:
            print("\n[dry-run] chưa ghi gì vào database.")
            return

        o_all = table_columns(conn, "orders")
        o_cols = [c for c in ("order_id", "order_datetime", "period_day", "weekday_weekend", "total_amount", "note",
                              "status", "order_channel", "payment_method", "delivery_method", "prepared_at", "completed_at")
                  if c in o_all]
        if "status" not in o_all:
            print("LƯU Ý: bảng orders không có cột status, bỏ qua.")
        d_all = table_columns(conn, "order_details")
        d_cols = [c for c in ("detail_id", "order_id", "product_id", "price", "quantity", "unit_price", "original_price",
                              "discount_percent", "discount_amount", "line_total", "product_name_snapshot") if c in d_all]
        if "order_datetime" not in o_cols or "quantity" not in d_cols:
            sys.exit("Bảng orders/order_details không có cột mong đợi (order_datetime, quantity).")

        if args.replace:
            n_o, n_d = purge(conn, ph)
            print(f"Đã xóa {n_o:,} đơn mô phỏng cũ.")
        oid, did = next_id(conn, "orders", "order_id"), next_id(conn, "order_details", "detail_id")
        o_rows, d_rows = [], []
        for day, _, _, orders in plan:
            for when, items in orders:
                total = sum(q * p for _, q, p, _ in items)
                bday = (when - timedelta(hours=BUSINESS_DAY_START_HOUR)).date()
                ts = when.strftime("%Y-%m-%d %H:%M:%S")
                # Giống một đơn POS đã hoàn tất do OrderService tạo: status Completed, prepared_at = completed_at = lúc đặt.
                # Đơn đã Completed nên KHÔNG BAO GIỜ nằm trong tab "Cần xử lý" của Worklist.
                o_vals = {"order_id": oid, "order_datetime": ts, "period_day": period_of(when),
                          "weekday_weekend": "Weekend" if when.weekday() >= 5 else "Weekday", "total_amount": total, "note": SIM_FLAG,
                          "status": "Completed", "order_channel": None if args.channel == "none" else args.channel,
                          "payment_method": str(rng.choice(["Cash", "QR_Code", "Momo"], p=[0.6, 0.25, 0.15])),
                          "delivery_method": "In_Store", "prepared_at": ts, "completed_at": ts}
                o_rows.append(tuple(o_vals[c] for c in o_cols))
                for pid, q, price, name in items:
                    d_vals = {"detail_id": did, "order_id": oid, "product_id": pid, "price": price, "quantity": q,
                              "unit_price": price, "original_price": price, "discount_percent": 0, "discount_amount": 0,
                              "line_total": q * price, "product_name_snapshot": name}
                    d_rows.append(tuple(d_vals[c] for c in d_cols))
                    did += 1
                oid += 1
        insert_rows(conn, ph, "orders", o_cols, o_rows)
        insert_rows(conn, ph, "order_details", d_cols, d_rows)
        conn.commit()
        print(f"\nĐã ghi {len(o_rows):,} đơn và {len(d_rows):,} dòng chi tiết (note = '{SIM_FLAG}'). "
              f"Muốn xóa riêng: python seed_simulated_orders.py --env-file ... --purge")
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    main()
