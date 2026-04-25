@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"

set "MODE=%~1"
if "%MODE%"=="" set "MODE=full"

if /I "%MODE%"=="full" goto :run_full
if /I "%MODE%"=="setup" goto :run_setup
if /I "%MODE%"=="start" goto :run_start

echo Usage: run-project.bat [full^|setup^|start]
echo   full  - install deps, prepare app, then start servers (default)
echo   setup - install deps and prepare app only
echo   start - start servers only
exit /b 1

:require_command
where %~1 >nul 2>nul
if errorlevel 1 (
  echo Missing required command: %~1
  echo Install it and run this script again.
  exit /b 1
)
exit /b 0

:run_full
call :run_setup
if errorlevel 1 exit /b 1
call :run_start
exit /b %ERRORLEVEL%

:run_setup
echo [check] Verifying required commands...
call :require_command php
if errorlevel 1 exit /b 1
call :require_command composer
if errorlevel 1 exit /b 1
call :require_command npm
if errorlevel 1 exit /b 1

set "NEW_ENV=0"
echo [1/5] Ensuring .env exists...
if not exist ".env" (
  copy /Y ".env.example" ".env" >nul
  if errorlevel 1 (
    echo Failed to create .env from .env.example
    exit /b 1
  )
  set "NEW_ENV=1"
  echo Created .env from .env.example
)

if "!NEW_ENV!"=="1" (
  echo [2/5] Configuring sqlite for local startup...
  powershell -NoProfile -Command "$root=(Resolve-Path .).Path -replace '\\','/'; $dbPath=$root + '/database/database.sqlite'; $c=Get-Content .env; $c=$c -replace '^DB_CONNECTION=.*','DB_CONNECTION=sqlite'; $c=$c -replace '^DB_DATABASE=.*',('DB_DATABASE=' + $dbPath); Set-Content .env -Value $c"
  if errorlevel 1 (
    echo Failed to configure sqlite in .env
    exit /b 1
  )
)

for /f "tokens=1,* delims==" %%A in ('findstr /b "DB_CONNECTION=" ".env"') do set "DB_CONNECTION=%%B"
if /I "!DB_CONNECTION!"=="sqlite" (
  if not exist "database" mkdir "database"
  if not exist "database\database.sqlite" type nul > "database\database.sqlite"

  echo [2/5] Ensuring absolute sqlite path in .env...
  powershell -NoProfile -Command "$root=(Resolve-Path .).Path -replace '\\','/'; $dbPath=$root + '/database/database.sqlite'; $c=Get-Content .env; $c=$c -replace '^DB_DATABASE=.*',('DB_DATABASE=' + $dbPath); Set-Content .env -Value $c"
  if errorlevel 1 (
    echo Failed to update sqlite path in .env
    exit /b 1
  )
)

echo [3/5] Installing PHP dependencies...
call composer install --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 (
  echo Composer install failed.
  exit /b 1
)

echo [4/5] Installing Node dependencies...
call npm install --no-audit --no-fund
if errorlevel 1 (
  echo npm install failed.
  exit /b 1
)

echo [5/5] Preparing Laravel app...
findstr /b "APP_KEY=base64:" ".env" >nul
if errorlevel 1 (
  php artisan key:generate --ansi
  if errorlevel 1 (
    echo APP_KEY generation failed.
    exit /b 1
  )
)

php artisan migrate --force
if errorlevel 1 (
  echo Migration failed. Check your database settings in .env.
  exit /b 1
)

php artisan db:seed --class=RoleSeeder --force
if errorlevel 1 (
  echo Role seeding failed.
  exit /b 1
)

echo.
echo Setup complete.
exit /b 0

:run_start
echo NOTE: Vite at http://localhost:5173 is for assets/HMR only.
echo Open your Laravel app at http://localhost:8000
echo Starting Vite dev server in a new window...
start "Vite Dev Server" cmd /k "cd /d ""%~dp0"" && npm run dev"

echo Starting Laravel server at http://localhost:8000 ...
php artisan serve --host=localhost --port=8000
exit /b %ERRORLEVEL%