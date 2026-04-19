# Sentinel Persistent Monitoring Agent

This folder contains a separate Windows deployment package for a persistent lab monitoring agent.

## What it does

- Builds a compiled `.exe` into `package\dist\SentinelPersistentAgent.exe`
- Installs once with Administrator privileges
- Starts monitoring immediately after installation
- Runs silently in the background with no visible console window
- Persists across reboot using a scheduled task that runs at startup as `SYSTEM`
- Continues running even when a standard user logs in later
- Stores the installed runtime in `C:\ProgramData\SentinelPersistentAgent`

## Files

- `agent.py`: source code for the Windows agent
- `build_agent.bat`: builds the executable with PyInstaller
- `install_agent.bat`: one-time installation script for client PCs
- `uninstall_agent.bat`: removes the scheduled task and installed runtime
- `.env.example`: configuration template
- `package\dist\SentinelPersistentAgent.exe`: compiled executable after build

## Build

1. Copy `.env.example` to `.env` if you want a local config for testing.
2. Run `build_agent.bat`.

## Install On Client PC

1. Copy this whole folder to the client PC.
2. Copy `.env.example` to `.env`.
3. Update `.env` with your real API settings.
4. Do not leave sample values like `your-sentinel-server.com` or `your-secure-api-key-change-this`.
4. Run `install_agent.bat` as Administrator.

After installation:

- the agent starts immediately in the background
- the agent also starts automatically on every system boot
- it runs under `SYSTEM`, so students using standard accounts should not be able to stop it easily

## Step-By-Step Install Checklist

1. Put the folder on the client PC in a normal local path such as `C:\SentinelPersistentAgentSetup`.
2. Open [`.env`](</e:/xampp/htdocs/sentinel-tests/persistent-monitoring-agent/.env>) and set:
   `SENTINEL_API_BASE`
   `SENTINEL_API_KEY`
   `SENTINEL_ADMIN_KEY`
   `SENTINEL_BLOCK_REDIRECT_IP`
3. Right-click `install_agent.bat` and choose `Run as administrator`.
4. Wait for the installer window to finish.
5. Verify these items on the client PC:
   `C:\ProgramData\SentinelPersistentAgent\SentinelPersistentAgent.exe` exists
   Task Scheduler contains `SentinelPersistentAgent`
   `C:\ProgramData\SentinelPersistentAgent\persistent_agent_debug.log` is being updated

## If Installation Fails

- Check that the installer is truly elevated. `Run as administrator` is required.
- Check that `.env` contains real values, not the sample placeholder values.
- Check that you are not running the installer from inside a zip file, network share, or protected temporary location.
- Check Windows Task Scheduler permissions and whether security software is blocking task creation or copy into `C:\ProgramData`.
- If `C:\ProgramData\SentinelPersistentAgent` exists, open `persistent_agent_debug.log` and review the last lines.

## Implementation Notes

- Persistence is implemented with Windows Task Scheduler using highest privileges and the `SYSTEM` account.
- The executable is built with `--noconsole` so the monitoring process does not open a console window.
- A single-instance lock prevents duplicate agent processes if install-time start and startup-task start overlap.
