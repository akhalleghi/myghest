# Smart push: try GitHub (origin) first; fall back to GitLab when unreachable.
param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Args)

$ErrorActionPreference = 'Continue'
Set-Location (git rev-parse --show-toplevel)

function Test-GitRemoteReachable([string]$RemoteName, [int]$TimeoutSeconds = 8) {
    $url = git remote get-url $RemoteName 2>$null
    if (-not $url) { return $false }
    $job = Start-Job -ScriptBlock {
        param($RemoteUrl)
        $env:GIT_TERMINAL_PROMPT = '0'
        git ls-remote --heads $RemoteUrl 2>$null | Out-Null
        return $LASTEXITCODE -eq 0
    } -ArgumentList $url
    $done = Wait-Job $job -Timeout $TimeoutSeconds
    if (-not $done) {
        Stop-Job $job -Force -ErrorAction SilentlyContinue
        Remove-Job $job -Force -ErrorAction SilentlyContinue
        return $false
    }
    $reachable = (Receive-Job $job)
    Remove-Job $job -Force -ErrorAction SilentlyContinue
    return [bool]$reachable
}

function Invoke-GitPush([string[]]$PushArgs) {
    & git -c alias.push= push @PushArgs
    exit $LASTEXITCODE
}

$pushArgs = @($Args)

if ($pushArgs -contains 'gitlab') {
    Invoke-GitPush $pushArgs
}

$usesOrigin = ($pushArgs.Count -eq 0) -or ($pushArgs -contains 'origin')

if ($usesOrigin -and -not (Test-GitRemoteReachable 'origin')) {
    Write-Host '[git] GitHub (origin) is not reachable. Pushing to GitLab...' -ForegroundColor Yellow
    if ($pushArgs.Count -eq 0) {
        $branch = git rev-parse --abbrev-ref HEAD
        Invoke-GitPush @('gitlab', $branch)
    }
    else {
        $pushArgs = $pushArgs | ForEach-Object { if ($_ -eq 'origin') { 'gitlab' } else { $_ } }
        Invoke-GitPush $pushArgs
    }
}

if ($usesOrigin) {
    Write-Host '[git] GitHub (origin) is reachable.' -ForegroundColor Green
}

Invoke-GitPush $pushArgs
