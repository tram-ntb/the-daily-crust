@echo off
REM Chạy dự báo sản lượng ngày mai. Dùng cho Windows Task Scheduler lúc 21:00.
REM Sửa 3 đường dẫn bên dưới cho đúng máy của bạn.
set PROJECT=C:\xampp\htdocs\the-daily-crust
set PYTHON=python

cd /d %PROJECT%
if not exist storage\logs mkdir storage\logs
echo ===== %date% %time% ===== >> storage\logs\forecast.log
%PYTHON% python\forecast_production.py --env-file %PROJECT%\.env >> storage\logs\forecast.log 2>&1
