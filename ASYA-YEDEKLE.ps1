param(
    [string] $CommitMessage = ''
)

$ErrorActionPreference = 'Stop'
$projectPath = 'C:\ASYA'
$gitPath = 'C:\Program Files\Git\cmd\git.exe'

if (-not (Test-Path -LiteralPath (Join-Path $projectPath '.git'))) {
    throw 'Local Git deposu bulunamadı.'
}

Set-Location $projectPath

if ([string]::IsNullOrWhiteSpace($CommitMessage)) {
    $CommitMessage = 'ASYA otomatik yedek '.(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
}

& $gitPath add --all
$pendingChanges = (& $gitPath diff --cached --name-only)

if ([string]::IsNullOrWhiteSpace(($pendingChanges -join ''))) {
    Write-Host 'ASYA yedeği: değişiklik yok.' -ForegroundColor DarkGray
    exit 0
}

& $gitPath commit -m $CommitMessage
if ($LASTEXITCODE -ne 0) {
    throw 'Local Git commit oluşturulamadı.'
}

& $gitPath push --set-upstream origin main
if ($LASTEXITCODE -ne 0) {
    throw 'GitHub push başarısız oldu; local commit korunuyor.'
}

Write-Host 'ASYA yedeği: local commit ve GitHub push tamamlandı.' -ForegroundColor Green