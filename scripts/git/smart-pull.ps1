# Smart pull: try GitHub (origin) first; fall back to GitLab when unreachable.
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

function Invoke-GitPull([string[]]$PullArgs) {
    & git -c alias.pull= pull @PullArgs
    exit $LASTEXITCODE
}

$pullArgs = @($Args)

if ($pullArgs -contains 'gitlab') {
    Invoke-GitPull $pullArgs
}

$usesOrigin = ($pullArgs.Count -eq 0) -or ($pullArgs -contains 'origin')

if ($usesOrigin -and -not (Test-GitRemoteReachable 'origin')) {
    Write-Host '[git] GitHub (origin) is not reachable. Pulling from GitLab...' -ForegroundColor Yellow
    if ($pullArgs.Count -eq 0) {
        $branch = git rev-parse --abbrev-ref HEAD
        Invoke-GitPull @('gitlab', $branch)
    }
    else {
        $pullArgs = $pullArgs | ForEach-Object { if ($_ -eq 'origin') { 'gitlab' } else { $_ } }
        Invoke-GitPull $pullArgs
    }
}

if ($usesOrigin) {
    Write-Host '[git] GitHub (origin) is reachable.' -ForegroundColor Green
}

Invoke-GitPull $pullArgs
