@echo off
echo ========================================
echo   Mirza Pro Web Panel - Local Server
echo ========================================
echo.
echo Starting PHP server on http://localhost:8000
echo.
echo Open your browser and go to:
echo   http://localhost:8000/webpanel/
echo.
echo Press Ctrl+C to stop the server
echo ========================================
echo.

REM Try to find PHP in common locations
if exist "C:\php\php.exe" (
    "C:\php\php.exe" -S localhost:8000 -t "%~dp0.."
) else if exist "C:\xampp\php\php.exe" (
    "C:\xampp\php\php.exe" -S localhost:8000 -t "%~dp0.."
) else if exist "C:\wamp64\bin\php\php8.1.0\php.exe" (
    "C:\wamp64\bin\php\php8.1.0\php.exe" -S localhost:8000 -t "%~dp0.."
) else (
    echo ERROR: PHP not found!
    echo.
    echo Please install PHP or update this batch file with your PHP path.
    echo Common PHP locations:
    echo   - C:\php\php.exe
    echo   - C:\xampp\php\php.exe
    echo   - C:\wamp64\bin\php\[version]\php.exe
    echo.
    echo Or add PHP to your system PATH.
    pause
)
