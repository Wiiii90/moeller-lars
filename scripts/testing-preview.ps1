[CmdletBinding()]
param([switch] $Stop)

$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$config = Join-Path $PSScriptRoot 'testing-preview.compose.yaml'
$project = 'moeller-lars-testing-preview'
$source = 'f0d168b22437668a29485ae308880e09831c4447'
$compose = @('compose', '--project-name', $project, '-f', $config)

Push-Location $repo
try {
    $commonDir = (git rev-parse --path-format=absolute --git-common-dir).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'Could not locate the existing local checkout.' }
    $mainRepo = Split-Path -Parent $commonDir
    $sharedEnv = Join-Path $mainRepo '.env'
    $sharedMedia = Join-Path $mainRepo 'storage/app/private'
    if (-not (Test-Path -LiteralPath $sharedEnv -PathType Leaf) -or -not (Test-Path -LiteralPath $sharedMedia -PathType Container)) {
        throw 'The existing local environment or media directory is unavailable.'
    }

    $postgresEnv = @(docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' moeller-lars-postgres-1)
    if ($LASTEXITCODE -ne 0) { throw 'The existing local PostgreSQL container is unavailable.' }
    $passwordLine = @($postgresEnv | Where-Object { $_ -like 'POSTGRES_PASSWORD=*' })
    if ($passwordLine.Count -ne 1) { throw 'The existing database credentials could not be identified.' }
    $env:TESTING_PREVIEW_SOURCE = $source
    $env:TESTING_PREVIEW_SHARED_ENV = $sharedEnv.Replace('\', '/')
    $env:TESTING_PREVIEW_MEDIA_PATH = $sharedMedia.Replace('\', '/')
    $env:TESTING_PREVIEW_SHARED_DB_PASSWORD = $passwordLine[0].Substring('POSTGRES_PASSWORD='.Length)

    if ($Stop) {
        & docker @compose stop
        if ($LASTEXITCODE -ne 0) { throw 'Could not stop the Testing preview.' }
        Write-Host 'Testing preview stopped; the shared database and media were not stopped or removed.'
        return
    }

    if ((git branch --show-current).Trim() -ne 'testing') { throw 'The Testing preview must be built from the testing worktree.' }
    $sourcePaths = @('app', 'bootstrap', 'config', 'database', 'docker', 'public', 'resources', 'routes', 'scripts', 'Dockerfile', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', ':!scripts/testing-preview.ps1', ':!scripts/testing-preview.compose.yaml')
    & git diff --quiet $source HEAD -- @sourcePaths
    if ($LASTEXITCODE -ne 0) { throw 'The committed application or frontend differs from the qualified source.' }
    & git diff --quiet -- @sourcePaths
    if ($LASTEXITCODE -ne 0) { throw 'Uncommitted application or frontend changes would affect the preview.' }
    & git diff --cached --quiet -- @sourcePaths
    if ($LASTEXITCODE -ne 0) { throw 'Staged application or frontend changes would affect the preview.' }
    $untracked = @(git ls-files --others --exclude-standard -- @sourcePaths)
    if ($untracked.Count -gt 0) { throw 'Untracked application or frontend files would affect the preview.' }

    & docker info --format '{{.ServerVersion}}' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Docker is not available.' }
    $volume = "${project}_storage"
    $existing = @(docker volume ls --quiet --filter "name=^${volume}$")
    if ($existing -contains $volume) {
        $owner = docker volume inspect --format '{{ index .Labels "com.docker.compose.project" }}' $volume
        if ($LASTEXITCODE -ne 0 -or $owner -ne $project) { throw 'The Testing storage volume belongs to another project.' }
    }
    & docker @compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw 'The Testing preview configuration is invalid.' }
    & docker @compose build preview
    if ($LASTEXITCODE -ne 0) { throw 'The Testing preview image build failed.' }

    $dbState = (docker inspect --format '{{.State.Status}}' moeller-lars-postgres-1).Trim()
    if ($LASTEXITCODE -ne 0) { throw 'The existing PostgreSQL state could not be inspected.' }
    if ($dbState -ne 'running') {
        & docker start moeller-lars-postgres-1 | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'The existing local database could not be started.' }
    }
    $ready = $false
    foreach ($attempt in 1..30) {
        & docker exec moeller-lars-postgres-1 pg_isready -U postgres -d moeller_lars_testing *> $null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'The existing local database is not ready.' }
    $identity = (docker exec moeller-lars-postgres-1 psql -XAt -U postgres -d moeller_lars_testing -c "select current_database(), current_user, current_setting('server_version_num')" | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $identity -notmatch '^moeller_lars_testing\|postgres\|17[0-9]{4}$') {
        throw 'The existing PostgreSQL connection identity is not the expected local PostgreSQL 17 database.'
    }
    $applied = @(docker exec moeller-lars-postgres-1 psql -XAt -U postgres -d moeller_lars_testing -c 'select migration from migrations')
    if ($LASTEXITCODE -ne 0) { throw 'The existing migration status could not be read; no migration was run.' }
    $missing = @(Get-ChildItem -LiteralPath (Join-Path $repo 'database/migrations') -Filter '*.php' | Where-Object { $_.BaseName -notin $applied })
    if ($missing.Count -gt 0) { throw "The existing database is missing $($missing.Count) migrations; none were run." }

    & docker @compose up -d --no-build --wait preview
    if ($LASTEXITCODE -ne 0) { throw 'The Testing preview could not start.' }
    $http = Invoke-WebRequest -Uri 'http://127.0.0.1:8002/up' -UseBasicParsing -TimeoutSec 15
    if ($http.StatusCode -ne 200) { throw 'The Testing preview health endpoint failed.' }
    $actualSource = (& docker @compose exec -T preview printenv APP_RELEASE_SHA | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $actualSource -ne $source) { throw 'The running image source does not match the qualified application.' }
    Write-Host "Testing preview: http://127.0.0.1:8002 (application $actualSource)"
    Write-Host 'The existing local database is shared and editable; existing media is mounted read-only.'
    Write-Host 'Use the existing local administrator login. Stop: .\scripts\testing-preview.ps1 -Stop'
} finally {
    Remove-Item Env:TESTING_PREVIEW_SHARED_DB_PASSWORD -ErrorAction SilentlyContinue
    Pop-Location
}
