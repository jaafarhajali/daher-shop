# ============================================================================
# Daher Phone - package end-to-end test.
# Exercises the SHIPPED package exactly like the launcher does, headless:
#   fresh datadir -> mysqld -> install.php (fresh + idempotent) -> web server
#   -> login -> updater (good package, then broken package with rollback)
#   -> backup -> shutdown -> reset package to pristine state.
# Uses test ports 3317/8125 so nothing collides with dev services.
# ============================================================================
$ErrorActionPreference = 'Stop'
$stage = Join-Path $PSScriptRoot 'build\Daher Phone'
$app = Join-Path $stage 'Application'
$phpExe = Join-Path $stage 'Server\PHP\php.exe'
$extDir = Join-Path $stage 'Server\PHP\ext'
$dbBin = Join-Path $stage 'Server\MariaDB\bin'
$dataDir = Join-Path $stage 'Database\data'
$dbPort = 3317
$webPort = 8125
$base = "http://127.0.0.1:$webPort/index.php"
$pass = 0; $fail = 0
$mysqld = $null; $phpSrv = $null

function Check([string]$name, [bool]$cond) {
    if ($cond) { $script:pass++; Write-Output "PASS  $name" }
    else { $script:fail++; Write-Output "FAIL  $name" }
}
function PhpCli([string]$args2) {
    # PHP may print warnings to stderr; those must not abort the test run.
    $eap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $phpExe -d "extension_dir=$extDir" $args2.Split(' ') 2>&1 | Out-String }
    finally { $ErrorActionPreference = $eap }
}
function PortOpen([int]$port) {
    try { $c = New-Object Net.Sockets.TcpClient; $c.Connect('127.0.0.1', $port); $c.Close(); $true }
    catch { $false }
}

# Test-time app.ini pointing at the test DB port.
$appIni = Join-Path $app 'config\app.ini'
$iniBackup = Get-Content $appIni -Raw
(Get-Content $appIni -Raw).Replace('port = 3307', "port = $dbPort") | Set-Content $appIni -Encoding ascii

