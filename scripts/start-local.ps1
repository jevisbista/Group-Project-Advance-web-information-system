param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$publicDirectory = Join-Path $projectDirectory 'public'
$storageDirectory = Join-Path $projectDirectory 'storage'
$phpExecutable = 'C:\xampp\php\php.exe'
$applicationUrl = "http://127.0.0.1:$Port"

if (-not (Test-Path -LiteralPath $phpExecutable -PathType Leaf)) {
    throw 'PHP was not found at C:\xampp\php\php.exe. Update this script for your XAMPP installation.'
}
if (-not (Test-Path -LiteralPath (Join-Path $publicDirectory 'dashboard.php') -PathType Leaf)) {
    throw 'The ClubConnect public directory could not be found.'
}

# Reuse an existing instance without starting a duplicate or stopping another app.
$existingResponse = $null
try {
    $existingResponse = Invoke-WebRequest -Uri "$applicationUrl/dashboard.php" -UseBasicParsing -TimeoutSec 2
} catch {
    if ($_.Exception.Response) {
        throw "A server is already responding on port $Port with an error. Check its configuration or choose another -Port."
    }
}
if ($existingResponse) {
    if ($existingResponse.Content -notmatch '<title>[^<]*ClubConnect</title>') {
        throw "Port $Port is already used by another application. Choose another -Port."
    }
    Write-Output "ClubConnect is already available at $applicationUrl/dashboard.php"
    return
}

New-Item -ItemType Directory -Path $storageDirectory -Force | Out-Null
$logSuffix = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$stdoutLog = Join-Path $storageDirectory "local-server-$Port-$logSuffix.stdout.log"
$stderrLog = Join-Path $storageDirectory "local-server-$Port-$logSuffix.stderr.log"

# Hidden, separate Windows process: closing this launcher does not close the server.
# Bind only to this computer and expose public/, never the project/configuration root.
$serverProcess = Start-Process -FilePath $phpExecutable `
    -ArgumentList @('-S', "127.0.0.1:$Port", '-t', ('"' + $publicDirectory + '"')) `
    -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog

for ($attempt = 0; $attempt -lt 20; $attempt++) {
    Start-Sleep -Milliseconds 250
    $serverProcess.Refresh()
    if ($serverProcess.HasExited) {
        throw "The local PHP server stopped. Check $stderrLog"
    }
    try {
        $response = Invoke-WebRequest -Uri "$applicationUrl/dashboard.php" -UseBasicParsing -TimeoutSec 2
        if ($response.StatusCode -eq 200 -and $response.Content -match '<title>[^<]*ClubConnect</title>') {
            Write-Output "ClubConnect is running at $applicationUrl/dashboard.php"
            Write-Output "Server process: $($serverProcess.Id). Keep MySQL running in XAMPP."
            Write-Output 'Run this script again after restarting Windows.'
            return
        }
    } catch {
        # A newly launched server may need a moment to become ready.
    }
}
throw "The PHP process started, but the application is not ready. Ensure MySQL is running and review $stderrLog and storage/php-error.log."
