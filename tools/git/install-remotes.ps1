# One-time setup: GitHub + GitLab remotes, smart origin, pre-push hook, pull alias.
$ErrorActionPreference = 'Stop'

$repoRoot = git rev-parse --show-toplevel
Set-Location $repoRoot

$githubUrl = 'https://github.com/akhalleghi/myghest.git'
$gitlabUrl = 'https://gitlab.aranserver.com/akhalleghi/myghest.git'

function Ensure-Remote($name, $url) {
    $existing = git remote 2>$null | Where-Object { $_ -eq $name }
    if ($existing) {
        git remote set-url $name $url | Out-Null
        Write-Host "remote '$name' -> $url"
    } else {
        git remote add $name $url
        Write-Host "remote '$name' added -> $url"
    }
}

Ensure-Remote 'github' $githubUrl
Ensure-Remote 'gitlab' $gitlabUrl

# Default origin to GitLab (GitHub often blocked); resolve-origin.ps1 switches before push/pull.
git remote set-url origin $gitlabUrl | Out-Null
Write-Host "remote 'origin' -> $gitlabUrl (switches to GitHub automatically when reachable)"

$hookPath = Join-Path $repoRoot '.git\hooks\pre-push'
$hookContent = @'
#!/bin/sh
# Auto-select GitHub or GitLab for origin before every push.
cd "$(git rev-parse --show-toplevel)" || exit 0
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "./tools/git/resolve-origin.ps1" -Quiet
exit 0
'@

[System.IO.File]::WriteAllText($hookPath, $hookContent.Replace("`r`n", "`n"))
Write-Host "pre-push hook installed at .git/hooks/pre-push"

$pullScript = (Join-Path $repoRoot 'tools\git\smart-pull.ps1') -replace '\\', '/'
git config --local alias.pull "!powershell -NoProfile -ExecutionPolicy Bypass -File `"$pullScript`""
Write-Host "alias.pull -> smart-pull (resolves origin before pull)"

& "$PSScriptRoot\resolve-origin.ps1"

Write-Host ""
Write-Host "Done. Use your usual workflow:"
Write-Host "  git add ."
Write-Host "  git commit -m `"message`""
Write-Host "  git push origin main"
