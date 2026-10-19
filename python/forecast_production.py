#!/usr/bin/env python3
# =============================================================================
# THE DAILY CRUST - Dự báo sản lượng sản xuất cho NGÀY MAI và ghi vào MySQL
#
# Cài thư viện (1 lần):   pip install pandas numpy scikit-learn pymysql
# Chạy thử không ghi DB:  python forecast_production.py --env-file C:\path\to\laravel\.env --dry-run
# Chạy thật:              python forecast_production.py --env-file C:\path\to\laravel\.env
# Giả lập một ngày khác:  python forecast_production.py --env-file ... --as-of 2025-12-20
#
# Luồng: đọc orders + order_details + products  ->  gom theo (ngày, sản phẩm)
#        -> tạo đặc trưng thời gian  -> kiểm định theo thời gian  -> huấn luyện lại trên toàn bộ
#        -> dự báo ngày mai  -> INSERT vào production_forecasts (xóa bản dự báo cũ cùng ngày trước).
#
# Quy tắc dữ liệu:
#   * Chỉ dùng đơn có order_datetime < 00:00 của ngày chạy (ngày chạy chưa kết thúc nên không tính).
#     Mọi đơn từ ngày chạy trở đi (dữ liệu giả của tương lai) bị loại, không bao giờ vào huấn luyện.
#   * Chỉ tính các "ngày có bán hàng". Ngày không có đơn nào trong file được coi là THIẾU DỮ LIỆU,
#     không điền 0 (nếu điền 0, mô hình sẽ học rằng tiệm "bán 0 cái" trong hàng trăm ngày).
# =============================================================================
import argparse
import os
import sys
from datetime import date, datetime, timedelta
from urllib.parse import unquote, urlparse

import numpy as np
import pandas as pd
from sklearn.ensemble import HistGradientBoostingRegressor

BUSINESS_DAY_START_HOUR = 6   # đơn từ 00:00 đến 05:59 thuộc về ngày bán hàng của hôm trước (tiệm bán đến quá nửa đêm)
MIN_DAY_RATIO = 0.3   # ngày có ít hơn 30% số đơn của ngày trung vị bị coi là ngày dữ liệu dở dang, loại ra

# Nhóm sản phẩm cần SẢN XUẤT (loại đồ uống pha tại chỗ và hàng thương mại/quà tặng)
DEFAULT_CATEGORIES = ["Bánh mì & Bánh nướng", "Bánh ngọt & Cookies", "Món mặn & Đồ ăn nhẹ"]

# Ngày lễ dương lịch (tháng, ngày) và mùng 1 Tết âm lịch từng năm (kiểm tra lại nếu thêm năm mới)
FIXED_HOLIDAYS = {(1, 1), (4, 30), (5, 1), (9, 1), (9, 2)}
TET_MUNG1 = {2024: date(2024, 2, 10), 2025: date(2025, 1, 29), 2026: date(2026, 2, 17), 2027: date(2027, 2, 6)}

FEATURES = ["product_id", "category_code", "price", "dow", "is_weekend", "month", "dom",
            "is_holiday", "is_pre_holiday", "is_tet", "roll7", "roll28", "dow_mean4"]


# ------------------------------- KẾT NỐI DB ----------------------------------
def load_env_file(path):
    """Đọc DB_* từ file .env của Laravel để khỏi cấu hình hai nơi."""
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, val = line.split("=", 1)
            os.environ.setdefault(key.strip(), val.strip().strip('"').strip("'"))


def connect():
    """Trả về (connection, placeholder). MySQL dùng %s; sqlite (chỉ để test) dùng ?."""
    url = os.environ.get("DB_URL", "")
    if url.startswith("sqlite:///"):
        import sqlite3
        return sqlite3.connect(url[len("sqlite:///"):]), "?"
    import pymysql
    conn = pymysql.connect(
        host=os.environ.get("DB_HOST", "127.0.0.1"),
        port=int(os.environ.get("DB_PORT", 3306)),
        user=os.environ.get("DB_USERNAME", "root"),
        password=os.environ.get("DB_PASSWORD", ""),
        database=os.environ.get("DB_DATABASE", "the_daily_crust"),
        charset="utf8mb4",
    )
    return conn, "%s"


def query_df(conn, sql):
    cur = conn.cursor()
    cur.execute(sql)
    cols = [c[0] for c in cur.description]
    df = pd.DataFrame(cur.fetchall(), columns=cols)
    cur.close()
    return df


