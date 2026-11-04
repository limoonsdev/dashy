#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installs Dashy on Windows through WSL2 and Ubuntu.

.DESCRIPTION
    Ensures WSL2 and Ubuntu are present, enables systemd, then runs the Linux
    installer as root inside the distribution. Re-running the script updates
    the source checkout and reruns the installer.

.NOTES
    Windows 10 2004+, Windows 11, or Windows Server 2019+ with WSL2.
    Virtualization must be enabled in the BIOS/UEFI.
#>

$ErrorActionPreference = 'Stop'
$Distro = 'Ubuntu'
$Repo = 'https://github.com/limoonsdev/dashy.git'

Write-Host ''
Write-Host 'Dashy / Windows bootstrap' -ForegroundColor Magenta
Write-Host 'Control plane runs in WSL2 with Linux containers.' -ForegroundColor DarkGray
Write-Host ''

if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    Write-Host 'WSL2 is not available.' -ForegroundColor Red
    Write-Host 'Run "wsl --install" in an elevated terminal, reboot, then run this script again.'
    exit 1
}

$installed = (wsl.exe --list --quiet 2>$null) -join "`n"
if ($installed -notmatch [regex]::Escape($Distro)) {
    Write-Host "Ubuntu is not installed. Installing $Distro..." -ForegroundColor Yellow
    wsl.exe --install -d $Distro
    Write-Host ''
    Write-Host 'Reboot Windows (or run "wsl --shutdown"), then run this script again.' -ForegroundColor Yellow
    exit 0
}

# systemd is required for Docker and the installer's service management.
wsl.exe -d $Distro -u root -- bash -lc "grep -q '^systemd=true' /etc/wsl.conf 2>/dev/null || printf '\n[boot]\nsystemd=true\n' >> /etc/wsl.conf" | Out-Null

$state = (wsl.exe -d $Distro -u root -- bash -lc "systemctl is-system-running 2>/dev/null || true") -join ''
if ($state -notmatch 'running|degraded') {
    Write-Host 'Enabling systemd in WSL2...' -ForegroundColor Yellow
    wsl.exe --shutdown
    Write-Host 'WSL has been restarted. Run this script again to continue.' -ForegroundColor Yellow
    exit 0
}

$bootstrap = @'
set -e
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq git curl ca-certificates
if [ ! -d /opt/dashy/.git ]; then
    git clone --depth=1 https://github.com/limoonsdev/dashy.git /opt/dashy
else
    git -C /opt/dashy fetch --depth=1 origin main
    git -C /opt/dashy reset --hard FETCH_HEAD
fi
cd /opt/dashy
./scripts/install.sh
'@

Write-Host 'Installing Dashy inside WSL2...' -ForegroundColor Magenta
wsl.exe -d $Distro -u root -- bash -lc $bootstrap

if ($LASTEXITCODE -ne 0) {
    Write-Host 'Installation failed. Check the installer log inside WSL.' -ForegroundColor Red
    exit $LASTEXITCODE
}

Write-Host ''
Write-Host 'Dashy is installed.' -ForegroundColor Green
Write-Host 'Open http://localhost:8000 and create the first account within 10 minutes.'
Write-Host 'For LAN access, use this PC address instead of localhost.'
