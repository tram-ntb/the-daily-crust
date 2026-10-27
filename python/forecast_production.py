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
# Phân loại (xem classify_products): Production = sản xuất tại bếp (kể cả bánh quy trẻ em), dự báo sản lượng MỖI NGÀY;
# Commercial = đồ uống / hàng thương mại / quà tặng nhập từ NCC, dự báo NHU CẦU 7 NGÀY để gợi ý nhập hàng.
# Mỗi sản phẩm có đúng 1 dòng trong production_forecasts (kèm item_group và horizon_days).
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

# PHÂN LOẠI SẢN PHẨM (37 sản phẩm) THEO CÁCH CỬA HÀNG VẬN HÀNH. Cả hai nhóm đều được dự báo, nhưng khác mục đích:
#   * Production (sản xuất tại bếp): bánh mì, bánh nướng, bánh ngọt, cookies, món mặn... KỂ CẢ bánh quy trẻ em
#     (hạn dùng 90 ngày nhưng vẫn làm thủ công tại tiệm). Dự báo SẢN LƯỢNG MỖI NGÀY để bếp lập kế hoạch mẻ nướng.
#   * Commercial (nhập từ nhà cung cấp): đồ uống, hàng thương mại, quà tặng. Dự báo NHU CẦU 7 NGÀY TỚI để Manager
#     quyết định khi nào nhập thêm và nhập bao nhiêu.
# Nhóm được xác định theo CATEGORY (không dựa vào hạn dùng nên không loại nhầm sản phẩm bếp có hạn dùng dài).
COMMERCIAL_CATEGORIES = ["Hàng thương mại & Quà tặng"]   # luôn là hàng nhập từ NCC
BOM_DECIDES_CATEGORIES = ["Đồ uống"]   # nhóm do CÔNG THỨC (BOM) quyết định: có BOM = pha chế tại quán, không BOM = đóng chai nhập từ NCC
GROUP_PRODUCTION = "Production"
GROUP_COMMERCIAL = "Commercial"
COMMERCIAL_HORIZON_DAYS = 7    # hàng nhập về: dự báo tổng nhu cầu của 7 ngày kể từ ngày dự báo
NO_HISTORY_CONFIDENCE = 0.05   # độ tin cậy gán cho sản phẩm chưa từng có lịch sử bán

# ------------------------------- LỊCH VÀ NGÀY LỄ -------------------------------
# Bảng ngày lễ / sự kiện. Thêm hoặc bớt dòng ở đây, mô hình tự dùng (không cần sửa chỗ khác).
#   (tên, tháng, ngày, is_gift): is_gift = dịp mua quà / bánh biếu, nhu cầu tăng mạnh với bánh ngọt, quà tặng
HOLIDAYS_SOLAR = [
    ("Tết Dương lịch", 1, 1, False),
    ("Valentine", 2, 14, True),
    ("Quốc tế Phụ nữ 8/3", 3, 8, True),
    ("Giải phóng miền Nam 30/4", 4, 30, False),
    ("Quốc tế Lao động 1/5", 5, 1, False),
    ("Quốc tế Thiếu nhi 1/6", 6, 1, True),
    ("Nghỉ lễ Quốc khánh 1/9", 9, 1, False),
    ("Quốc khánh 2/9", 9, 2, False),
    ("Phụ nữ Việt Nam 20/10", 10, 20, True),
    ("Nhà giáo Việt Nam 20/11", 11, 20, True),
    ("Đêm Giáng sinh", 12, 24, True),
    ("Giáng sinh", 12, 25, True),
]
# Ngày theo ÂM LỊCH đổi theo từng năm nên khai báo ngày dương lịch tương ứng (kiểm tra lại khi thêm năm mới):
TET_MUNG1 = {2024: date(2024, 2, 10), 2025: date(2025, 1, 29), 2026: date(2026, 2, 17),
             2027: date(2027, 2, 6), 2028: date(2028, 1, 26)}            # mùng 1 Tết Nguyên đán
