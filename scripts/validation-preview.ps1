[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [string] $Ref
)

$ErrorActionPreference = 'Stop'

$Repo = 'Wiiii90/moeller-lars'
$Workflow = 'preview.yml'

if ([string]::IsNullOrWhiteSpace($Ref)) {
    $CurrentBranch = git branch --show-current
    if ($LASTEXITCODE -eq 0) {
        $Ref = ($CurrentBranch | Out-String).Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($Ref)) {
    throw 'No ref supplied and the current Git branch could not be determined.'
}

gh auth status *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub CLI is not authenticated. Run gh auth login first.'
}

$ShaResult = gh api --method GET "repos/$Repo/commits" `
    -f "sha=$Ref" `
    -f 'per_page=1' `
    --jq '.[0].sha'

if ($LASTEXITCODE -ne 0) {
    throw "Could not resolve '$Ref' through the GitHub API."
}

$Sha = ($ShaResult | Out-String).Trim()
if ($Sha -notmatch '^[0-9a-f]{40}$') {
    throw "Could not resolve '$Ref' to an exact 40-character commit SHA."
}

$BeforeJson = gh run list `
    --repo $Repo `
    --workflow $Workflow `
    --branch main `
    --event workflow_dispatch `
    --limit 50 `
    --json databaseId

if ($LASTEXITCODE -ne 0) {
    throw 'Could not list existing preview workflow runs.'
}

$BeforeIds = @()
if (-not [string]::IsNullOrWhiteSpace($BeforeJson)) {
    $BeforeIds = @(
        ($BeforeJson | ConvertFrom-Json) |
            ForEach-Object { [int64] $_.databaseId }
    )
}

gh workflow run $Workflow `
    --repo $Repo `
    --ref main `
    -f "target_ref=$Sha"

if ($LASTEXITCODE -ne 0) {
    throw 'Could not dispatch the preview workflow.'
}

$ExpectedTitle = "Validation preview $Sha"
$Run = $null

for ($Attempt = 0; $Attempt -lt 60 -and $null -eq $Run; $Attempt++) {
    Start-Sleep -Seconds 2

    $RunsJson = gh run list `
        --repo $Repo `
        --workflow $Workflow `
        --branch main `
        --event workflow_dispatch `
        --limit 50 `
        --json databaseId,displayTitle,createdAt,status,url

    if ($LASTEXITCODE -ne 0) {
        throw 'Could not query preview workflow runs after dispatch.'
    }

    $Runs = @($RunsJson | ConvertFrom-Json)
    $Run = $Runs |
        Where-Object {
            $_.displayTitle -eq $ExpectedTitle -and
            ([int64] $_.databaseId -notin $BeforeIds)
        } |
        Sort-Object createdAt -Descending |
        Select-Object -First 1
}

if ($null -eq $Run) {
    throw 'The dispatched preview run could not be identified.'
}

gh run watch ([int64] $Run.databaseId) `
    --repo $Repo `
    --exit-status

if ($LASTEXITCODE -ne 0) {
    throw "Preview workflow run $($Run.databaseId) failed."
}

$FinalJson = gh run view ([int64] $Run.databaseId) `
    --repo $Repo `
    --json conclusion,url

if ($LASTEXITCODE -ne 0) {
    throw "Preview workflow run $($Run.databaseId) completed, but final metadata could not be read."
}

$Final = $FinalJson | ConvertFrom-Json

Write-Host ''
Write-Host "PREVIEW_SHA=$Sha"
Write-Host "RUN_ID=$($Run.databaseId)"
Write-Host "RUN_URL=$($Final.url)"
Write-Host "IMAGE=ghcr.io/wiiii90/moeller-lars:$Sha"
Write-Host "VALIDATION_COMMAND=sudo server-platform-moeller-lars-validation update $Sha"
