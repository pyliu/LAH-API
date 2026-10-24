@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"
set "PHP_BIN=C:\AppServ\php85\php.exe"
set "PHPUNIT_BIN=vendor\bin\phpunit"

if not exist "%PHP_BIN%" (
    echo [ERROR] Cannot find PHP 8.5 binary: %PHP_BIN%
    exit /b 1
)

if not exist "%PHPUNIT_BIN%" (
    echo [ERROR] Cannot find PHPUnit binary: %PHPUNIT_BIN%
    echo Please run composer install in API directory.
    exit /b 1
)

echo =======================================================
echo   LAH-API-v85 [PHP 8.5] Automated Test Runner
echo =======================================================

if "%~1"=="unit" (
    echo [*] Running Unit Test Suite...
    "%PHP_BIN%" "%PHPUNIT_BIN%" --testsuite Unit
    set "EXIT_CODE=!ERRORLEVEL!"
) else if "%~1"=="api" (
    echo [*] Running Integration Test Suite - 37 APIs...
    "%PHP_BIN%" "%PHPUNIT_BIN%" --testsuite Integration
    set "EXIT_CODE=!ERRORLEVEL!"
) else (
    echo [*] Running All Test Suites: Unit and Integration...
    "%PHP_BIN%" "%PHPUNIT_BIN%"
    set "EXIT_CODE=!ERRORLEVEL!"
)

echo.
if !EXIT_CODE! equ 0 (
    echo [SUCCESS] All tests passed!
) else (
    echo [FAILED] Tests failed with exit code: !EXIT_CODE!
)

pause
exit /b !EXIT_CODE!
