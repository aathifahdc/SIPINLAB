@echo off
setlocal enabledelayedexpansion

set "backupDir=C:\laragon\www\sipinlab\backup"
set "mysqlDir=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin"

set "year=%date:~6,4%"
set "month=%date:~3,2%"
set "day=%date:~0,2%"
set "hour=%time:~0,2%"
set "minute=%time:~3,2%"

if "!hour:~0,1!"==" " set "hour=0!hour:~1,1!"

set "timestamp=!year!-!month!-!day!_!hour!-!minute!"

"%mysqlDir%\mysqldump.exe" -u SIPINLAB -psipinlab123 sipinlab > "%backupDir%\backup_!timestamp!.sql"

endlocal
