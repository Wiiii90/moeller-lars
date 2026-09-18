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

docker compose up -d --build --wait preview
if ($LASTEXITCODE) { throw 'Lokaler Preview-Build/Start fehlgeschlagen' }

Write-Host ''
Write-Host "Git:     $($env:APP_GIT_SHA.Substring(0, 7))"
Write-Host 'Preview: http://127.0.0.1:8001'
