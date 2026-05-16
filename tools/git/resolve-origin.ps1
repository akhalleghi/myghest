# Resolves whether origin should point to GitHub or GitLab (GitLab when GitHub is unreachable).
param(
    [switch]$Quiet
)

$ErrorActionPreference = 'Stop'

$repoRoot = git rev-parse --show-toplevel 2>$null
if (-not $repoRoot) {
    exit 1
}

Set-Location $repoRoot

$githubUrl = 'https://github.com/akhalleghi/myghest.git'
$gitlabUrl = 'https://gitlab.aranserver.com/akhalleghi/myghest.git'

function Test-GitHubReachable {
    try {
        $null = Invoke-WebRequest -Uri 'https://github.com' -Method Head -TimeoutSec 8 -UseBasicParsing
        return $true
    } catch {
        return $false
    }
}

$useGitHub = Test-GitHubReachable
$targetUrl = if ($useGitHub) { $githubUrl } else { $gitlabUrl }
$targetName = if ($useGitHub) { 'GitHub' } else { 'GitLab' }

$currentUrl = (git remote get-url origin 2>$null)
if ($currentUrl -ne $targetUrl) {
    git remote set-url origin $targetUrl | Out-Null
}

if (-not $Quiet) {
    if ($useGitHub) {
        Write-Host "GitHub در دسترس است؛ push/pull از origin به GitHub می‌رود."
    } else {
        Write-Host "GitHub در دسترس نیست؛ push/pull از origin به GitLab ($gitlabUrl) می‌رود."
    }
}

exit 0
