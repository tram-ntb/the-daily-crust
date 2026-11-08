@echo off
rem Chay bo lap lich cua Laravel 1 lan (Task Scheduler goi file nay moi phut).
rem Neu php khong nam trong PATH, sua dong duoi thanh duong dan day du, vi du: set PHP_EXE=C:\xampp\php\php.exe
set PHP_EXE=php
cd /d "%~dp0..\.."
"%PHP_EXE%" artisan schedule:run >> storage\logs\scheduler.log 2>&1
