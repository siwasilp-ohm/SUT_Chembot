@echo off
title SUT ChemBot - Git Push
chcp 65001 >nul
setlocal enabledelayedexpansion

echo ============================================
echo   SUT ChemBot  Git Push to GitHub
echo ============================================
echo.

cd /d "C:\xampp\htdocs\v1"

set GIT_EXE=
for %%G in (
    "C:\Program Files\Git\bin\git.exe"
    "C:\Program Files (x86)\Git\bin\git.exe"
) do if exist %%G if "!GIT_EXE!"=="" set GIT_EXE=%%~G
if "!GIT_EXE!"=="" (
    where git >nul 2>&1
    if !errorlevel! equ 0 set GIT_EXE=git
)
if "!GIT_EXE!"=="" (
    echo [ERROR] Git not found. Install: https://git-scm.com/download/win
    pause & exit /b 1
)

echo [INFO] Dir    : %CD%
echo [INFO] Remote :
"!GIT_EXE!" remote get-url origin 2>nul || echo   (no remote)
echo.

:: -- Show changed files ---------------------
echo Changed files:
"!GIT_EXE!" status --short
echo.

:: -- Check if anything to commit ------------
"!GIT_EXE!" status --porcelain > "%TEMP%\chembot_status.txt" 2>nul
for %%A in ("%TEMP%\chembot_status.txt") do set STAT_SIZE=%%~zA
if "!STAT_SIZE!"=="0" (
    echo [INFO] Nothing to commit. Already up to date.
    pause & exit /b 0
)

:: -- Commit message --------------------------
set /p MSG=Commit message (Enter for auto): 
if "!MSG!"=="" (
    for /f "tokens=2 delims==" %%D in ('wmic os get localdatetime /value 2^>nul') do set DT=%%D
    set "MSG=Update !DT:~0,4!-!DT:~4,2!-!DT:~6,2! !DT:~8,2!:!DT:~10,2!"
)
echo.
echo [INFO] Message : !MSG!
echo.

:: -- Stage all -------------------------------
echo [INFO] Staging all changes...
"!GIT_EXE!" add --all

:: -- Commit -----------------------------------
echo [INFO] Committing...
"!GIT_EXE!" commit -m "!MSG!"
if !errorlevel! neq 0 (
    echo [ERROR] Commit failed.
    pause & exit /b 1
)

:: -- Push -------------------------------------
echo.
echo [INFO] Pushing to origin/master...
"!GIT_EXE!" push origin master
if !errorlevel! neq 0 (
    echo.
    echo [ERROR] Push failed. Check internet or GitHub credentials.
    pause & exit /b 1
)

echo.
echo ============================================
echo   Done! Latest commit:
"!GIT_EXE!" log --oneline -1
echo ============================================
echo.
pause