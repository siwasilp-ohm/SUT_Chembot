@echo off
title SUT ChemBot - Git Pull

echo ============================================
echo   SUT ChemBot  Git Pull from GitHub
echo ============================================
echo.

cd /d "C:\xampp\htdocs\v1"
echo [INFO] Directory : %CD%

set GIT_EXE=
for %%G in (
    "C:\Program Files\Git\bin\git.exe"
    "C:\Program Files (x86)\Git\bin\git.exe"
    "%LOCALAPPDATA%\Programs\Git\bin\git.exe"
) do if exist %%G if "%GIT_EXE%"=="" set GIT_EXE=%%~G

if "%GIT_EXE%"=="" (
    where git >nul 2>&1
    if %errorlevel% equ 0 set GIT_EXE=git
)
if "%GIT_EXE%"=="" (
    echo [ERROR] Git not found. Install: https://git-scm.com/download/win
    pause & exit /b 1
)
echo [INFO] Git       : %GIT_EXE%

:: Init repo if needed
"%GIT_EXE%" rev-parse --git-dir >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Initializing git repo...
    "%GIT_EXE%" init
    "%GIT_EXE%" branch -M master
)

:: Add remote if missing
"%GIT_EXE%" remote get-url origin >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Adding remote origin...
    "%GIT_EXE%" remote add origin https://github.com/siwasilp-ohm/SUT_Chembot.git
)

echo [INFO] Remote    :
"%GIT_EXE%" remote get-url origin
echo.

:: Warn if local files are modified
"%GIT_EXE%" status --porcelain > "%TEMP%\git_status.txt" 2>nul
for %%A in ("%TEMP%\git_status.txt") do if %%~zA gtr 0 (
    echo [WARN] Local changes will be OVERWRITTEN by the latest GitHub version.
    echo        (Your local edits will be lost)
    echo.
    set /p CONFIRM=Continue? (Y/N): 
    if /i not "%CONFIRM%"=="Y" (
        echo Cancelled.
        pause & exit /b 0
    )
    echo.
)

echo [INFO] Fetching from GitHub...
"%GIT_EXE%" fetch origin
if %errorlevel% neq 0 (
    echo [ERROR] Cannot reach GitHub. Check internet connection.
    pause & exit /b 1
)

echo [INFO] Resetting to origin/master...
"%GIT_EXE%" reset --hard origin/master
if %errorlevel% neq 0 (
    echo [ERROR] Reset failed.
    pause & exit /b 1
)

echo.
echo ============================================
echo   Done! Code is up to date.
echo ============================================
"%GIT_EXE%" log --oneline -3
echo.
pause