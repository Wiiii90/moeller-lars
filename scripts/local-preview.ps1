$ErrorActionPreference = 'Stop'

Set-Location 'P:\moeller-lars'

if (git status --porcelain) {
    throw 'Lokale Git-Aenderungen vorhanden. Erst committen, stashen oder verwerfen.'
}

git switch dev
if ($LASTEXITCODE) { throw 'git switch fehlgeschlagen' }

git pull --ff-only origin dev
if ($LASTEXITCODE) { throw 'git pull fehlgeschlagen' }

$env:APP_GIT_SHA = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE) { throw 'Git SHA konnte nicht gelesen werden' }

Write-Host "Building dev $($env:APP_GIT_SHA.Substring(0, 7))"

docker compose build preview
if ($LASTEXITCODE) { throw 'Lokaler Preview-Build fehlgeschlagen. Alter Preview-Container bleibt unangetastet.' }

docker volume create moeller-lars_postgres_data | Out-Null
if ($LASTEXITCODE) { throw 'Lokales PostgreSQL-Volume konnte nicht bereitgestellt werden.' }

$ExistingPreview = docker ps -aq --filter 'name=^/moeller-lars-local-web$'
if ($LASTEXITCODE) { throw 'Bestehender Preview-Container konnte nicht ermittelt werden.' }

if ($ExistingPreview) {
    docker rm -f $ExistingPreview | Out-Null
    if ($LASTEXITCODE) { throw 'Alter Preview-Container konnte nicht ersetzt werden.' }
}

docker compose up -d --no-build --wait preview
if ($LASTEXITCODE) { throw 'Lokaler Preview-Container konnte nicht gestartet werden.' }

Write-Host ''
Write-Host "Git:     $($env:APP_GIT_SHA.Substring(0, 7))"
Write-Host 'Preview: http://127.0.0.1:8001'
