# Dedicated disposable PostgreSQL runner; never uses the persistent preview.
$ErrorActionPreference = 'Stop'
$repoPath = (Get-Location).Path
if ((git branch --show-current) -notin @('dev', 'testing') -or (git rev-parse --show-toplevel).Replace('/', '\') -ne $repoPath) {
    throw 'Run from the root of the dev or testing checkout; only disposable PostgreSQL and isolated runtime paths are permitted.'
}
$token = [guid]::NewGuid().ToString('N')
$relativeRoot = "storage/framework/testing/concurrency-$token"
$runtimeRoot = Join-Path $repoPath $relativeRoot
$containerName = "moeller-concurrency-$token"
$databaseName = "concurrency_$token"
$passwordValue = [guid]::NewGuid().ToString('N')
$oldEnvironment = @{}
$createdContainer = $false
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
    Write-Output "Verified PostgreSQL: $identity; loopback port $($binding.HostPort); tmpfs; no persistent mounts."
    $values = @{
        CONCURRENCY_RUNTIME=$runtimeRoot; CONCURRENCY_DATABASE=$databaseName; CONCURRENCY_USER=$databaseName; CONCURRENCY_PORT=$binding.HostPort
        APP_ENV='testing'; APP_KEY='base64:AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8='
        DB_CONNECTION='pgsql'; DB_HOST='127.0.0.1'; DB_PORT=$binding.HostPort; DB_DATABASE=$databaseName; DB_USERNAME=$databaseName; DB_PASSWORD=$passwordValue; DB_URL=''
        LARAVEL_STORAGE_PATH="$runtimeRoot/storage"; MEDIA_DISK='local'; FILESYSTEM_DISK='local'; MEDIA_STORAGE_QUOTA_BYTES='1000000000'
        APP_CONFIG_CACHE="$relativeRoot/bootstrap/cache/config.php"; APP_PACKAGES_CACHE="$relativeRoot/bootstrap/cache/packages.php"; APP_SERVICES_CACHE="$relativeRoot/bootstrap/cache/services.php"; APP_EVENTS_CACHE="$relativeRoot/bootstrap/cache/events.php"; APP_ROUTES_CACHE="$relativeRoot/bootstrap/cache/routes.php"
        VIEW_COMPILED_PATH="$runtimeRoot/storage/framework/views"; LOG_CHANNEL='stderr'; TMP="$runtimeRoot/php-temp"; TEMP="$runtimeRoot/php-temp"; TMPDIR="$runtimeRoot/php-temp"
        GITHUB_ACTIONS=$null; MOELLER_LARS_DISPOSABLE_TEST_DATABASE=$null; MOELLER_LARS_PERSISTENT_PREVIEW=$null
    }
    foreach ($entry in $values.GetEnumerator()) {
        $oldEnvironment[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
    }
    foreach ($directory in @('storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache','php-temp','php-upload','phpunit-cache')) {
        New-Item -ItemType Directory -Force (Join-Path $runtimeRoot $directory) | Out-Null
    }
    New-Item -ItemType File "$runtimeRoot/.env.testing" | Out-Null
    # Each worker independently checks Laravel/PDO, PHPUnit and paths before
    # enabling disposable flags. The controller itself never boots Laravel.
    php -d variables_order=EGPCS -d "sys_temp_dir=$runtimeRoot/php-temp" -d "upload_tmp_dir=$runtimeRoot/php-upload" vendor/bin/pest tests/Integration/Publication/PublicationConcurrencyTest.php --cache-directory "$runtimeRoot/phpunit-cache" @args
    $testExit = $LASTEXITCODE
} finally {
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
