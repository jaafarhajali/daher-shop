# ============================================================================
# Daher Phone - compile DaherPhoneSetup.exe with Inno Setup 6.
# Downloads and silently installs Inno Setup if it is not present.
# Run AFTER deploy\build.ps1 (needs the staged package).
# ============================================================================
$ErrorActionPreference = 'Stop'
$version = (Get-Content (Join-Path (Split-Path -Parent $PSScriptRoot) 'VERSION') -Raw).Trim()

# --- locate or obtain ISCC -----------------------------------------------------
$candidates = @(
    "$env:ProgramFiles(x86)\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
)
$iscc = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $iscc) {
    Write-Output 'Inno Setup not found - locating the current release ...'
    $page = (Invoke-WebRequest 'https://jrsoftware.org/isdl.php' -UseBasicParsing -TimeoutSec 60).Content
    $url = ([regex]::Matches($page, 'https?://[^\s"''<>]+innosetup-[\d.]+\.exe') |
            ForEach-Object { $_.Value } | Select-Object -First 1)
    if (-not $url) { throw 'Could not find the Inno Setup download link - install it manually.' }
    Write-Output "Downloading $url ..."
    $installer = Join-Path $env:TEMP 'innosetup-installer.exe'
    Invoke-WebRequest $url -OutFile $installer -UseBasicParsing -TimeoutSec 300
    $mz = [System.IO.File]::ReadAllBytes($installer)[0..1]
    if ($mz[0] -ne 77 -or $mz[1] -ne 90) { throw 'Downloaded file is not a valid installer.' }
    Write-Output 'Installing Inno Setup silently ...'
    Start-Process $installer -ArgumentList '/VERYSILENT /SUPPRESSMSGBOXES /NORESTART' -Wait
    $iscc = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $iscc) { throw 'Inno Setup installation failed - install it manually and re-run.' }
}
Write-Output "Using: $iscc"

# --- compile ----------------------------------------------------------------------
$iss = Join-Path $PSScriptRoot 'installer.iss'
& $iscc "/DMyAppVersion=$version" $iss
if ($LASTEXITCODE -ne 0) { throw 'Installer compilation failed.' }

$setup = Join-Path $PSScriptRoot 'build\DaherPhoneSetup.exe'
$size = [math]::Round((Get-Item $setup).Length / 1MB, 1)
Write-Output "Installer ready: $setup ($size MB)"