MID_AUTUMN = {2024: date(2024, 9, 17), 2025: date(2025, 10, 6), 2026: date(2026, 9, 25),
              2027: date(2027, 9, 15), 2028: date(2028, 10, 3)}           # Tết Trung thu (rằm tháng 8 âm lịch)
TET_WINDOW = (-3, 5)               # 27 Tết -> mùng 6: cả giai đoạn Tết (mua sắm, nghỉ bán) được coi là kỳ nghỉ lễ
HOLIDAY_LOOKAHEAD = 30             # days_until_holiday tối đa 30 (xa hơn coi như "không có lễ sắp tới")

FEATURES_BASIC = ["product_id", "category_code", "price", "dow", "is_weekend", "month", "dom",
                  "is_holiday", "is_pre_holiday", "is_tet", "roll7", "roll28", "dow_mean4"]
FEATURES_CALENDAR = FEATURES_BASIC + ["is_month_start", "is_month_end", "days_to_month_end", "season",
                                      "days_until_holiday", "is_gift_day"]
FEATURES = FEATURES_CALENDAR       # main() có thể đổi sang FEATURES_BASIC bằng --feature-set basic (để so sánh trước/sau)


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
def load_history(conn, cutoff):
    orders = query_df(conn, "SELECT order_id, order_datetime FROM orders")
    details = query_df(conn, "SELECT order_id, product_id, quantity FROM order_details")
    products = query_df(conn, "SELECT product_id, vi_name, category, price, status, expiry_duration_day FROM products")

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
    products["expiry_duration_day"] = pd.to_numeric(products["expiry_duration_day"], errors="coerce")
    return orders, details, products.reset_index(drop=True)


def load_bom_product_ids(conn):
    """Tập product_id đã có công thức BOM; None nếu không đọc được bảng (khi đó bỏ qua quy tắc BOM)."""
    try:
        df = query_df(conn, "SELECT DISTINCT product_id FROM bill_of_materials WHERE product_id IS NOT NULL")
        return set(int(x) for x in df["product_id"])
    except Exception as e:
        print(f"CẢNH BÁO: không đọc được bill_of_materials ({e}); bỏ qua quy tắc 'phải có BOM'.")
        return None


def classify_products(products, bom_ids, commercial_categories, bom_decides_categories=None):
    """Thêm cột item_group (Production | Commercial | None nếu ngừng bán) và note (cảnh báo dữ liệu).

    Quy tắc:
      1. Ngừng bán -> bỏ qua.
      2. Thuộc commercial_categories (hàng thương mại, quà tặng) -> Commercial (nhập từ NCC).
      3. Thuộc bom_decides_categories (đồ uống): CÓ công thức BOM = pha chế tại quán -> Production;
         KHÔNG có BOM = hàng đóng chai nhập nguyên kiện (nước suối...) -> Commercial.
      4. Còn lại (bánh mì, bánh ngọt, món mặn, bánh quy trẻ em...) -> Production, bất kể hạn dùng.
    """
    bom_decides_categories = BOM_DECIDES_CATEGORIES if bom_decides_categories is None else bom_decides_categories
    group, note = [], []
    for r in products.itertuples():
        n = ""
        has_bom = bom_ids is None or int(r.product_id) in bom_ids
        if (r.status if isinstance(r.status, str) else "Active") != "Active":
            g, n = None, "Ngừng bán"
        elif r.category in commercial_categories:
            g = GROUP_COMMERCIAL
        elif r.category in bom_decides_categories:
            g = GROUP_PRODUCTION if has_bom else GROUP_COMMERCIAL
            n = "" if has_bom else "Không có công thức BOM: coi là hàng đóng chai nhập từ NCC"
        else:
            g = GROUP_PRODUCTION
            if not has_bom:
                n = "Chưa có công thức BOM (vẫn dự báo, nhưng phải khai báo BOM mới lập kế hoạch được)"
        group.append(g)
        note.append(n)
    return products.assign(item_group=group, note=note)


