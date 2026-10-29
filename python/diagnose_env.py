# -*- coding: utf-8 -*-
"""In ra Python nào đang chạy và từng thư viện có import được không (kèm lỗi đầy đủ). Dùng cho /admin/forecast/diagnose."""
import importlib
import os
import sys
import traceback

print("python:", sys.executable)
print("version:", sys.version.split()[0])
print("prefix:", sys.prefix)
try:
    import site
    print("user_site:", site.getusersitepackages(), "| tồn tại:", os.path.isdir(site.getusersitepackages()))
except Exception as e:  # noqa: BLE001
    print("user_site: lỗi", e)
print("env:", ", ".join(f"{k}={'có' if os.environ.get(k) else 'THIẾU'}" for k in
      ("SystemRoot", "USERPROFILE", "APPDATA", "LOCALAPPDATA", "TEMP", "PYTHONHOME", "PYTHONPATH")))
print()

ok = True
for name in ("numpy", "scipy", "joblib", "threadpoolctl", "pandas", "sklearn", "sklearn.ensemble", "pymysql"):
    try:
        m = importlib.import_module(name)
        print(f"OK   {name:18s} {getattr(m, '__version__', '')}")
    except Exception:  # noqa: BLE001
        ok = False
        print(f"LỖI  {name}")
        print(traceback.format_exc().strip())
        print()
print()
print("KẾT LUẬN:", "mọi thư viện import được" if ok else "có thư viện KHÔNG import được, xem dòng LỖI ở trên (dòng cuối của traceback là nguyên nhân)")
