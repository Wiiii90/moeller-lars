# Dedicated disposable PostgreSQL runner; never uses the persistent preview.
$QualificationSuite = $null
if ($args.Count -gt 0 -and $args[0] -eq '-QualificationSuite') {
    if ($args.Count -lt 2 -or $args[1] -notin @('Migration', 'Integration', 'Feature', 'Browser')) {
        throw 'Specify Migration, Integration, Feature, or Browser after -QualificationSuite.'
    }
    $QualificationSuite = $args[1]
    $args = @($args | Select-Object -Skip 2)
}

$linux = $QualificationSuite -ne 'Browser' -and $args.Count -gt 0 -and $args[0] -eq '-Linux'
if ($linux) { $args = @($args | Select-Object -Skip 1) }
$ErrorActionPreference = 'Stop'
$repoPath = (Get-Location).Path
if ((git branch --show-current) -notin @('dev', 'testing') -or (git rev-parse --show-toplevel).Replace('/', '\') -ne $repoPath) {
    throw 'Run from the root of the dev or testing checkout; only disposable PostgreSQL and isolated runtime paths are permitted.'
}
function Invoke-QualificationPhp {
    if (-not $linux) {
        php @phpOptions @args
        return
    }

    $containerRoot = "/app/$relativeRoot"
    $containerEnv = @()
    foreach ($entry in $values.GetEnumerator()) {
        $value = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
        if ($null -eq $value) { continue }
        $value = $value.Replace($runtimeRoot, $containerRoot)
        if ($entry.Key -in @('DB_PORT', 'CONCURRENCY_PORT')) { $value = '5432' }
        $containerEnv += @('--env', "$($entry.Key)=$value")
    }
    $linuxArgs = @($args | ForEach-Object { $_.Replace($runtimeRoot, $containerRoot) })
    docker run --rm --network "container:$containerId" --entrypoint php --workdir /app `
        --mount "type=bind,source=$repoPath,target=/app,readonly" `
        --mount "type=bind,source=$runtimeRoot,target=$containerRoot" `
        @containerEnv moeller-lars-local-preview:latest `
        -d memory_limit=512M -d variables_order=EGPCS `
        -d "sys_temp_dir=$containerRoot/php-temp" -d "upload_tmp_dir=$containerRoot/php-upload" @linuxArgs
}

$token = [guid]::NewGuid().ToString('N')
$relativeRoot = "storage/framework/testing/concurrency-$token"
$runtimeRoot = Join-Path $repoPath $relativeRoot
$containerName = "moeller-concurrency-$token"
$databaseName = "concurrency_$token"
$passwordValue = [guid]::NewGuid().ToString('N')
$oldEnvironment = @{}
$createdContainer = $false
$browserServer = $null
New-Item -ItemType Directory -Path $runtimeRoot | Out-Null
try {
    $containerId = docker run -d --name $containerName --publish '127.0.0.1::5432' --tmpfs '/var/lib/postgresql/data:rw,noexec,nosuid,size=1073741824' --env "POSTGRES_DB=$databaseName" --env "POSTGRES_USER=$databaseName" --env "POSTGRES_PASSWORD=$passwordValue" postgres:17
    if ($LASTEXITCODE -ne 0) { throw 'Disposable PostgreSQL launch failed.' }
    $createdContainer = $true
    $details = (docker inspect $containerId | ConvertFrom-Json)[0]
    $binding = $details.NetworkSettings.Ports.'5432/tcp'[0]
    if ($details.Mounts.Count -ne 0 -or -not $details.HostConfig.Tmpfs.'/var/lib/postgresql/data' -or $binding.HostIp -ne '127.0.0.1') {
        throw 'Disposable PostgreSQL isolation mismatch.'
    }
    $deadline = [DateTime]::UtcNow.AddSeconds(30)
    do {
        docker exec $containerId pg_isready -h 127.0.0.1 -U $databaseName -d $databaseName | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
    } while ([DateTime]::UtcNow -lt $deadline)
    if ($LASTEXITCODE -ne 0) { throw 'Disposable PostgreSQL did not become ready.' }
    $identity = docker exec --env "PGPASSWORD=$passwordValue" $containerId psql -h 127.0.0.1 -U $databaseName -d $databaseName -Atc "select current_database(),current_user,inet_server_port(),current_setting('server_version_num'),(select count(*) from information_schema.tables where table_schema='public');"
    if ($LASTEXITCODE -ne 0 -or $identity -notmatch "^$databaseName\|$databaseName\|5432\|17[0-9]+\|0$") { throw 'Initial SQL identity/schema verification failed.' }
    $clusterIdentity = docker exec --env "PGPASSWORD=$passwordValue" $containerId psql -h 127.0.0.1 -U $databaseName -d $databaseName -Atc 'select system_identifier::text from pg_control_system();'
    if ($LASTEXITCODE -ne 0 -or $clusterIdentity -notmatch '^[0-9]+$') { throw 'Cluster identity verification failed.' }
    Write-Output "Verified PostgreSQL: $identity; loopback port $($binding.HostPort); tmpfs; no persistent mounts."
    $values = @{
        CONCURRENCY_CLUSTER=$clusterIdentity; CONCURRENCY_RUNTIME=$runtimeRoot; CONCURRENCY_DATABASE=$databaseName; CONCURRENCY_USER=$databaseName; CONCURRENCY_PORT=$binding.HostPort
        APP_ENV='testing'; CACHE_STORE='array'; SESSION_DRIVER='array'; APP_KEY='base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8='
        DB_CONNECTION='pgsql'; DB_HOST='127.0.0.1'; DB_PORT=$binding.HostPort; DB_DATABASE=$databaseName; DB_USERNAME=$databaseName; DB_PASSWORD=$passwordValue; DB_URL=''
        LARAVEL_STORAGE_PATH="$runtimeRoot/storage"; MEDIA_DISK='local'; FILESYSTEM_DISK='local'; MEDIA_STORAGE_QUOTA_BYTES='1000000000'
        APP_CONFIG_CACHE="$relativeRoot/bootstrap/cache/config.php"; APP_PACKAGES_CACHE="$relativeRoot/bootstrap/cache/packages.php"; APP_SERVICES_CACHE="$relativeRoot/bootstrap/cache/services.php"; APP_EVENTS_CACHE="$relativeRoot/bootstrap/cache/events.php"; APP_ROUTES_CACHE="$relativeRoot/bootstrap/cache/routes.php"
        VIEW_COMPILED_PATH="$runtimeRoot/storage/framework/views"; LOG_CHANNEL='stderr'; TMP="$runtimeRoot/php-temp"; TEMP="$runtimeRoot/php-temp"; TMPDIR="$runtimeRoot/php-temp"
        GITHUB_ACTIONS=$null; MOELLER_LARS_DISPOSABLE_TEST_DATABASE=$null; MOELLER_LARS_PERSISTENT_PREVIEW=$null
    }
    if ($QualificationSuite -eq 'Browser') {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        try { $browserPort = $listener.LocalEndpoint.Port } finally { $listener.Stop() }
        $browserUrl = "http://127.0.0.1:$browserPort"
        $values.APP_URL = $browserUrl
        $values.PLAYWRIGHT_BASE_URL = $browserUrl
        $values.SESSION_DRIVER = 'file'
        $values.MOELLER_LARS_BROWSER_PROFILE = '1'
        $values.PLAYWRIGHT_ADMIN_EMAIL = 'playwright-admin@example.test'
        $values.PLAYWRIGHT_ADMIN_PASSWORD = [guid]::NewGuid().ToString('N') + '!Aa7'
        $values.PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE = "$runtimeRoot/storage/app/private/browser-public-media.json"
        $values.PLAYWRIGHT_DATABASE_CONTAINER = $containerId
        $values.PLAYWRIGHT_DATABASE_NAME = $databaseName
        $values.PLAYWRIGHT_DATABASE_USER = $databaseName
        $values.MATOMO_TRACKING_ENABLED = 'false'
        $values.MATOMO_REPORTING_ENABLED = 'false'
    }
    foreach ($entry in $values.GetEnumerator()) {
        $oldEnvironment[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
    }
    foreach ($directory in @('storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache','php-temp','php-upload','phpunit-cache')) {
        New-Item -ItemType Directory -Force (Join-Path $runtimeRoot $directory) | Out-Null
    }
    New-Item -ItemType File "$runtimeRoot/.env.testing" | Out-Null
    $phpOptions = @('-d', 'memory_limit=512M', '-d', 'variables_order=EGPCS', '-d', "sys_temp_dir=$runtimeRoot/php-temp", '-d', "upload_tmp_dir=$runtimeRoot/php-upload")
    if ($QualificationSuite) {
        if ((Test-Path -LiteralPath '.env') -or (Test-Path -LiteralPath '.env.testing')) {
            throw 'Qualification requires a checkout without application environment files.'
        }
        $response = Invoke-QualificationPhp tests/Support/PublicationConcurrencyWorker.php '{"operation":"verify-qualification"}'
        if ($LASTEXITCODE -ne 0) { Write-Output $response; throw 'Effective isolation verification failed.' }
        $evidence = @($response | ForEach-Object { ConvertFrom-Json -InputObject $_ })
        if ($evidence.Count -ne 2 -or $evidence[0].phase -ne 'verified' -or $evidence[1].phase -ne 'result' -or
            $evidence[1].ok -ne $true -or $evidence[1].cluster -ne $clusterIdentity) {
            throw 'Missing positive effective isolation evidence; Pest will not start.'
        }
        Write-Output $response
        # Dedicated concurrency cases retain their own setup and isolation.
        foreach ($key in @('CONCURRENCY_CLUSTER', 'CONCURRENCY_RUNTIME', 'CONCURRENCY_DATABASE', 'CONCURRENCY_USER', 'CONCURRENCY_PORT')) {
            [Environment]::SetEnvironmentVariable($key, $null, 'Process')
        }
        $env:GITHUB_ACTIONS = 'true'
        $env:MOELLER_LARS_DISPOSABLE_TEST_DATABASE = '1'
        if ($QualificationSuite -eq 'Browser') {
            php @phpOptions artisan migrate --force
            if ($LASTEXITCODE -ne 0) { throw 'Browser database migration failed.' }
            php @phpOptions artisan db:seed '--class=Database\Seeders\BrowserProfilingSeeder' --force
            if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $env:PLAYWRIGHT_PUBLIC_MEDIA_FIXTURE_FILE)) {
                throw 'Browser fixture preparation failed.'
            }
            $php = (Get-Command php -CommandType Application).Source
            $router = Join-Path $repoPath 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
            $serverArgs = @($phpOptions | ForEach-Object { '"' + $_ + '"' }) + @('-S', "127.0.0.1:$browserPort", ('"' + $router + '"'))
            $browserServer = Start-Process -FilePath $php -ArgumentList $serverArgs -WorkingDirectory (Join-Path $repoPath 'public') -WindowStyle Hidden -PassThru -RedirectStandardError "$runtimeRoot/browser-server.log"
            $ready = $false
            for ($attempt = 0; $attempt -lt 30; $attempt++) {
                if ($browserServer.HasExited) { break }
                try {
                    $ready = (Invoke-WebRequest "$browserUrl/up" -TimeoutSec 2).StatusCode -eq 200
                    if ($ready) { break }
                } catch { Start-Sleep -Seconds 1 }
            }
            if (-not $ready -or $browserServer.HasExited) {
                Get-Content "$runtimeRoot/browser-server.log" -Tail 15
                throw 'The isolated browser server did not become ready.'
            }
            npm.cmd run test:browser -- @args
        } else {
            Invoke-QualificationPhp vendor/bin/pest "--testsuite=$QualificationSuite" --cache-directory "$runtimeRoot/phpunit-cache" @args
        }
    } else {
        # Each worker verifies its own connection before enabling disposable flags.
        Invoke-QualificationPhp vendor/bin/pest tests/Integration/Publication/PublicationConcurrencyTest.php --cache-directory "$runtimeRoot/phpunit-cache" @args
    }
    $testExit = $LASTEXITCODE
} finally {
    if ($browserServer -and -not $browserServer.HasExited) { Stop-Process -InputObject $browserServer; $browserServer.WaitForExit() }
    foreach ($entry in $oldEnvironment.GetEnumerator()) { [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process') }
    if ($createdContainer) { docker rm -f $containerId | Out-Null }
    $resolvedRoot = (Resolve-Path -LiteralPath $runtimeRoot).Path
    $allowedRoot = (Resolve-Path -LiteralPath (Join-Path $repoPath 'storage/framework/testing')).Path + '\'
    if (-not $resolvedRoot.StartsWith($allowedRoot, [StringComparison]::OrdinalIgnoreCase) -or (Get-ChildItem -LiteralPath $resolvedRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint })) {
        throw 'Cleanup target failed containment/reparse verification; preserved for inspection.'
    }
    Remove-Item -LiteralPath $resolvedRoot -Recurse -Force
}
exit $testExit