def print_classification(cls):
    print(f"\nPhân loại {len(cls)} sản phẩm:")
    for g, label in ((GROUP_PRODUCTION, "SẢN XUẤT tại bếp  -> dự báo sản lượng MỖI NGÀY"),
                     (GROUP_COMMERCIAL, "NHẬP từ NCC       -> dự báo nhu cầu 7 NGÀY (gợi ý nhập hàng)")):
        sub = cls[cls["item_group"] == g]
        print(f"  [{label}] {len(sub)} sản phẩm")
        for cat, grp in sub.groupby("category"):
            print(f"      {cat}: {len(grp)}")
    for r in cls[cls["item_group"].isna()].itertuples():
        print(f"  [BỎ QUA] #{r.product_id:<3} {r.vi_name:<40} -> {r.note}")
    for r in cls[cls["note"].ne("") & cls["item_group"].notna()].itertuples():
        print(f"  [LƯU Ý]  #{r.product_id:<3} {r.vi_name:<40} -> {r.note}")


def build_daily_grid(orders, details, products):
    """Bảng (ngày bán hàng x sản phẩm) với số lượng bán; ngày không có đơn nào thì KHÔNG đưa vào."""
    d = details.merge(orders[["order_id", "business_date"]], on="order_id", how="inner")
    d["date"] = d["business_date"]
    units = d.groupby(["date", "product_id"])["quantity"].sum()

    per_day = orders.groupby("business_date")["order_id"].nunique().sort_index()
    # so với trung vị CỤC BỘ (15 ngày bán quanh nó), không phải trung vị cả lịch sử: nếu quy mô tiệm đổi theo thời gian
    # (ví dụ 55 đơn/ngày rồi 200 đơn/ngày) thì so với trung vị toàn cục sẽ loại nhầm cả giai đoạn cũ
    local_median = per_day.rolling(15, center=True, min_periods=3).median()
    partial = per_day[per_day < MIN_DAY_RATIO * local_median]
    if len(partial):
        print(f"Loại {len(partial)} ngày dữ liệu dở dang (quá ít đơn so với ngày bình thường): "
              + ", ".join(f"{d:%d/%m/%Y}({n} đơn)" for d, n in partial.items()))
    days = pd.DatetimeIndex(sorted(per_day.drop(partial.index).index))
    grid = pd.MultiIndex.from_product([days, products["product_id"]], names=["date", "product_id"]).to_frame(index=False)
    grid = grid.merge(units.rename("units").reset_index(), on=["date", "product_id"], how="left")
    grid["units"] = grid["units"].fillna(0.0)
    return grid.merge(products[["product_id", "category", "price"]], on="product_id", how="left")


# ------------------------------- ĐẶC TRƯNG -----------------------------------
def holiday_events(years):
    """Danh sách (ngày, tên, is_gift) của mọi sự kiện trong các năm cho trước (dương lịch + âm lịch)."""
    ev = []
    for y in years:
        ev += [(pd.Timestamp(y, m, d), name, gift) for name, m, d, gift in HOLIDAYS_SOLAR]
        if y in TET_MUNG1:
            ev.append((pd.Timestamp(TET_MUNG1[y]), "Tết Nguyên đán", False))
        if y in MID_AUTUMN:
            ev.append((pd.Timestamp(MID_AUTUMN[y]), "Tết Trung thu", True))
    return sorted(ev, key=lambda e: e[0])


_warned_years = set()


