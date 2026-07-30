# ============================================================================
# Daher Phone - production package builder.
#
#   powershell -ExecutionPolicy Bypass -File deploy\build.ps1
#       Builds deploy\build\Daher Phone\  (the full private-server package)
#
#   powershell -ExecutionPolicy Bypass -File deploy\build.ps1 -UpdateZip
#       Also produces deploy\build\DaherPhone-update-<version>.zip + update.json
#       (host both files anywhere; paste the update.json URL into the app)
#
# Sources for the bundled server: the local XAMPP installation
# (PHP is licensed under the PHP License, MariaDB under the GPL - both
# redistributable). Override with -PhpSource / -MariaDbSource.
# ============================================================================
param(
    [string]$PhpSource = 'C:\xampp\php',
    [string]$MariaDbSource = 'C:\xampp\mysql',
    [switch]$UpdateZip
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot          # repo root (deploy\..)
$stage = Join-Path $PSScriptRoot 'build\Daher Phone'
$version = (Get-Content (Join-Path $repo 'VERSION') -Raw).Trim()

Write-Output "Building Daher Phone v$version"
Write-Output "  repo : $repo"
Write-Output "  stage: $stage"

# --- 0. Clean stage -----------------------------------------------------------
if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
foreach ($d in @('Server\PHP\ext', 'Server\MariaDB', 'Application', 'Database',
                 'Backups', 'Updates', 'Logs')) {
    New-Item -ItemType Directory -Force (Join-Path $stage $d) | Out-Null
}

# --- 1. PHP (slim copy) ---------------------------------------------------------
Write-Output 'Copying PHP ...'
Copy-Item "$PhpSource\*.exe" (Join-Path $stage 'Server\PHP')
Copy-Item "$PhpSource\*.dll" (Join-Path $stage 'Server\PHP')
foreach ($ext in @('php_pdo_mysql.dll', 'php_mysqli.dll', 'php_mbstring.dll',
                   'php_curl.dll', 'php_openssl.dll', 'php_zip.dll',
                   'php_fileinfo.dll', 'php_intl.dll')) {
    $src = Join-Path "$PhpSource\ext" $ext
    if (Test-Path $src) { Copy-Item $src (Join-Path $stage 'Server\PHP\ext') }
}

# Visual C++ runtime, app-local: clean Windows machines do NOT have
# VCRUNTIME140.dll etc., and XAMPP's php folder does not ship them (it relies
# on a system-wide install). MariaDB's bin ships the full redistributable set -
# copy it next to php.exe so PHP runs everywhere without any system install.
$vcRuntime = @('vcruntime140.dll', 'vcruntime140_1.dll', 'concrt140.dll',
               'msvcp140.dll', 'msvcp140_1.dll', 'msvcp140_2.dll',
               'msvcp140_atomic_wait.dll', 'msvcp140_codecvt_ids.dll')
foreach ($dll in $vcRuntime) {
    $src = Join-Path "$MariaDbSource\bin" $dll
    if (-not (Test-Path $src)) { $src = Join-Path "$env:WINDIR\System32" $dll }
    if (Test-Path $src) { Copy-Item $src (Join-Path $stage 'Server\PHP') -Force }
}
if (-not (Test-Path (Join-Path $stage 'Server\PHP\vcruntime140.dll'))) {
    throw 'VC++ runtime DLLs not found - PHP would fail on clean machines.'
}

@'
; Daher Phone - production PHP configuration.
; extension_dir is passed absolutely by the launcher (-d extension_dir=...).
extension=pdo_mysql
extension=mbstring
extension=curl
extension=openssl
extension=zip
extension=fileinfo

memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
display_errors = Off
log_errors = On
expose_php = Off
'@ | Out-File (Join-Path $stage 'Server\PHP\php.ini') -Encoding ascii

# --- 2. MariaDB (bin + share only; data is created on first run) -----------------
Write-Output 'Copying MariaDB ...'
Copy-Item "$MariaDbSource\bin"   (Join-Path $stage 'Server\MariaDB\bin')   -Recurse
Copy-Item "$MariaDbSource\share" (Join-Path $stage 'Server\MariaDB\share') -Recurse
foreach ($lic in @('COPYING', 'CREDITS', 'README.md')) {
    $src = Join-Path $MariaDbSource $lic
    if (Test-Path $src) { Copy-Item $src (Join-Path $stage 'Server\MariaDB') }
}

# --- 3. Application ---------------------------------------------------------------
Write-Output 'Copying application ...'
$appDest = Join-Path $stage 'Application'
robocopy $repo $appDest /MIR `
    /XD "$repo\.git" "$repo\deploy" "$repo\storage" "$repo\releases" "$repo\node_modules" `
    /XF "$repo\config\app.ini" /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }
