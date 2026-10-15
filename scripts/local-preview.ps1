$ErrorActionPreference = 'Stop'

$RunTimer = [System.Diagnostics.Stopwatch]::StartNew()

Set-Location 'P:\moeller-lars'

$Changes = git status --porcelain
if ($LASTEXITCODE) { throw 'Git-Status fehlgeschlagen.' }
if ($Changes) { throw 'Lokale Git-Aenderungen vorhanden. Erst committen, stashen oder verwerfen.' }

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

$env:APP_GIT_SHA = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE) { throw 'Git SHA konnte nicht gelesen werden.' }

$AnalyticsDemo = 'storage\app\analytics-demo.php'
if (-not (Test-Path $AnalyticsDemo)) { throw "Lokale Analytics-Demo fehlt: $AnalyticsDemo" }

# local-dev and local-preview intentionally share 127.0.0.1:8001.
docker compose --profile dev stop dev-app dev-vite | Out-Null
if ($LASTEXITCODE) { throw 'Lokaler Dev-Modus konnte vor dem Preview-Build nicht gestoppt werden.' }
Remove-Item 'public\hot' -Force -ErrorAction SilentlyContinue

Write-Host "[2/3] Preview-Image bauen ($($env:APP_GIT_SHA.Substring(0, 7)))"
docker compose build preview
if ($LASTEXITCODE) { throw 'Lokaler Preview-Build fehlgeschlagen. Alter Preview-Container bleibt unangetastet.' }

docker volume create moeller-lars_postgres_data | Out-Null
if ($LASTEXITCODE) { throw 'Lokales PostgreSQL-Volume konnte nicht bereitgestellt werden.' }

Write-Host '[3/3] Preview starten und Analytics laden'
docker compose up -d --no-build --wait preview
if ($LASTEXITCODE) { throw 'Lokaler Preview-Container konnte nicht gestartet werden.' }

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