# ------------------------------- DỮ LIỆU -------------------------------------
def load_history(conn, cutoff, categories):
    orders = query_df(conn, "SELECT order_id, order_datetime FROM orders")
    details = query_df(conn, "SELECT order_id, product_id, quantity FROM order_details")
    products = query_df(conn, "SELECT product_id, vi_name, category, price, status FROM products")

    orders["order_datetime"] = pd.to_datetime(orders["order_datetime"])
    orders["business_date"] = (orders["order_datetime"] - pd.Timedelta(hours=BUSINESS_DAY_START_HOUR)).dt.normalize()
    n_all = len(orders)
    orders = orders[orders["business_date"] < cutoff]                       # LOẠI dữ liệu từ ngày chạy trở đi
    print(f"Đơn hàng: {n_all:,} tổng | {len(orders):,} dùng được (trước {cutoff:%d/%m/%Y}) | "
          f"{n_all - len(orders):,} bị loại vì thuộc ngày chạy/tương lai")
    if orders.empty:
        sys.exit("Không có đơn hàng lịch sử nào để huấn luyện.")

    details["quantity"] = pd.to_numeric(details["quantity"], errors="coerce").fillna(1.0)   # quantity NULL = 1 cái/dòng
    products["price"] = pd.to_numeric(products["price"], errors="coerce")
    products = products[products["category"].isin(categories)]
    if "status" in products:
        products = products[products["status"].fillna("Active") == "Active"]
    return orders, details, products.reset_index(drop=True)


def build_daily_grid(orders, details, products):
    """Bảng (ngày bán hàng x sản phẩm) với số lượng bán; ngày không có đơn nào thì KHÔNG đưa vào."""
    d = details.merge(orders[["order_id", "business_date"]], on="order_id", how="inner")
    d["date"] = d["business_date"]
    units = d.groupby(["date", "product_id"])["quantity"].sum()

    per_day = orders.groupby("business_date")["order_id"].nunique()
    partial = per_day[per_day < MIN_DAY_RATIO * per_day.median()]
    if len(partial):
        print(f"Loại {len(partial)} ngày dữ liệu dở dang (quá ít đơn so với ngày bình thường): "
              + ", ".join(f"{d:%d/%m/%Y}({n} đơn)" for d, n in partial.items()))
    days = pd.DatetimeIndex(sorted(per_day.drop(partial.index).index))
    grid = pd.MultiIndex.from_product([days, products["product_id"]], names=["date", "product_id"]).to_frame(index=False)
    grid = grid.merge(units.rename("units").reset_index(), on=["date", "product_id"], how="left")
    grid["units"] = grid["units"].fillna(0.0)
    return grid.merge(products[["product_id", "category", "price"]], on="product_id", how="left")


# ------------------------------- ĐẶC TRƯNG -----------------------------------
def holiday_flags(dates):
    idx = pd.DatetimeIndex(dates)
    md = list(zip(idx.month, idx.day))
    is_h = np.array([m in FIXED_HOLIDAYS for m in md])
    nxt = idx + pd.Timedelta(days=1)
    is_pre = np.array([(m, d) in FIXED_HOLIDAYS for m, d in zip(nxt.month, nxt.day)])
    is_tet = np.zeros(len(idx), dtype=bool)
    for y, m1 in TET_MUNG1.items():
        delta = (idx - pd.Timestamp(m1)).days
        is_tet |= (delta >= -3) & (delta <= 5)          # 27 Tết -> mùng 6
    return is_h.astype(int), is_pre.astype(int), is_tet.astype(int)


def add_features(df):
    """Mọi đặc trưng 'quá khứ' đều shift(1) nên không nhìn thấy ngày hiện tại (không rò rỉ)."""
    df = df.sort_values(["product_id", "date"]).reset_index(drop=True)
    df["dow"] = df["date"].dt.dayofweek
    df["is_weekend"] = (df["dow"] >= 5).astype(int)
    df["month"] = df["date"].dt.month
    df["dom"] = df["date"].dt.day
    df["is_holiday"], df["is_pre_holiday"], df["is_tet"] = holiday_flags(df["date"])
    df["category_code"] = df["category"].astype("category").cat.codes

    g = df.groupby("product_id")["units"]
    df["roll7"] = g.transform(lambda s: s.shift(1).rolling(7, min_periods=1).mean())      # 7 ngày bán gần nhất
    df["roll28"] = g.transform(lambda s: s.shift(1).rolling(28, min_periods=1).mean())    # 28 ngày bán gần nhất
    df["dow_mean4"] = df.groupby(["product_id", "dow"])["units"].transform(
        lambda s: s.shift(1).rolling(4, min_periods=1).mean())                            # 4 lần cùng thứ gần nhất
    return df


