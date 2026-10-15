$ErrorActionPreference = 'Stop'

$RunTimer = [System.Diagnostics.Stopwatch]::StartNew()

Set-Location 'P:\moeller-lars'

$Changes = git status --porcelain
if ($LASTEXITCODE) { throw 'Git-Status fehlgeschlagen.' }
if ($Changes) { throw 'Lokale Git-Aenderungen vorhanden. Erst committen, stashen oder verwerfen.' }

git switch dev
if ($LASTEXITCODE) { throw 'git switch fehlgeschlagen.' }

Write-Host '[1/4] Git dev aktualisieren'
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

Write-Host '[2/4] Dev-Laufzeit vorbereiten'

docker volume create moeller-lars_postgres_data | Out-Null
if ($LASTEXITCODE) { throw 'Lokales PostgreSQL-Volume konnte nicht bereitgestellt werden.' }

docker compose --profile preview stop preview | Out-Null
if ($LASTEXITCODE) { throw 'Lokaler Preview-Container konnte vor dem Dev-Start nicht gestoppt werden.' }

docker compose --profile dev stop dev-app dev-vite | Out-Null
if ($LASTEXITCODE) { throw 'Vorhandener lokaler Dev-Modus konnte nicht sauber gestoppt werden.' }

Remove-Item 'public\hot' -Force -ErrorAction SilentlyContinue

docker compose --profile dev build dev-app
if ($LASTEXITCODE) { throw 'Lokales Dev-PHP-Image konnte nicht gebaut werden.' }

$ComposerBootstrap = @'
set -eu
lock_hash="$(sha256sum composer.json composer.lock | sha256sum | cut -d ' ' -f1)"
marker='vendor/.moeller-lars-dev-dependencies'
if [ ! -f vendor/autoload.php ] || [ "$(cat "$marker" 2>/dev/null || true)" != "$lock_hash" ]; then
    composer install --no-interaction --no-progress --prefer-dist
    printf '%s\n' "$lock_hash" > "$marker"
fi
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan media:measure-capacity --no-interaction
'@

docker compose --profile dev run --rm dev-app sh -lc $ComposerBootstrap
if ($LASTEXITCODE) { throw 'Laravel-/Composer-Vorbereitung fuer local-dev fehlgeschlagen.' }

$NodeBootstrap = @'
set -eu
lock_hash="$(sha256sum package.json package-lock.json | sha256sum | cut -d ' ' -f1)"
marker='node_modules/.moeller-lars-dev-dependencies'
if [ ! -x node_modules/.bin/vite ] || [ "$(cat "$marker" 2>/dev/null || true)" != "$lock_hash" ]; then
    npm ci --ignore-scripts --no-audit --no-fund
    printf '%s\n' "$lock_hash" > "$marker"
fi
'@

docker compose --profile dev run --rm dev-vite sh -lc $NodeBootstrap
if ($LASTEXITCODE) { throw 'Node-/Vite-Vorbereitung fuer local-dev fehlgeschlagen.' }

Write-Host '[3/4] Laravel + Vite starten'
docker compose --profile dev up -d --no-build --wait dev-app dev-vite
if ($LASTEXITCODE) { throw 'Lokaler Dev-Modus konnte nicht gestartet werden.' }

Write-Host '[4/4] Analytics laden'
docker exec moeller-lars-local-dev-web php artisan tinker --execute="require storage_path('app/analytics-demo.php');"
if ($LASTEXITCODE) { throw 'Lokale Analytics-Demo konnte nicht geladen werden.' }

Write-Host ''
Write-Host "Git:     $($env:APP_GIT_SHA.Substring(0, 7))"
Write-Host 'Dev:     http://127.0.0.1:8001'
Write-Host 'Vite:    HMR aktiv'

$RunTimer.Stop()
$RunFinishedAt = Get-Date
$RunDuration = '{0:00}:{1:00}:{2:00}' -f [math]::Floor($RunTimer.Elapsed.TotalHours), $RunTimer.Elapsed.Minutes, $RunTimer.Elapsed.Seconds

Write-Host "Dauer:   $RunDuration"
Write-Host "Fertig:  $($RunFinishedAt.ToString('yyyy-MM-dd HH:mm:ss'))"
