@echo off
setlocal

set "SRC_EXE=%~dp0package\dist\SentinelPersistentAgent.exe"
set "SRC_ENV=%~dp0.env"
set "SRC_ENV_EXAMPLE=%~dp0.env.example"
set "INSTALL_DIR=%ProgramData%\SentinelPersistentAgent"
set "INSTALL_EXE=%INSTALL_DIR%\SentinelPersistentAgent.exe"
set "INSTALL_ENV=%INSTALL_DIR%\.env"
set "TASK_NAME=SentinelPersistentAgent"
set "INSTALL_OK=0"

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo This installer must be run as Administrator.
    pause
    exit /b 1
)

if not exist "%SRC_EXE%" (
    echo Missing package\dist\SentinelPersistentAgent.exe
    echo Run build_agent.bat first or copy the built package folder here.
    pause
    exit /b 1
)

if not exist "%SRC_ENV%" (
    echo Missing .env next to this installer.
    echo Copy .env.example to .env and update the API values first.
    pause
    exit /b 1
)

findstr /B /C:"SENTINEL_API_BASE=" "%SRC_ENV%" >nul 2>&1
if %errorlevel% neq 0 (
    echo .env is missing SENTINEL_API_BASE.
    pause
    exit /b 1
)

findstr /B /C:"SENTINEL_API_KEY=" "%SRC_ENV%" >nul 2>&1
if %errorlevel% neq 0 (
    echo .env is missing SENTINEL_API_KEY.
    pause
    exit /b 1
)

findstr /B /C:"SENTINEL_ADMIN_KEY=" "%SRC_ENV%" >nul 2>&1
if %errorlevel% neq 0 (
    echo .env is missing SENTINEL_ADMIN_KEY.
    pause
    exit /b 1
)

echo Stopping any old Sentinel agent processes...
taskkill /F /IM SentinelPersistentAgent.exe >nul 2>&1

echo Preparing installation folder...
if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"
if %errorlevel% neq 0 (
    echo Failed to create %INSTALL_DIR%
    pause
    exit /b 1
)

copy /Y "%SRC_EXE%" "%INSTALL_EXE%" >nul
if %errorlevel% neq 0 (
    echo Failed to copy SentinelPersistentAgent.exe into %INSTALL_DIR%
    pause
    exit /b 1
)

copy /Y "%SRC_ENV%" "%INSTALL_ENV%" >nul
if %errorlevel% neq 0 (
    echo Failed to copy .env into %INSTALL_DIR%
    pause
    exit /b 1
)

if exist "%SRC_ENV_EXAMPLE%" copy /Y "%SRC_ENV_EXAMPLE%" "%INSTALL_DIR%\.env.example" >nul
attrib +h +s "%INSTALL_DIR%" >nul 2>&1

echo Creating startup task...
schtasks /Create /TN "%TASK_NAME%" /TR "\"%INSTALL_EXE%\" run" /SC ONSTART /RU SYSTEM /RL HIGHEST /F
if %errorlevel% neq 0 (
    echo Failed to create the Windows startup task.
    echo Check whether Task Scheduler allows creation as SYSTEM on this PC.
    pause
    exit /b 1
)

set "INSTALL_OK=1"
echo Starting the agent now...
schtasks /Run /TN "%TASK_NAME%" >nul 2>&1
if %errorlevel% neq 0 (
    echo Task was created, but immediate start failed.
    echo Reboot the PC once and the task should start automatically on boot.
    echo You can also start it manually from Task Scheduler: %TASK_NAME%
)

if "%INSTALL_OK%"=="1" (
    echo Installation successful.
    echo The agent has started now in the background.
    echo It will also start automatically at every boot for all users.
) else (
    echo Installation failed.
    echo.
    echo Check these items:
    echo 1. Run this file with Administrator privileges.
    echo 2. Make sure .env has real values, not example placeholders.
    echo 3. Make sure the folder is extracted locally and not being run from inside a zip.
    echo 4. Confirm Windows allows Task Scheduler creation as SYSTEM.
    echo 5. Check whether C:\ProgramData\SentinelPersistentAgent exists after the attempt.
    echo 6. If that folder exists, open persistent_agent_debug.log inside it for details.
)

pause
