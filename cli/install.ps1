# Dashy CLI installer — Windows (x64 and ARM64)
#
#   irm https://raw.githubusercontent.com/limoonsdev/dashy/main/cli/install.ps1 | iex
#
# Installs a private Node runtime when the machine has none, then adds the
# `dashy` command to your user PATH. No administrator rights are required.

$ErrorActionPreference = 'Stop'

$NodeVersion = if ($env:DASHY_NODE_VERSION) { $env:DASHY_NODE_VERSION } else { 'v22.11.0' }
$InstallDir = if ($env:DASHY_INSTALL_DIR) { $env:DASHY_INSTALL_DIR } else { Join-Path $env:LOCALAPPDATA 'Dashy' }
$BinDir = Join-Path $InstallDir 'bin'
$RawUrl = if ($env:DASHY_RAW_URL) { $env:DASHY_RAW_URL } else { 'https://raw.githubusercontent.com/limoonsdev/dashy/main/cli' }

function Write-Logo {
    Write-Host ''
    Write-Host '    ██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗' -ForegroundColor Magenta
    Write-Host '    ██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝' -ForegroundColor Magenta
    Write-Host '    ██║  ██║███████║███████╗███████║ ╚████╔╝ ' -ForegroundColor Cyan
    Write-Host '    ██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ' -ForegroundColor Cyan
    Write-Host '    ██████╔╝██║  ██║███████║██║  ██║   ██║   ' -ForegroundColor DarkGray
    Write-Host '    ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ' -ForegroundColor DarkGray
    Write-Host ''
    Write-Host '  CLI installer · Windows · x64 & ARM64' -ForegroundColor DarkGray
    Write-Host ''
}

function Write-Step([string] $Message) { Write-Host "  > $Message" -ForegroundColor Cyan }
function Write-Ok([string] $Message) { Write-Host "  + $Message" -ForegroundColor Green }
function Write-Warn([string] $Message) { Write-Host "  ! $Message" -ForegroundColor Yellow }
function Fail([string] $Message) { Write-Host "  x $Message" -ForegroundColor Red; exit 1 }

Write-Logo

$Arch = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64') { 'arm64' } else { 'x64' }

New-Item -ItemType Directory -Force -Path $InstallDir, $BinDir | Out-Null

# ── Node ────────────────────────────────────────────────────────────────────────
$NodeExe = $null
$Existing = Get-Command node -ErrorAction SilentlyContinue

if ($Existing) {
    $Major = [int](& node -p "process.versions.node.split('.')[0]")
    if ($Major -ge 18) {
        $NodeExe = $Existing.Source
        Write-Ok "Using Node $(& node -v) from $NodeExe"
    }
}

if (-not $NodeExe) {
    $NodeDir = Join-Path $InstallDir 'node'
    $Cached = Join-Path $NodeDir 'node.exe'

    if (Test-Path $Cached) {
        $NodeExe = $Cached
        Write-Ok "Using the private Node in $NodeDir"
    }
    else {
        $Archive = "node-$NodeVersion-win-$Arch.zip"
        $Url = "https://nodejs.org/dist/$NodeVersion/$Archive"
        $Zip = Join-Path $InstallDir $Archive

        Write-Step "Downloading a private Node runtime ($NodeVersion win-$Arch)"
        try {
            Invoke-WebRequest -Uri $Url -OutFile $Zip -UseBasicParsing
        }
        catch {
            Fail 'Could not download Node from nodejs.org. Install Node 18+ yourself and run this again.'
        }

        Write-Step 'Extracting'
        $Extract = Join-Path $InstallDir 'node-extract'
        if (Test-Path $Extract) { Remove-Item $Extract -Recurse -Force }
        Expand-Archive -Path $Zip -DestinationPath $Extract -Force
        $Inner = Get-ChildItem $Extract -Directory | Select-Object -First 1
        if (-not $Inner) { Fail 'The Node archive was empty.' }
        if (Test-Path $NodeDir) { Remove-Item $NodeDir -Recurse -Force }
        Move-Item $Inner.FullName $NodeDir
        Remove-Item $Zip -Force
        Remove-Item $Extract -Recurse -Force

        $NodeExe = Join-Path $NodeDir 'node.exe'
        Write-Ok "Node installed in $NodeDir"
    }
}

# ── CLI ────────────────────────────────────────────────────────────────────────
$CliPath = Join-Path $BinDir 'dashy.mjs'
$LocalBuild = Join-Path $PSScriptRoot 'dist/cli.js'
$LocalPortable = Join-Path $PSScriptRoot 'dashy.js'

if (Test-Path $LocalBuild) {
    Copy-Item $LocalBuild $CliPath -Force
    Write-Ok "CLI copied from $LocalBuild"
}
elseif (Test-Path $LocalPortable) {
    $CliPath = Join-Path $BinDir 'dashy.cjs'
    Copy-Item $LocalPortable $CliPath -Force
    Write-Ok "Portable CLI copied from $LocalPortable"
}
else {
    Write-Step 'Downloading the CLI'
    try {
        Invoke-WebRequest -Uri "$RawUrl/dist/cli.js" -OutFile $CliPath -UseBasicParsing
    }
    catch {
        Write-Warn 'The full-screen build is unavailable, using the portable CLI'
        $CliPath = Join-Path $BinDir 'dashy.cjs'
        try {
            Invoke-WebRequest -Uri "$RawUrl/dashy.js" -OutFile $CliPath -UseBasicParsing
        }
        catch {
            Fail "Could not download the CLI from $RawUrl/dashy.js"
        }
    }
    Write-Ok 'CLI downloaded'
}

$Wrapper = Join-Path $BinDir 'dashy.cmd'
@"
@echo off
"$NodeExe" "$CliPath" %*
"@ | Set-Content -Path $Wrapper -Encoding ASCII
Write-Ok "Command installed at $Wrapper"

# ── PATH ───────────────────────────────────────────────────────────────────────
$UserPath = [Environment]::GetEnvironmentVariable('Path', 'User')
$PathParts = @()

if ($UserPath) { $PathParts = $UserPath.Split(';') | Where-Object { $_ } }

if ($PathParts -contains $BinDir) {
    Write-Ok "$BinDir is already on PATH"
}
else {
    $NewPath = (@($PathParts) + $BinDir) -join ';'
    [Environment]::SetEnvironmentVariable('Path', $NewPath, 'User')
    $env:Path = "$env:Path;$BinDir"
    Write-Ok "Added $BinDir to your user PATH (open a new terminal)"
}

Write-Host ''
Write-Host '  Installed.' -ForegroundColor Green
Write-Host '  1. In Dashy: Settings -> CLI -> Generate a sign-in code' -ForegroundColor DarkGray
Write-Host '  2. Run: dashy connect DASHY-XXXX-XXXX' -ForegroundColor DarkGray
Write-Host '  3. Run: dashy' -ForegroundColor DarkGray
Write-Host ''
Write-Host '  Docs: https://github.com/limoonsdev/dashy/tree/main/cli' -ForegroundColor DarkGray
Write-Host ''