def calendar_features(dates):
    """Đặc trưng lịch + ngày lễ cho một dãy ngày. Chỉ phụ thuộc vào LỊCH nên biết trước cho cả ngày dự báo (không rò rỉ)."""
    idx = pd.DatetimeIndex(dates)
    years = range(idx.year.min() - 1, idx.year.max() + 2)
    for y in range(idx.year.min(), idx.year.max() + 1):
        if y not in TET_MUNG1 and y not in _warned_years and y >= 2024:
            _warned_years.add(y)
            print(f"CẢNH BÁO: chưa khai báo Tết / Trung thu năm {y} trong TET_MUNG1 và MID_AUTUMN, đặc trưng ngày lễ năm đó thiếu.")
    ev = holiday_events(years)
    ev_dates = np.array([e[0].to_datetime64() for e in ev], dtype="datetime64[D]")
    gift_dates = {e[0] for e in ev if e[2]}
    ev_set = {e[0] for e in ev}

    out = pd.DataFrame(index=range(len(idx)))
    d64 = idx.values.astype("datetime64[D]")
    pos = np.searchsorted(ev_dates, d64, side="left")
    nxt = np.where(pos < len(ev_dates), ev_dates[np.clip(pos, 0, len(ev_dates) - 1)], d64 + np.timedelta64(HOLIDAY_LOOKAHEAD, 'D'))
    until = np.clip((nxt - d64).astype(int), 0, HOLIDAY_LOOKAHEAD)

    tet = np.zeros(len(idx), dtype=bool)
    for m1 in TET_MUNG1.values():
        delta = (idx - pd.Timestamp(m1)).days
        tet |= (delta >= TET_WINDOW[0]) & (delta <= TET_WINDOW[1])
    norm = idx.normalize()
    on_event = np.array([t in ev_set for t in norm])
    tomorrow = np.array([(t + pd.Timedelta(days=1)) in ev_set for t in norm])

    out["is_holiday"] = (on_event | tet).astype(int)               # đúng ngày lễ hoặc trong kỳ Tết
    out["is_pre_holiday"] = tomorrow.astype(int)                   # hôm sau là ngày lễ
    out["is_tet"] = tet.astype(int)
    out["days_until_holiday"] = until                              # 0 = hôm nay là lễ, 30 = chưa có lễ nào trong 30 ngày tới
    out["is_gift_day"] = np.array([t in gift_dates for t in norm]).astype(int)
    out["is_month_start"] = (idx.day <= 3).astype(int)
    out["days_to_month_end"] = (idx.days_in_month - idx.day).astype(int)
    out["is_month_end"] = (out["days_to_month_end"] <= 2).astype(int)
    out["season"] = ((idx.month - 1) // 3).astype(int)             # 0 Xuân (T1-3), 1 Hạ (T4-6), 2 Thu (T7-9), 3 Đông (T10-12)
    return out


def add_features(df):
    """Mọi đặc trưng 'quá khứ' đều shift(1) nên không nhìn thấy ngày hiện tại (không rò rỉ)."""
    df = df.sort_values(["product_id", "date"]).reset_index(drop=True)
    df["dow"] = df["date"].dt.dayofweek
    df["is_weekend"] = (df["dow"] >= 5).astype(int)
    df["month"] = df["date"].dt.month
    df["dom"] = df["date"].dt.day
    cal = calendar_features(df["date"]).set_index(df.index)
    for col in cal.columns:
        df[col] = cal[col]
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
    conf[chk["y"] <= 0] = NO_HISTORY_CONFIDENCE      # không có đơn nào trong tập kiểm định: không có bằng chứng, không cho điểm cao
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


# ------------------------------- NHÓM HÀNG NHẬP VỀ ----------------------------
def forecast_commercial(grid, target_date, horizon):
    """Dự báo TỔNG nhu cầu `horizon` ngày tới cho hàng nhập về.

    Vì sao khác nhóm sản xuất: hàng thương mại/quà tặng bán rời rạc (nhiều ngày bán 0, vài ngày bán 1-2 cái) nên dự báo
    từng ngày chỉ ra 0 hoặc 1 và vô nghĩa. Gộp theo tuần ổn định hơn nhiều, và quyết định nhập hàng cũng theo tuần.
    Ba phương pháp mức nền (TB 28 ngày bán, TB 56 ngày bán, làm mượt mũ) được chấm bằng kiểm định lăn theo thời gian trên
    tổng nhu cầu 7 ngày (nhiều mốc cách nhau 1 tuần), chọn phương pháp có WAPE thấp nhất cho cả nhóm.
    Trả về (DataFrame product_id, predicted_qty, confidence), tên phiên bản mô hình."""
    piv = grid.pivot(index="date", columns="product_id", values="units").sort_index().fillna(0.0)
    n = len(piv)
    methods = {
        "Mean28": lambda h: h.tail(28).mean(),
        "Mean56": lambda h: h.tail(56).mean(),
        "EWMA": lambda h: h.ewm(alpha=0.05, adjust=False).mean().iloc[-1],
    }

    origins = list(range(max(56, n - horizon - 7 * 12), n - horizon + 1, 7)) if n >= 56 + horizon else []
    err = {m: pd.Series(0.0, index=piv.columns) for m in methods}
    act = pd.Series(0.0, index=piv.columns)
    for t in origins:
        actual = piv.iloc[t:t + horizon].sum()
        act += actual
        for m, fn in methods.items():
            err[m] += (actual - fn(piv.iloc[:t]) * horizon).abs()

    if origins and act.sum() > 0:
        scores = {m: float(err[m].sum() / act.sum()) for m in methods}
        for m, sc in scores.items():
            print(f"  [hàng nhập] {m:<7} WAPE tổng nhu cầu {horizon} ngày = {sc:.1%}  ({len(origins)} mốc kiểm định)")
        best = min(scores, key=scores.get)
        conf = (1 - err[best] / act.clip(lower=1e-9)).clip(0.05, 0.99)
        conf[act <= 0] = NO_HISTORY_CONFIDENCE
    else:
        print("  [hàng nhập] Lịch sử quá ngắn để kiểm định, dùng Mean28 và độ tin cậy thấp.")
        best, conf = "Mean28", pd.Series(NO_HISTORY_CONFIDENCE, index=piv.columns)
    print(f"  [hàng nhập] => Chọn: {best}")

    level = methods[best](piv)
    out = pd.DataFrame({"product_id": piv.columns.astype(int),
                        "predicted_qty": np.rint(np.clip(level.to_numpy(), 0, None) * horizon).astype(int),
                        "confidence": conf.reindex(piv.columns).fillna(NO_HISTORY_CONFIDENCE).round(2).to_numpy()})
    return out, f"Weekly-{best}-v1.0"


# ------------------------------- GHI DATABASE --------------------------------
def has_group_columns(conn):
    """Bảng production_forecasts đã có item_group và horizon_days chưa (migration 2026_10_24_000002)."""
    cur = conn.cursor()
    try:
        cur.execute("SELECT item_group, horizon_days FROM production_forecasts WHERE 1=0")
        return True
    except Exception:
        return False
    finally:
        cur.close()


def write_forecasts(conn, ph, target_date, rows, dry_run):
    """rows: product_id, predicted_qty, confidence, model_version, item_group, horizon_days (mỗi sản phẩm đúng 1 dòng)."""
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    fdate = f"{target_date:%Y-%m-%d} 00:00:00"
    with_group = has_group_columns(conn)
    if not with_group:
        print("\nCẢNH BÁO: production_forecasts chưa có cột item_group/horizon_days. Chạy `php artisan migrate` để thêm. "
              "Tạm thời CHỈ ghi nhóm sản xuất (hàng nhập về chỉ được in ra, không ghi DB).")
        rows = rows[rows["item_group"] == GROUP_PRODUCTION]

    cols = "(forecast_id, forecast_date, product_id, predicted_qty, model_version, confidence, created_at" \
           + (", item_group, horizon_days)" if with_group else ")")
    cur = conn.cursor()
    cur.execute("SELECT COALESCE(MAX(forecast_id), 0) FROM production_forecasts")
    next_id = int(cur.fetchone()[0]) + 1
    data = []
    for i, r in enumerate(rows.itertuples()):
        row = (next_id + i, fdate, int(r.product_id), int(r.predicted_qty), r.model_version, float(r.confidence), now)
        data.append(row + ((r.item_group, int(r.horizon_days)) if with_group else ()))

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
        cur.executemany(f"INSERT INTO production_forecasts {cols} VALUES ({', '.join([ph] * len(data[0]))})", data)
        conn.commit()
        print(f"Đã ghi {len(data)} dòng vào production_forecasts.")
    except Exception:
        conn.rollback()
        raise
    finally:
        cur.close()


# ------------------------------- MAIN ----------------------------------------
def main():
    ap = argparse.ArgumentParser(description="Dự báo sản lượng sản xuất và nhu cầu hàng nhập về cho The Daily Crust")
    ap.add_argument("--env-file", help="đường dẫn .env của Laravel (đọc DB_HOST, DB_DATABASE...)")
    ap.add_argument("--as-of", help="giả lập ngày chạy, định dạng YYYY-MM-DD (mặc định: hôm nay)")
    ap.add_argument("--include-today", action="store_true", help="tính cả đơn của ngày chạy (mặc định bỏ vì chưa hết ngày)")
    ap.add_argument("--buffer", type=float, default=0.0,
                    help="hệ số dự phòng cho NHÓM SẢN XUẤT, ví dụ 0.1 = cộng thêm 10%%")
    ap.add_argument("--commercial-categories", help="các nhóm hàng NHẬP VỀ (không sản xuất tại bếp), cách nhau bằng dấu | "
                    "(mặc định: Đồ uống|Hàng thương mại & Quà tặng)")
    ap.add_argument("--bom-decides-categories", help="nhóm hàng do BOM quyết định (có BOM = pha chế tại quán, không BOM = nhập từ NCC), "
                    "cách nhau bằng dấu | (mặc định: Đồ uống)")
    ap.add_argument("--horizon", type=int, default=COMMERCIAL_HORIZON_DAYS,
                    help="số ngày nhu cầu cần dự báo cho hàng nhập về (mặc định 7)")
    ap.add_argument("--model", choices=["auto", "hgb"], default="auto",
                    help="nhóm sản xuất - auto: tự chọn mô hình có sai số kiểm định thấp nhất; hgb: luôn dùng mô hình học máy")
    ap.add_argument("--feature-set", choices=["calendar", "basic"], default="calendar",
                    help="calendar (mặc định): thêm đặc trưng đầu/cuối tháng, mùa, ngày lễ; basic: bộ đặc trưng cũ, để so sánh")
    ap.add_argument("--dry-run", action="store_true", help="chỉ in kết quả, không ghi database")
    args = ap.parse_args()
    global FEATURES
    FEATURES = FEATURES_CALENDAR if args.feature_set == "calendar" else FEATURES_BASIC

    if args.env_file:
        load_env_file(args.env_file)
    run_day = date.fromisoformat(args.as_of) if args.as_of else date.today()
    target_date = pd.Timestamp(run_day + timedelta(days=1))
    cutoff = pd.Timestamp(run_day) + pd.Timedelta(days=1 if args.include_today else 0)
    commercial_categories = args.commercial_categories.split("|") if args.commercial_categories else COMMERCIAL_CATEGORIES

    print(f"=== Dự báo cho ngày {target_date:%d/%m/%Y} (chạy ngày {run_day:%d/%m/%Y}) ===")
    conn, ph = connect()
    try:
        orders, details, products_all = load_history(conn, cutoff)
        bom_decides = args.bom_decides_categories.split("|") if args.bom_decides_categories else BOM_DECIDES_CATEGORIES
        cls = classify_products(products_all, load_bom_product_ids(conn), commercial_categories, bom_decides)
        print_classification(cls)
        products = cls[cls["item_group"].notna()].reset_index(drop=True)

        last_day = orders["business_date"].max().date()
        if (run_day - last_day).days > 14:
            print(f"CẢNH BÁO: đơn hàng gần nhất là {last_day:%d/%m/%Y}, cách ngày chạy {(run_day - last_day).days} ngày. "
                  f"Dự báo dựa trên dữ liệu cũ - nên nhập đơn thật gần đây.")
        grid = build_daily_grid(orders, details, products)
        sold = grid.groupby("product_id")["units"].sum()
        grid = grid[grid["product_id"].isin(sold[sold > 0].index)]     # mô hình chỉ học từ sản phẩm đã từng bán
        names = products.set_index("product_id")["vi_name"]
        parts = []

        # --- Nhóm 1: sản xuất tại bếp -> sản lượng mỗi ngày
        g_prod = products[products["item_group"] == GROUP_PRODUCTION]
        grid_p = grid[grid["product_id"].isin(g_prod["product_id"])]
        print(f"\n--- NHÓM SẢN XUẤT ({len(g_prod)} sản phẩm): sản lượng cần làm trong ngày ---")
        if grid_p.empty:
            sys.exit("Không có lịch sử bán của nhóm sản xuất để huấn luyện.")
        tgt, version = run_model(grid_p, target_date, args.buffer, args.model == "hgb")
        tgt = tgt[["product_id", "predicted_qty", "confidence"]].assign(model_version=version)
        parts.append(tgt.assign(item_group=GROUP_PRODUCTION, horizon_days=1))

        # --- Nhóm 2: hàng nhập về -> nhu cầu 7 ngày để gợi ý nhập hàng
        g_com = products[products["item_group"] == GROUP_COMMERCIAL]
        grid_c = grid[grid["product_id"].isin(g_com["product_id"])]
        print(f"\n--- NHÓM HÀNG NHẬP VỀ ({len(g_com)} sản phẩm): nhu cầu {args.horizon} ngày tới ---")
        if grid_c.empty:
            print("  Không có lịch sử bán của nhóm hàng nhập về; các sản phẩm sẽ được ghi dự báo 0.")
        else:
            com, cver = forecast_commercial(grid_c, target_date, args.horizon)
            parts.append(com.assign(model_version=cver, item_group=GROUP_COMMERCIAL, horizon_days=args.horizon))

        out = pd.concat(parts, ignore_index=True)

        # MỌI sản phẩm đều có đúng một dòng: chưa từng bán thì dự báo 0 (độ tin cậy thấp nhất)
        missing = products[~products["product_id"].isin(out["product_id"])]
        if len(missing):
            print("Chưa có lịch sử bán, dự báo 0: " + ", ".join(f"#{r.product_id} {r.vi_name}" for r in missing.itertuples()))
            out = pd.concat([out, pd.DataFrame({
                "product_id": missing["product_id"].astype(int), "predicted_qty": 0, "confidence": NO_HISTORY_CONFIDENCE,
                "model_version": "No-history", "item_group": missing["item_group"],
                "horizon_days": missing["item_group"].map({GROUP_PRODUCTION: 1, GROUP_COMMERCIAL: args.horizon})})],
                ignore_index=True)

        for g, unit in ((GROUP_PRODUCTION, "cái / ngày"), (GROUP_COMMERCIAL, f"cái / {args.horizon} ngày")):
            sub = out[out["item_group"] == g].assign(ten=lambda d: d["product_id"].map(names)).sort_values("predicted_qty", ascending=False)
            print(f"\n{g}: {len(sub)} sản phẩm, tổng {int(sub['predicted_qty'].sum())} ({unit}) | Top 8:")
            for r in sub.head(8).itertuples():
                print(f"  #{r.product_id:<3} {r.ten:<38} {r.predicted_qty:>5}  (tin cậy {r.confidence:.2f})")

        write_forecasts(conn, ph, target_date, out.sort_values(["item_group", "product_id"]), args.dry_run)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
