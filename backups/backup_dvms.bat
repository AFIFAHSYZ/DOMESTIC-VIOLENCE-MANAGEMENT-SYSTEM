@echo off
setlocal

REM === Generate safe timestamp ===
for /f "tokens=2 delims==." %%I in ('"wmic os get localdatetime /value"') do set dt=%%I
set DATESTAMP=%dt:~0,8%_%dt:~8,6%

REM === Set environment and paths ===
set PGPASSWORD=afifah
set PATH=C:\Program Files\PostgreSQL\17\bin;%PATH%
set BACKUP_DIR=C:\xampp\htdocs\psm\backups

REM === Show what will happen ===
echo Backup directory: "%BACKUP_DIR%"
echo Backup filename:  "%BACKUP_DIR%\DVMS_%DATESTAMP%.backup"

REM === Make sure the backup directory exists ===
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM === Run backup ===
echo Running:
echo pg_dump -U postgres -h localhost -F c -b -v -f "%BACKUP_DIR%\DVMS_%DATESTAMP%.backup" DVMS

"C:\Program Files\PostgreSQL\17\bin\pg_dump.exe" -U postgres -h localhost -F c -b -v -f "%BACKUP_DIR%\DVMS_%DATESTAMP%.backup" DVMS

