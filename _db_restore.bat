@echo off
title SUT ChemBot - DB Restore
chcp 65001 >nul
setlocal enabledelayedexpansion

echo ============================================
echo   SUT ChemBot  Database Restore
echo   DB: chem_inventory_db  ^|  Host: localhost
echo ============================================
echo.

set DB_HOST=localhost
set DB_NAME=chem_inventory_db
set DB_USER=root
set DB_PASS=
set BACKUP_DIR=C:\xampp\htdocs\v1\backups
set MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe

if not exist "%MYSQL_EXE%" (
    echo [ERROR] mysql.exe not found: %MYSQL_EXE%
    pause & exit /b 1
)
if not exist "%BACKUP_DIR%\" (
    echo [ERROR] Backup folder not found: %BACKUP_DIR%
    pause & exit /b 1
)

echo Available backups:
echo.
set IDX=0
for /f "tokens=*" %%F in ('dir /b /o-d "%BACKUP_DIR%\backup_*.sql" "%BACKUP_DIR%\backup_*.zip" 2^>nul') do (
    set /a IDX+=1
    set "FILE_!IDX!=%%F"
    echo   [!IDX!] %%F
)
if "!IDX!"=="0" (
    echo [ERROR] No backup files found in %BACKUP_DIR%
    pause & exit /b 1
)
echo.
set /p CHOICE=Select backup number (1-!IDX!): 
if "!CHOICE!"=="" (echo Cancelled. & pause & exit /b 0)

set "SELECTED=!FILE_%CHOICE%!"
if "!SELECTED!"=="" (echo [ERROR] Invalid selection. & pause & exit /b 1)

echo.
echo [INFO] Selected : !SELECTED!
echo.

set "IMPORT_DIR=%BACKUP_DIR%"
set "IMPORT_FILE=!SELECTED!"
set "TEMP_EXTRACT="

echo !SELECTED! | findstr /i "\.zip$" >nul
if !errorlevel! equ 0 (
    echo [INFO] Extracting ZIP...
    set "TEMP_EXTRACT=%TEMP%\chembot_%RANDOM%"
    mkdir "!TEMP_EXTRACT!"
    powershell -Command "Expand-Archive -Force -Path '%BACKUP_DIR%\!SELECTED!' -DestinationPath '!TEMP_EXTRACT!'"
    if !errorlevel! neq 0 (echo [ERROR] Extraction failed. & pause & exit /b 1)
    set "IMPORT_DIR=!TEMP_EXTRACT!"
    for /f %%F in ('dir /b /o-n "!TEMP_EXTRACT!\*.sql" 2^>nul') do (
        if "!IMPORT_FILE!"=="!SELECTED!" set "IMPORT_FILE=%%F"
    )
    echo [INFO] Will import: !IMPORT_FILE!
    echo.
)

set "BASE_NAME=!IMPORT_FILE!"
echo !IMPORT_FILE! | findstr /r "_part[0-9]" >nul
if !errorlevel! equ 0 (
    powershell -Command "'!IMPORT_FILE!' -replace '_part\d+\.sql$',''" > "%TEMP%\chembot_base.txt"
    set /p BASE_NAME=<"%TEMP%\chembot_base.txt"
    set "BASE_NAME=!BASE_NAME: =!"
) else (
    set "BASE_NAME=!IMPORT_FILE:.sql=!"
)

set PART_COUNT=0
for /f %%F in ('dir /b /o-n "!IMPORT_DIR!\!BASE_NAME!*.sql" 2^>nul') do set /a PART_COUNT+=1

echo [INFO] Base name : !BASE_NAME!
if !PART_COUNT! gtr 1 (
    echo [INFO] Multipart : !PART_COUNT! parts found
) else (
    echo [INFO] Single file
)
echo.
echo [WARN] This will OVERWRITE database: %DB_NAME%
echo.
set /p CONFIRM=Type YES to continue: 
if /i not "!CONFIRM!"=="YES" (echo Cancelled. & pause & exit /b 0)
echo.

if "!DB_PASS!"=="" (
    set "MYSQL_AUTH=-h !DB_HOST! -u !DB_USER! --password="
) else (
    set "MYSQL_AUTH=-h !DB_HOST! -u !DB_USER! --password=!DB_PASS!"
)

echo [INFO] Connecting to MySQL...
"%MYSQL_EXE%" !MYSQL_AUTH! -e "CREATE DATABASE IF NOT EXISTS `!DB_NAME!` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if !errorlevel! neq 0 (echo [ERROR] Cannot connect. Is XAMPP running? & pause & exit /b 1)

set PART_NUM=0
for /f "tokens=*" %%F in ('dir /b /o-n "!IMPORT_DIR!\!BASE_NAME!*.sql" 2^>nul') do (
    set /a PART_NUM+=1
    echo [INFO] Importing !PART_NUM!/!PART_COUNT!: %%F
    "%MYSQL_EXE%" !MYSQL_AUTH! !DB_NAME! < "!IMPORT_DIR!\%%F"
    if !errorlevel! neq 0 (
        echo [ERROR] Failed on: %%F
        if not "!TEMP_EXTRACT!"=="" powershell -Command "Remove-Item -Recurse -Force '!TEMP_EXTRACT!'" 2>nul
        pause & exit /b 1
    )
    echo        OK
)

if not "!TEMP_EXTRACT!"=="" powershell -Command "Remove-Item -Recurse -Force '!TEMP_EXTRACT!'" 2>nul

echo.
echo ============================================
echo   Restore complete! !PART_NUM! file(s) imported
echo   Database : !DB_NAME!
echo   Host     : !DB_HOST!
echo ============================================
echo.
pause