def baseline_dow(df):      # trung bình 4 lần cùng thứ gần nhất
    return df["dow_mean4"].fillna(df["roll28"]).fillna(df["roll7"]).fillna(0.0).to_numpy()


def baseline_mean28(df):   # trung bình 28 ngày bán gần nhất
    return df["roll28"].fillna(df["roll7"]).fillna(0.0).to_numpy()


def new_model():
    return HistGradientBoostingRegressor(                 # mô hình nhỏ, chính quy hóa mạnh vì dữ liệu ít và nhiễu
        loss="poisson", learning_rate=0.05, max_iter=120, max_depth=3, min_samples_leaf=60,
        l2_regularization=1.0, categorical_features=["product_id", "category_code"], random_state=42)


def wape(y, p):
    return float(np.abs(y - p).sum() / max(y.sum(), 1e-9))


# ------------------------------- HUẤN LUYỆN + DỰ BÁO -------------------------
def run_model(grid, target_date, buffer, force_ml=False):
    target_rows = grid[["product_id", "category", "price"]].drop_duplicates().assign(date=target_date, units=np.nan)
    full = add_features(pd.concat([grid, target_rows], ignore_index=True))      # cùng một đường code cho train và dự báo
    hist = full[full["units"].notna()].copy()
    tgt = full[full["date"] == target_date].copy()

    days = np.array(sorted(hist["date"].unique()))
    n_test = max(5, int(len(days) * 0.2))
    split_day = days[-n_test]
    train, test = hist[hist["date"] < split_day], hist[hist["date"] >= split_day]
    print(f"Ngày bán hàng: {len(days)} | huấn luyện {len(days) - n_test} ngày, kiểm định {n_test} ngày cuối "
          f"({pd.Timestamp(split_day):%d/%m/%Y} -> {pd.Timestamp(days[-1]):%d/%m/%Y})")

    # 1) kiểm định theo thời gian: so sánh mô hình ML với hai đường cơ sở, chọn cái sai số thấp nhất
    mdl = new_model().fit(train[FEATURES], train["units"])
    y = test["units"].to_numpy()
    cands = {"HGB-Poisson-v1.0": np.clip(mdl.predict(test[FEATURES]), 0, None),
             "Baseline-DOW-v1.0": baseline_dow(test),
             "Baseline-Mean28-v1.0": baseline_mean28(test)}
    for name, pr in cands.items():
        daily = test.assign(pr=pr).groupby("date")[["units", "pr"]].sum()
        print(f"  {name:<22} MAE/sp-ngày={np.abs(y - pr).mean():.3f}  WAPE sp-ngày={wape(y, pr):.1%}  "
              f"WAPE tổng/ngày={wape(daily['units'].to_numpy(), daily['pr'].to_numpy()):.1%}")
    version = "HGB-Poisson-v1.0" if force_ml else min(cands, key=lambda k: wape(y, cands[k]))
    print(f"  => Chọn: {version}" + ("  (ép dùng mô hình ML theo --model hgb)" if force_ml else ""))

    # độ tin cậy từng sản phẩm = 1 - WAPE trên tập kiểm định (ước lượng thực nghiệm, KHÔNG phải xác suất thống kê)
    best = cands[version]
    chk = test.assign(err=np.abs(y - best), y=y).groupby("product_id")[["err", "y"]].sum()
    conf = (1 - chk["err"] / chk["y"].clip(lower=1e-9)).clip(0.05, 0.99)
    conf_default = float(np.clip(1 - wape(y, best), 0.05, 0.99))

    # 2) huấn luyện lại trên TOÀN BỘ lịch sử rồi dự báo ngày mai
    if version.startswith("HGB"):
        final = new_model().fit(hist[FEATURES], hist["units"])
        pred = np.clip(final.predict(tgt[FEATURES]), 0, None)
    elif version.startswith("Baseline-DOW"):
        pred = baseline_dow(tgt)
    else:
        pred = baseline_mean28(tgt)
    tgt = tgt.assign(pred=pred * (1 + buffer))
    tgt["predicted_qty"] = np.rint(tgt["pred"]).astype(int)
    tgt["confidence"] = tgt["product_id"].map(conf).fillna(conf_default).round(2)
    return tgt, version


