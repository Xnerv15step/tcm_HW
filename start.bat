@echo off
setlocal

cd /d "%~dp0"

if not exist "generated" (
  mkdir "generated" >nul 2>nul
)

echo Starting server: http://127.0.0.1:8000/index.php
start "" "http://127.0.0.1:8000/index.php"
php -S 127.0.0.1:8000 -t .

