@echo off
chcp 65001 > NUL
echo ==========================================
echo      TEST SCENARIO FOR WRAPPER
echo ==========================================
echo.
echo 1. Testing Input (set /p)
echo ------------------------------------------
set /p name="Please enter your name: "
echo.
echo Hello, %name%!
echo.

echo 2. Testing Delays (Buffer flush)
echo ------------------------------------------
echo Waiting 2 seconds...
timeout /t 2 > NUL
echo Done waiting.
echo.

echo 3. Testing Progress Bar (Spam Filter)
echo ------------------------------------------
echo Simulating progress...
for /L %%i in (1,1,100) do (
    <nul set /p=".Processing item %%i%%"
    <nul set /p=^

    ping 127.0.0.1 -n 1 -w 10 > NUL
)
echo.
echo.
echo ==========================================
echo               TEST COMPLETE
echo ==========================================
pause
