@echo off
rem Chay file nay 1 LAN (chuot phai > Run as administrator) de tao tac vu "moi phut goi schedule:run".
set TASK=TheDailyCrust-Scheduler
set SCRIPT=%~dp0chay_scheduler.bat
schtasks /Create /F /TN "%TASK%" /SC MINUTE /MO 1 /TR "\"%SCRIPT%\""
echo.
echo Da tao tac vu %TASK%. Kiem tra: schtasks /Query /TN %TASK%
echo Xoa tac vu:                    schtasks /Delete /TN %TASK% /F
pause