try {
    # --- 1. First run: initialize data directory ------------------------------
    if (Test-Path $dataDir) { Remove-Item $dataDir -Recurse -Force }
    New-Item -ItemType Directory -Force $dataDir | Out-Null
    $p = Start-Process -FilePath (Join-Path $dbBin 'mysql_install_db.exe') `
        -ArgumentList "--datadir=`"$dataDir`"" -WorkingDirectory $stage -PassThru -WindowStyle Hidden -Wait
    Check 'datadir initialized' ((Test-Path (Join-Path $dataDir 'mysql')) -and $p.ExitCode -eq 0)

    # --- 2. Start bundled MariaDB ----------------------------------------------
    $mysqld = Start-Process -FilePath (Join-Path $dbBin 'mysqld.exe') `
        -ArgumentList "--no-defaults --console --port=$dbPort --bind-address=127.0.0.1 --datadir=`"$dataDir`" --innodb_buffer_pool_size=64M" `
        -PassThru -WindowStyle Hidden
    $up = $false
    for ($i = 0; $i -lt 60; $i++) { if (PortOpen $dbPort) { $up = $true; break }; Start-Sleep -Milliseconds 500 }
    Check 'bundled MariaDB started' $up

    # --- 3. install.php: fresh, then idempotent ---------------------------------
    Push-Location $app
    $out1 = PhpCli 'bin\install.php'
    Check 'fresh install imports schema' ($out1 -match 'Fresh installation' -and $out1 -match 'Database OK')
    $out2 = PhpCli 'bin\install.php'
    Check 'second run touches nothing' ($out2 -match 'up to date')
    Pop-Location

    # --- 4. Start bundled web server ----------------------------------------------
    $phpSrv = Start-Process -FilePath $phpExe `
        -ArgumentList "-d extension_dir=`"$extDir`" -S 127.0.0.1:$webPort -t `"$app\public`"" `
        -WorkingDirectory $app -PassThru -WindowStyle Hidden
    $up = $false
    for ($i = 0; $i -lt 30; $i++) { if (PortOpen $webPort) { $up = $true; break }; Start-Sleep -Milliseconds 500 }
    Check 'bundled web server started' $up

    # --- 5. Login + pages through the package ---------------------------------------
    $r = Invoke-WebRequest "$base`?r=auth/login" -SessionVariable S -UseBasicParsing
    Check 'login page shows Daher Phone' ($r.Content -match 'Daher Phone')
    $token = [regex]::Match($r.Content, 'name="_token" value="([0-9a-f]+)"').Groups[1].Value
    $r = Invoke-WebRequest "$base`?r=auth/attempt" -WebSession $S -Method Post -UseBasicParsing `
         -Body @{ _token = $token; username = 'admin'; password = 'admin123' }
    Check 'seeded admin can sign in' ($r.Content -match 'Dashboard')
    $r = Invoke-WebRequest "$base`?r=updates/index" -WebSession $S -UseBasicParsing
    Check 'updates page shows v1.3.0' ($r.Content -match 'v1\.3\.0')

    # --- 6. GOOD update package: 1.3.0 -> 1.3.1 --------------------------------------
    $updTest = Join-Path $app 'bin\_updtest.php'
    @'
<?php
require __DIR__ . '/bootstrap.php';
try { $v = (new App\Core\Updater())->apply($argv[1]); echo "APPLIED {$v}\n"; exit(0); }
catch (Throwable $e) { echo 'FAILED-SAFE: ' . $e->getMessage() . "\n"; exit(3); }
'@ | Set-Content $updTest -Encoding ascii

    $tmp = Join-Path $env:TEMP 'dp-upd-good'
    if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
    robocopy $app $tmp /MIR /XD "$app\storage" /XF "$app\config\app.ini" /NFL /NDL /NJH /NJS /NP | Out-Null
    Set-Content (Join-Path $tmp 'VERSION') '1.3.1' -Encoding ascii
    $goodZip = Join-Path $env:TEMP 'dp-good.zip'
    if (Test-Path $goodZip) { Remove-Item $goodZip -Force }
    Compress-Archive "$tmp\*" $goodZip

    Push-Location $app
    $out = PhpCli "bin\_updtest.php $goodZip"
    Pop-Location
    Check 'good update applied (1.3.1)' ($out -match 'APPLIED 1\.3\.1')
    Check 'VERSION file bumped' ((Get-Content (Join-Path $app 'VERSION') -Raw).Trim() -eq '1.3.1')
    Check 'pre-update DB backup exists' ((Get-ChildItem (Join-Path $stage 'Backups') -Filter 'backup_*.sql').Count -ge 1)

    # --- 7. BROKEN update package: bad migration -> automatic rollback ---------------
    Set-Content (Join-Path $tmp 'VERSION') '1.3.2' -Encoding ascii
    Set-Content (Join-Path $tmp 'database\migrations\999_bad_migration.sql') 'THIS IS NOT VALID SQL;' -Encoding ascii
    $badZip = Join-Path $env:TEMP 'dp-bad.zip'
    if (Test-Path $badZip) { Remove-Item $badZip -Force }
    Compress-Archive "$tmp\*" $badZip

    Push-Location $app
    $out = PhpCli "bin\_updtest.php $badZip"
    Pop-Location
    Check 'broken update fails safely' ($out -match 'FAILED-SAFE' -and $out -match 'previous version was restored')
    Check 'VERSION rolled back to 1.3.1' ((Get-Content (Join-Path $app 'VERSION') -Raw).Trim() -eq '1.3.1')
    Check 'bad migration file removed by rollback' (-not (Test-Path (Join-Path $app 'database\migrations\999_bad_migration.sql')))
    $r = Invoke-WebRequest "$base`?r=auth/login" -UseBasicParsing
    Check 'app still serves after rollback' ($r.StatusCode -eq 200)

    # --- 8. Backup CLI writes to the package Backups folder ---------------------------
    Push-Location $app
    $out = PhpCli 'bin\backup.php'
    Pop-Location
    Check 'backup lands in package Backups folder' ($out -match 'Backup created' -and
        (Get-ChildItem (Join-Path $stage 'Backups') -Filter 'backup_*.sql').Count -ge 2)
}
finally {
    # --- Shutdown + reset the package to pristine state -------------------------------
    try { if ($phpSrv -and -not $phpSrv.HasExited) { Stop-Process -Id $phpSrv.Id -Force } } catch {}
    try {
        & (Join-Path $dbBin 'mysqladmin.exe') --port=$dbPort --host=127.0.0.1 -u root shutdown 2>&1 | Out-Null
        if ($mysqld) { $mysqld.WaitForExit(20000) | Out-Null }
    } catch {}
    try { if ($mysqld -and -not $mysqld.HasExited) { Stop-Process -Id $mysqld.Id -Force } } catch {}

    Set-Content $appIni $iniBackup -Encoding ascii
    foreach ($reset in @("$stage\Database\data", "$stage\Logs", "$stage\Backups", "$stage\Updates")) {
        if (Test-Path $reset) { Remove-Item $reset -Recurse -Force }
        New-Item -ItemType Directory -Force $reset | Out-Null
    }
    if (Test-Path (Join-Path $app 'bin\_updtest.php')) { Remove-Item (Join-Path $app 'bin\_updtest.php') -Force }
}

Write-Output '-----------------------------------------'
Write-Output "RESULT: $pass passed, $fail failed"
if ($fail -gt 0) { exit 1 }
