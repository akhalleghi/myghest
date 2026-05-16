param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$GitArgs
)

$ErrorActionPreference = 'Stop'

$repoRoot = git rev-parse --show-toplevel
Set-Location $repoRoot

& "$PSScriptRoot\resolve-origin.ps1" -Quiet

if ($GitArgs.Count -eq 0) {
    & git pull
} else {
    & git pull @GitArgs
}

exit $LASTEXITCODE
