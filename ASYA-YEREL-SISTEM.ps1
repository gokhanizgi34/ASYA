$ErrorActionPreference = 'Stop'

$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$phpPath = $env:ASYA_PHP_PATH

if ([string]::IsNullOrWhiteSpace($phpPath)) {
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($null -ne $phpCommand) {
        $phpPath = $phpCommand.Source
    }
}

if ([string]::IsNullOrWhiteSpace($phpPath) -or -not (Test-Path -LiteralPath $phpPath)) {
    throw 'PHP bulunamadı. ASYA_PHP_PATH ortam değişkenini tanımlayın veya PHP''yi PATH''e ekleyin.'
}
$artisanPath = Join-Path $projectPath 'artisan'
$runtimePath = Join-Path $projectPath 'storage\app\asya-local'
$logPath = Join-Path $projectPath 'storage\logs'

New-Item -ItemType Directory -Force -Path $runtimePath, $logPath | Out-Null

function Test-AsyaProcess {
    param(
        [Parameter(Mandatory)]
        [string] $PidFile,

        [Parameter(Mandatory)]
        [string] $Marker
    )

    if (-not (Test-Path -LiteralPath $PidFile)) {
        return $false
    }

    $processId = [int] (Get-Content -LiteralPath $PidFile -Raw)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue

    return $null -ne $process -and $process.CommandLine -like "*$projectPath*" -and $process.CommandLine -like "*$Marker*"
}

function Start-AsyaProcess {
    param(
        [Parameter(Mandatory)]
        [string] $Name,

        [Parameter(Mandatory)]
        [string[]] $Arguments,

        [Parameter(Mandatory)]
        [string] $Marker
    )

    $pidFile = Join-Path $runtimePath "$Name.pid"

    if (Test-AsyaProcess -PidFile $pidFile -Marker $Marker) {
        return
    }

    $process = Start-Process -FilePath $phpPath `
        -ArgumentList $Arguments `
        -WorkingDirectory $projectPath `
        -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logPath "$Name-output.log") `
        -RedirectStandardError (Join-Path $logPath "$Name-error.log") `
        -PassThru

    [IO.File]::WriteAllText($pidFile, [string] $process.Id)
}

$port = $env:ASYA_PORT
if ([string]::IsNullOrWhiteSpace($port)) {
    $port = '8000'
}

while ($true) {
    Start-AsyaProcess -Name 'asya-server' -Marker 'artisan serve' -Arguments @(
        $artisanPath, 'serve', '--host=127.0.0.1', ('--port={0}' -f $port), '--no-reload'
    )

    Start-AsyaProcess -Name 'asya-queue-ingestion' -Marker '--queue=news-ingestion' -Arguments @(
        $artisanPath, 'queue:work', '--queue=news-ingestion',
        '--sleep=1', '--tries=3', '--timeout=300', '--max-time=3600'
    )

    Start-AsyaProcess -Name 'asya-queue-content' -Marker '--queue=content' -Arguments @(
        $artisanPath, 'queue:work', '--queue=content',
        '--sleep=1', '--tries=3', '--timeout=300', '--max-time=3600'
    )

    Start-AsyaProcess -Name 'asya-queue-publishing' -Marker '--queue=publishing' -Arguments @(
        $artisanPath, 'queue:work', '--queue=publishing',
        '--sleep=1', '--tries=3', '--timeout=300', '--max-time=3600'
    )

    Start-AsyaProcess -Name 'asya-queue-operations' -Marker '--queue=scheduling,analytics,default' -Arguments @(
        $artisanPath, 'queue:work', '--queue=scheduling,analytics,default',
        '--sleep=1', '--tries=3', '--timeout=300', '--max-time=3600'
    )

    Start-AsyaProcess -Name 'asya-schedule' -Marker 'schedule:work' -Arguments @(
        $artisanPath, 'schedule:work'
    )

    Start-Sleep -Seconds 10
}