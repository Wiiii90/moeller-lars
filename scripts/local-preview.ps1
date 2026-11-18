$ErrorActionPreference = 'Stop'

$RunTimer = [System.Diagnostics.Stopwatch]::StartNew()

Set-Location 'P:\moeller-lars'

$Changes = git status --porcelain
if ($LASTEXITCODE) { throw 'Git-Status fehlgeschlagen.' }
if ($Changes) { throw 'Lokale Git-Aenderungen vorhanden. Erst committen, stashen oder verwerfen.' }

# The script is parsed before execution. If Git updates this script itself,
# restart from the new file rather than continuing the stale in-memory code.
$OriginalScriptHash = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash

git switch dev
if ($LASTEXITCODE) { throw 'git switch fehlgeschlagen.' }

Write-Host '[1/3] Git dev aktualisieren'
foreach ($Attempt in 1..4) {
    git fetch origin dev
    if ($LASTEXITCODE -eq 0) { break }
    if ($Attempt -eq 4) { throw 'Git-Fetch nach vier Versuchen fehlgeschlagen.' }
    Write-Warning "Git-Fetch fehlgeschlagen ($Attempt/4), erneuter Versuch in 2 Sekunden."
    Start-Sleep -Seconds 2
}
git merge --ff-only FETCH_HEAD
if ($LASTEXITCODE) { throw 'Git-Fast-Forward fehlgeschlagen.' }

$UpdatedScriptHash = (Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash
if ($UpdatedScriptHash -ne $OriginalScriptHash) {
    Write-Host 'Preview script was updated; restarting the current version.'
    & $PSCommandPath
    return
}

$env:APP_GIT_SHA = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE) { throw 'Git SHA konnte nicht gelesen werden.' }

$AnalyticsDemo = 'storage\app\analytics-demo.php'
if (-not (Test-Path $AnalyticsDemo)) { throw "Lokale Analytics-Demo fehlt: $AnalyticsDemo" }

# Never let a local Vite dev-server marker leak into the frozen preview image.
Remove-Item 'public\hot' -Force -ErrorAction SilentlyContinue

Write-Host "[2/3] Building application preview ($($env:APP_GIT_SHA.Substring(0, 7)))"
docker compose build preview
if ($LASTEXITCODE) { throw 'Application preview build failed. Existing preview container remains untouched.' }

docker volume create moeller-lars_postgres_data | Out-Null
if ($LASTEXITCODE) { throw 'Lokales PostgreSQL-Volume konnte nicht bereitgestellt werden.' }

Write-Host '[3/3] Starting application preview and media-rights worker'
docker compose up -d --no-build --wait preview
if ($LASTEXITCODE) { throw 'Application preview failed to start.' }

docker compose up -d --no-build media-rights-worker
if ($LASTEXITCODE) { throw 'Lokaler Bildrechte-Worker konnte nicht gestartet werden.' }

$WorkerStatus = docker inspect --format '{{.State.Status}}' moeller-lars-local-rights-worker
if ($LASTEXITCODE -ne 0 -or $WorkerStatus -ne 'running') {
    throw 'Lokaler Bildrechte-Worker laeuft nicht. Docker-Logs pruefen.'
}

docker cp $AnalyticsDemo moeller-lars-local-web:/var/www/html/storage/app/analytics-demo.php
if ($LASTEXITCODE) { throw 'Lokale Analytics-Demo konnte nicht in den Preview-Container kopiert werden.' }

docker exec moeller-lars-local-web php artisan tinker --execute="require storage_path('app/analytics-demo.php');"
if ($LASTEXITCODE) { throw 'Lokale Analytics-Demo konnte nicht geladen werden.' }

Write-Host ''
Write-Host "Git:     $($env:APP_GIT_SHA.Substring(0, 7))"
Write-Host 'Preview: http://127.0.0.1:8001'

$RunTimer.Stop()
$RunFinishedAt = Get-Date
$RunDuration = '{0:00}:{1:00}:{2:00}' -f [math]::Floor($RunTimer.Elapsed.TotalHours), $RunTimer.Elapsed.Minutes, $RunTimer.Elapsed.Seconds

Write-Host "Dauer:   $RunDuration"
Write-Host "Fertig:  $($RunFinishedAt.ToString('yyyy-MM-dd HH:mm:ss'))"