New-Item -ItemType Directory -Force (Join-Path $appDest 'storage\logs') | Out-Null

# Production app.ini: bundled DB on port 3307, debug off, package folders.
@'
; Daher Phone - installation configuration. Updates never overwrite this file.
[database]
host = 127.0.0.1
port = 3307
name = daher_store
user = root
pass =

[app]
debug = 0
timezone = Asia/Beirut

[paths]
backups = ..\Backups
updates = ..\Updates
'@ | Out-File (Join-Path $appDest 'config\app.ini') -Encoding ascii

# --- 4. Launcher configuration -------------------------------------------------------
@'
; Daher Phone launcher configuration. Paths are relative to this folder.
[server]
php_port = 8123
db_port = 3307
app_dir = Application
php_dir = Server\PHP
db_dir = Server\MariaDB
data_dir = Database\data
logs_dir = Logs
'@ | Out-File (Join-Path $stage 'server.ini') -Encoding ascii

# --- 5. Compile the launcher -----------------------------------------------------------
Write-Output 'Compiling DaherPhone.exe ...'
$csc = "$env:WINDIR\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if (-not (Test-Path $csc)) { $csc = "$env:WINDIR\Microsoft.NET\Framework\v4.0.30319\csc.exe" }
if (-not (Test-Path $csc)) { throw '.NET Framework compiler (csc.exe) not found.' }

$outExe = Join-Path $stage 'DaherPhone.exe'
$srcCs = Join-Path $PSScriptRoot 'launcher\Launcher.cs'
$icon = Join-Path $PSScriptRoot 'launcher\app.ico'
if (Test-Path $icon) {
    & $csc /nologo /target:winexe /optimize+ "/win32icon:$icon" "/out:$outExe" "$srcCs"
} else {
    & $csc /nologo /target:winexe /optimize+ "/out:$outExe" "$srcCs"
}
if ($LASTEXITCODE -ne 0) { throw 'Launcher compilation failed.' }

# --- 6. Report --------------------------------------------------------------------------
$size = [math]::Round((Get-ChildItem $stage -Recurse -File | Measure-Object Length -Sum).Sum / 1MB, 1)
Write-Output "Package ready: $stage  ($size MB)"

# --- 7. Optional update package ------------------------------------------------------------
if ($UpdateZip) {
    Write-Output 'Building update package ...'
    # The build folder is a workshop, not an archive: drop update zips of
    # OTHER versions so only the current release's artifacts remain.
    Get-ChildItem (Join-Path $PSScriptRoot 'build') -Filter 'DaherPhone-update-*.zip' |
        Where-Object { $_.Name -ne "DaherPhone-update-$version.zip" } |
        Remove-Item -Force
    $updStage = Join-Path $PSScriptRoot 'build\update-stage'
    if (Test-Path $updStage) { Remove-Item $updStage -Recurse -Force }
    robocopy $repo $updStage /MIR `
        /XD "$repo\.git" "$repo\deploy" "$repo\storage" "$repo\releases" "$repo\node_modules" `
        /XF "$repo\config\app.ini" /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed with code $LASTEXITCODE" }

    $zipPath = Join-Path $PSScriptRoot "build\DaherPhone-update-$version.zip"
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
    # .NET ZipFile opens sources with read-sharing (Compress-Archive does not,
    # and loses races against antivirus/IDE indexing on fresh copies).
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($updStage, $zipPath)
    Remove-Item $updStage -Recurse -Force

    $sha = (Get-FileHash $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $json = '{' + "`n" +
        '  "version": "' + $version + '",' + "`n" +
        '  "url": "REPLACE-WITH-PUBLIC-URL/DaherPhone-update-' + $version + '.zip",' + "`n" +
        '  "sha256": "' + $sha + '",' + "`n" +
        '  "notes": "Daher Phone v' + $version + '"' + "`n" + '}'
    $json | Out-File (Join-Path $PSScriptRoot 'build\update.json') -Encoding ascii
    Write-Output "Update package: $zipPath"
    Write-Output "Feed template : deploy\build\update.json (fill in the real URL before hosting)"
}
