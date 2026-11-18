$ErrorActionPreference = 'Stop'

# Optional browser-engine comparison. The normal local-preview.ps1 is deliberately
# independent of this large Playwright image and never builds or starts it.
Set-Location 'P:\moeller-lars'

$State = docker inspect --format '{{.State.Health.Status}}' moeller-lars-local-web
if ($LASTEXITCODE -ne 0 -or $State -ne 'healthy') {
    throw 'Start the healthy application Preview with scripts/local-preview.ps1 before enabling optional browser comparisons.'
}

$RunTimer = [System.Diagnostics.Stopwatch]::StartNew()
Write-Host 'Building optional browser comparisons (Chromium, WebKit, Firefox)'
docker compose --profile preview --profile browser-preview build preview-renderer
if ($LASTEXITCODE -ne 0) {
    throw 'Optional browser renderer build failed. The existing application Preview is unchanged.'
}

Write-Host 'Starting optional browser renderer'
docker compose --profile preview --profile browser-preview up -d --no-build --wait preview-renderer
if ($LASTEXITCODE -ne 0) {
    throw 'Optional browser renderer failed to start. The application Preview remains available.'
}

$RunTimer.Stop()
$RunFinishedAt = Get-Date
$RunDuration = '{0:00}:{1:00}:{2:00}' -f [math]::Floor($RunTimer.Elapsed.TotalHours), $RunTimer.Elapsed.Minutes, $RunTimer.Elapsed.Seconds

Write-Host ''
Write-Host 'Browser comparison: ready for local Live Preview'
Write-Host "Duration: $RunDuration"
Write-Host "Finished: $($RunFinishedAt.ToString('yyyy-MM-dd HH:mm:ss'))"
