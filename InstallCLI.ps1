# Dashy CLI installer — Windows (x64 and ARM64)
#
#   irm https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.ps1 | iex
#   powershell -ExecutionPolicy Bypass -File .\InstallCLI.ps1
#
# Installs the complete CLI (all modules, not just the entry file), a private
# Node runtime when needed, and the `dashy` command in your user PATH.
# No administrator rights are required.
#
# Environment overrides:
#   DASHY_INSTALL_DIR   install location (default: %LOCALAPPDATA%\Dashy)
#   DASHY_NODE_VERSION  Node version to download (default: v22.11.0)
#   DASHY_BRANCH        repository branch (default: main)
#   DASHY_RAW_URL       raw CLI base URL (advanced)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

try { [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new() } catch { }
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch { }

$NodeVersion = if ($env:DASHY_NODE_VERSION) { $env:DASHY_NODE_VERSION } else { 'v22.11.0' }
$Branch = if ($env:DASHY_BRANCH) { $env:DASHY_BRANCH } else { 'main' }
$TempRoot = if ($env:TEMP) { $env:TEMP } else { [IO.Path]::GetTempPath() }

$InstallDir = if ($env:DASHY_INSTALL_DIR) {
    $env:DASHY_INSTALL_DIR
} elseif ($env:LOCALAPPDATA) {
    Join-Path $env:LOCALAPPDATA 'Dashy'
} else {
    Join-Path $HOME 'Dashy'
}

$BinDir = Join-Path $InstallDir 'bin'
$CliDir = Join-Path $InstallDir 'cli'
$RawBase = if ($env:DASHY_RAW_URL) {
    $env:DASHY_RAW_URL.TrimEnd('/')
} else {
    "https://raw.githubusercontent.com/limoonsdev/dashy/$Branch/cli"
}
$FallbackUrl = "$RawBase/dashy.js"

# $PSScriptRoot is empty when the script is piped into iex. Repositories are
# only checked when a real script path exists.
$ScriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { $null }

function Write-Logo {
    Write-Host ''
    Write-Host '   ██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗' -ForegroundColor Magenta
    Write-Host '   ██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝' -ForegroundColor Magenta
    Write-Host '   ██║  ██║███████║███████╗███████║ ╚████╔╝ ' -ForegroundColor Cyan
    Write-Host '   ██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ' -ForegroundColor Cyan
    Write-Host '   ██████╔╝██║  ██║███████║██║  ██║   ██║   ' -ForegroundColor DarkGray
    Write-Host '   ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ' -ForegroundColor DarkGray
    Write-Host ''
    Write-Host '   CLI installer · Windows · x64 & ARM64' -ForegroundColor DarkGray
    Write-Host ''
}

function Write-Step([string] $Message) {
    Write-Host '  ' -NoNewline
    Write-Host '▸' -NoNewline -ForegroundColor Cyan
    Write-Host " $Message"
}

function Write-Ok([string] $Message) {
    Write-Host '  ' -NoNewline
    Write-Host '✔' -NoNewline -ForegroundColor Green
    Write-Host " $Message"
}

function Write-Warn([string] $Message) {
    Write-Host '  ' -NoNewline
    Write-Host '!' -NoNewline -ForegroundColor Yellow
    Write-Host " $Message"
}

function Write-Bad([string] $Message) {
    Write-Host '  ' -NoNewline
    Write-Host '✖' -NoNewline -ForegroundColor Red
    Write-Host " $Message"
}

function Fail([string] $Message) {
    throw [System.Exception]::new($Message)
}

function Get-RemoteFile([string] $Uri, [string] $Path) {
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            Invoke-WebRequest -Uri $Uri -OutFile $Path -UseBasicParsing `
                -Headers @{ 'User-Agent' = 'Dashy-CLI-Installer' }
            return
        } catch {
            if ($attempt -eq 3) {
                throw [System.Exception]::new("Could not download $Uri. $($_.Exception.Message)")
            }
            Start-Sleep -Seconds (2 * $attempt)
        }
    }
}

function Get-RemoteText([string] $Uri) {
    $response = Invoke-WebRequest -Uri $Uri -UseBasicParsing `
        -Headers @{ 'User-Agent' = 'Dashy-CLI-Installer' }
    return [string] $response.Content
}

function Get-NodeMajor([string] $Exe) {
    if (-not $Exe) { return 0 }
    try {
        $value = & $Exe -p "process.versions.node.split('.')[0]" 2>$null
        return [int] $value
    } catch {
        return 0
    }
}

function Install-Node([string] $Arch) {
    $nodeDir = Join-Path $InstallDir 'node'
    $nodeExe = Join-Path $nodeDir 'node.exe'
    $archive = "node-$NodeVersion-win-$Arch.zip"
    $url = "https://nodejs.org/dist/$NodeVersion/$archive"
    $token = [guid]::NewGuid().ToString('N')
    $zip = Join-Path $TempRoot "dashy-node-$token.zip"
    $extract = Join-Path $TempRoot "dashy-node-$token"

    try {
        Write-Step "Downloading a private Node runtime ($NodeVersion win-$Arch)"
        Get-RemoteFile $url $zip

        Write-Step 'Extracting Node'
        New-Item -ItemType Directory -Force -Path $extract | Out-Null
        Expand-Archive -Path $zip -DestinationPath $extract -Force
        $inner = Get-ChildItem $extract -Directory | Select-Object -First 1

        if (-not $inner) {
            Fail 'The Node archive was empty.'
        }

        if (Test-Path $nodeDir) {
            Remove-Item $nodeDir -Recurse -Force
        }

        Move-Item $inner.FullName $nodeDir
    } finally {
        Remove-Item $zip -Force -ErrorAction SilentlyContinue
        Remove-Item $extract -Recurse -Force -ErrorAction SilentlyContinue
    }

    if (-not (Test-Path $nodeExe)) {
        Fail "Node was downloaded but $nodeExe is missing."
    }

    Write-Ok "Node installed in $nodeDir"
    return $nodeExe
}

function Install-Cli {
    $distDir = Join-Path $CliDir 'dist'
    $entry = Join-Path $distDir 'cli.js'
    $localDist = $null

    if ($ScriptRoot) {
        $candidate = Join-Path $ScriptRoot 'cli\dist'
        if (Test-Path (Join-Path $candidate 'cli.js')) {
            $localDist = $candidate
        }
    }

    if ($localDist) {
        if (Test-Path $distDir) {
            Remove-Item $distDir -Recurse -Force
        }
        New-Item -ItemType Directory -Force -Path $distDir | Out-Null
        Copy-Item (Join-Path $localDist '*') $distDir -Recurse -Force
        Write-Ok "CLI copied from $localDist"
        return $entry
    }

    Write-Step 'Downloading the CLI modules'
    $manifest = Get-RemoteText "$RawBase/dist/manifest.txt"
    $files = @($manifest -split "\r?\n" |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -and -not $_.StartsWith('#') })

    if ($files.Count -eq 0) {
        Fail 'The CLI manifest is empty.'
    }

    if (Test-Path $distDir) {
        Remove-Item $distDir -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $distDir | Out-Null

    foreach ($file in $files) {
        if ($file -match '(^[/\\])|(^|[/\\])\.\.([/\\]|$)') {
            Fail "Unsafe path in the CLI manifest: $file"
        }

        $target = Join-Path $distDir ($file -replace '/', '\')
        $parent = Split-Path $target -Parent

        if ($parent -and -not (Test-Path $parent)) {
            New-Item -ItemType Directory -Force -Path $parent | Out-Null
        }

        Get-RemoteFile "$RawBase/dist/$file" $target
    }

    if (-not (Test-Path $entry)) {
        Fail 'The CLI entry point is missing after download.'
    }

    Write-Ok "CLI installed in $distDir"
    return $entry
}

function Install-Dashy {
    Write-Logo

    $arch = if ($env:PROCESSOR_ARCHITECTURE -eq 'ARM64' -or $env:PROCESSOR_ARCHITEW6432 -eq 'ARM64') {
        'arm64'
    } else {
        'x64'
    }

    if ($env:PROCESSOR_ARCHITECTURE -eq 'x86' -and -not $env:PROCESSOR_ARCHITEW6432) {
        Fail 'Dashy CLI requires 64-bit Windows (x64 or ARM64).'
    }

    New-Item -ItemType Directory -Force -Path $InstallDir, $BinDir, $CliDir | Out-Null

    $nodeExe = $null
    $existing = Get-Command node -ErrorAction SilentlyContinue

    if ($existing) {
        $major = Get-NodeMajor $existing.Path
        if ($major -ge 18) {
            $nodeExe = $existing.Path
            Write-Ok "Using Node $(& $nodeExe -v) from $nodeExe"
        } else {
            Write-Warn "Node $major is older than 18; installing a private runtime."
        }
    }

    $privateNode = Join-Path (Join-Path $InstallDir 'node') 'node.exe'
    if (-not $nodeExe -and (Test-Path $privateNode) -and (Get-NodeMajor $privateNode) -ge 18) {
        $nodeExe = $privateNode
        Write-Ok "Using the private Node in $(Join-Path $InstallDir 'node')"
    }

    if (-not $nodeExe) {
        $nodeExe = Install-Node $arch
    }

    $entry = $null
    try {
        $entry = Install-Cli
    } catch {
        Write-Warn "The full CLI is unavailable: $($_.Exception.Message)"
        $distDir = Join-Path $CliDir 'dist'
        if (Test-Path $distDir) {
            Remove-Item $distDir -Recurse -Force
        }

        $entry = Join-Path $CliDir 'dashy.js'
        $localPortable = if ($ScriptRoot) { Join-Path $ScriptRoot 'cli\dashy.js' } else { $null }

        if ($localPortable -and (Test-Path $localPortable)) {
            Copy-Item $localPortable $entry -Force
            Write-Ok 'Portable CLI copied from the local checkout'
        } else {
            Write-Step 'Downloading the portable CLI'
            Get-RemoteFile $FallbackUrl $entry
            Write-Ok 'Portable CLI installed'
        }
    }

    $wrapper = Join-Path $BinDir 'dashy.cmd'
    @"
@echo off
setlocal
"$nodeExe" "$entry" %*
"@ | Set-Content -Path $wrapper -Encoding Default

    Write-Ok "Command installed at $wrapper"

    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    $pathParts = @()
    if ($userPath) {
        $pathParts = $userPath.Split(';') | Where-Object { $_ }
    }

    if ($pathParts -contains $BinDir) {
        Write-Ok "$BinDir is already on PATH"
    } else {
        $newPath = (@($pathParts) + $BinDir) -join ';'
        [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Ok "Added $BinDir to your user PATH"
    }

    $env:Path = "$env:Path;$BinDir"

    try {
        $version = (& $nodeExe $entry --version 2>$null | Select-Object -First 1)
        if ($version) {
            Write-Ok "Installed: $version"
        }
    } catch {
        Write-Warn 'The command was installed, but it did not report its version yet.'
    }

    Write-Host ''
    Write-Host '  Installed.' -ForegroundColor Green
    Write-Host ''
    Write-Host '  Next steps' -ForegroundColor DarkGray
    Write-Host '  1. In Dashy: Settings → CLI → Generate a sign-in code' -ForegroundColor Gray
    Write-Host '  2. Run: dashy connect DASHY-XXXX-XXXX' -ForegroundColor Gray
    Write-Host '  3. Run: dashy' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Open a new terminal if `dashy` is not found yet.' -ForegroundColor DarkGray
    Write-Host '  Docs: https://github.com/limoonsdev/dashy/tree/main/cli' -ForegroundColor DarkGray
    Write-Host ''
}

try {
    Install-Dashy
} catch {
    Write-Host ''
    Write-Bad $_.Exception.Message
    Write-Host ''
    Write-Host '  Nothing else was changed. Fix the issue and run the command again.' -ForegroundColor DarkGray
    Write-Host ''
}
