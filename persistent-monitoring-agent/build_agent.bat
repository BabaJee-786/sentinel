@echo off
setlocal

where python >nul 2>&1
if %errorlevel% neq 0 (
    echo Python was not found in PATH.
    exit /b 1
)

echo Building Sentinel Persistent Agent executable...
python -m PyInstaller --noconfirm --clean --onefile --noconsole --name SentinelPersistentAgent --distpath "%~dp0package\dist" --workpath "%~dp0package\build" --specpath "%~dp0package" "%~dp0agent.py"

if %errorlevel% neq 0 (
    echo Build failed.
    exit /b 1
)

if exist "%~dp0.env.example" copy /Y "%~dp0.env.example" "%~dp0package\dist\.env.example" >nul

echo Build complete.
echo Executable: %~dp0package\dist\SentinelPersistentAgent.exe
