@echo off
setlocal

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo This uninstaller must be run as Administrator.
    pause
    exit /b 1
)

set "AGENT_EXE=%ProgramData%\SentinelPersistentAgent\SentinelPersistentAgent.exe"
if not exist "%AGENT_EXE%" (
    set "AGENT_EXE=%~dp0package\dist\SentinelPersistentAgent.exe"
)

if not exist "%AGENT_EXE%" (
    echo Could not find SentinelPersistentAgent.exe to uninstall.
    pause
    exit /b 1
)

call "%AGENT_EXE%" uninstall
if %errorlevel% equ 0 (
    echo Uninstallation successful.
) else (
    echo Uninstallation failed.
)

pause