# ------------------------------- GHI DATABASE --------------------------------
def write_forecasts(conn, ph, target_date, rows, version, dry_run):
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    fdate = f"{target_date:%Y-%m-%d} 00:00:00"
    cols = "(forecast_id, forecast_date, product_id, predicted_qty, model_version, confidence, created_at)"
    cur = conn.cursor()
    cur.execute("SELECT COALESCE(MAX(forecast_id), 0) FROM production_forecasts")
    next_id = int(cur.fetchone()[0]) + 1
    data = [(next_id + i, fdate, int(r.product_id), int(r.predicted_qty), version, float(r.confidence), now)
            for i, r in enumerate(rows.itertuples())]

    print(f"\nCâu lệnh INSERT sẽ chạy ({len(data)} dòng cho ngày {target_date:%d/%m/%Y}):")
    print(f"INSERT INTO production_forecasts {cols} VALUES")
    for i, d in enumerate(data):
        print("  (" + ", ".join(repr(x) for x in d) + ")" + ("," if i < len(data) - 1 else ";"))
    if dry_run:
        print("\n[--dry-run] Không ghi vào database.")
        return
    try:
        cur.execute(f"DELETE FROM production_forecasts WHERE forecast_date >= {ph} AND forecast_date < {ph}",
                    (fdate, f"{target_date + timedelta(days=1):%Y-%m-%d} 00:00:00"))
        print(f"Đã xóa {cur.rowcount} dòng dự báo cũ của ngày này (chạy lại không bị trùng).")
        cur.executemany(f"INSERT INTO production_forecasts {cols} VALUES ({', '.join([ph] * 7)})", data)
        conn.commit()
        print(f"Đã ghi {len(data)} dòng vào production_forecasts.")
    except Exception:
        conn.rollback()
        raise
    finally:
        cur.close()


# ------------------------------- MAIN ----------------------------------------
def main():
    ap = argparse.ArgumentParser(description="Dự báo sản lượng ngày mai cho The Daily Crust")
    ap.add_argument("--env-file", help="đường dẫn .env của Laravel (đọc DB_HOST, DB_DATABASE...)")
    ap.add_argument("--as-of", help="giả lập ngày chạy, định dạng YYYY-MM-DD (mặc định: hôm nay)")
    ap.add_argument("--include-today", action="store_true", help="tính cả đơn của ngày chạy (mặc định bỏ vì chưa hết ngày)")
    ap.add_argument("--buffer", type=float, default=0.0, help="hệ số dự phòng, ví dụ 0.1 = cộng thêm 10%%")
    ap.add_argument("--categories", help="các nhóm sản phẩm, cách nhau bằng dấu |")
    ap.add_argument("--model", choices=["auto", "hgb"], default="auto",
                    help="auto: tự chọn mô hình có sai số kiểm định thấp nhất; hgb: luôn dùng mô hình học máy")
    ap.add_argument("--dry-run", action="store_true", help="chỉ in kết quả, không ghi database")
    args = ap.parse_args()

    if args.env_file:
        load_env_file(args.env_file)
    run_day = date.fromisoformat(args.as_of) if args.as_of else date.today()
    target_date = pd.Timestamp(run_day + timedelta(days=1))
    cutoff = pd.Timestamp(run_day) + pd.Timedelta(days=1 if args.include_today else 0)
    categories = args.categories.split("|") if args.categories else DEFAULT_CATEGORIES

    print(f"=== Dự báo cho ngày {target_date:%d/%m/%Y} (chạy ngày {run_day:%d/%m/%Y}) ===")
    conn, ph = connect()
    try:
        orders, details, products = load_history(conn, cutoff, categories)
        last_day = orders["business_date"].max().date()
        if (run_day - last_day).days > 14:
            print(f"CẢNH BÁO: đơn hàng gần nhất là {last_day:%d/%m/%Y}, cách ngày chạy {(run_day - last_day).days} ngày. "
                  f"Dự báo dựa trên dữ liệu cũ - nên nhập đơn thật gần đây.")
        grid = build_daily_grid(orders, details, products)
        sold = grid.groupby("product_id")["units"].sum()
        grid = grid[grid["product_id"].isin(sold[sold > 0].index)]     # bỏ sản phẩm chưa từng bán
        tgt, version = run_model(grid, target_date, args.buffer, args.model == "hgb")

        names = products.set_index("product_id")["vi_name"]
        show = tgt.assign(ten=tgt["product_id"].map(names)).sort_values("predicted_qty", ascending=False)
        print(f"\nTổng sản lượng dự báo: {int(tgt['predicted_qty'].sum())} cái | Top 10:")
        for r in show.head(10).itertuples():
            print(f"  {r.ten:<38} {r.predicted_qty:>4}  (tin cậy {r.confidence:.2f})")
        write_forecasts(conn, ph, target_date, tgt.sort_values("product_id"), version, args.dry_run)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
