<#
  Teacher System - deploy to MonsterASP.NET via Web Deploy.

  Usage (from the project root, in PowerShell):
      .\deploy\deploy.ps1                 # build + upload
      .\deploy\deploy.ps1 -Migrate        # build + run migrations/seeders on the live DB + upload
      .\deploy\deploy.ps1 -DryRun         # build + show what would be uploaded, change nothing
      .\deploy\deploy.ps1 -Site teacher-sys   # use a different deploy\<Site>*.publishSettings (default: meky)

  Needs on this PC: PHP 8.4+, Composer, Microsoft Web Deploy (msdeploy.exe).
  Reads:  deploy\*.publishSettings  (your MonsterASP Web Deploy profile)
          .env.production            (becomes .env on the server)
#>
[CmdletBinding()]
param(
    [switch]$Migrate,
    [switch]$DryRun,
    [string]$Site = 'meky'
)

$ErrorActionPreference = 'Stop'
$Root    = Split-Path $PSScriptRoot -Parent
$Release = Join-Path $PSScriptRoot 'release'

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }

# ---------------------------------------------------------------- checks
Step 'Checking tools and config'

$msdeploy = @(
    "$env:ProgramFiles\IIS\Microsoft Web Deploy V3\msdeploy.exe",
    "${env:ProgramFiles(x86)}\IIS\Microsoft Web Deploy V3\msdeploy.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $msdeploy) {
    throw "msdeploy.exe not found. Install Web Deploy: https://www.iis.net/downloads/microsoft/web-deploy"
}
if (-not (Get-Command php -ErrorAction SilentlyContinue))      { throw 'php is not on PATH.' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { throw 'composer is not on PATH.' }

$phpVer = (& php -r 'echo PHP_VERSION;')
if ([version]($phpVer -replace '[^0-9.].*$','') -lt [version]'8.4.1') {
    throw "PHP $phpVer found; the locked packages (Symfony 8.1) need PHP 8.4.1+."
}

$profileFile = Get-ChildItem $PSScriptRoot -Filter "$Site*.publishSettings" | Select-Object -First 1
if (-not $profileFile) { throw "No $Site*.publishSettings found in $PSScriptRoot" }
Write-Host "Profile: $($profileFile.Name)"
[xml]$xml = Get-Content $profileFile.FullName
$pub = $xml.publishData.publishProfile | Where-Object { $_.publishMethod -eq 'MSDeploy' } | Select-Object -First 1

$envProd = Join-Path $Root '.env.production'
if (-not (Test-Path $envProd)) { throw '.env.production not found in the project root.' }
if (Select-String -Path $envProd -Pattern 'YOUR_|YOUR-' -Quiet) {
    throw '.env.production still has YOUR_... placeholders (DB details). Fill them in first.'
}

# ---------------------------------------------------------------- build
Step "Building release folder: $Release"

$excludeDirs = @(
    'vendor', 'node_modules', '.git', '.idea', '.vscode', '.claude', '.cursor', 'tests',
    (Join-Path $Root 'deploy'),
    (Join-Path $Root 'storage\logs'),
    (Join-Path $Root 'storage\framework\cache\data'),
    (Join-Path $Root 'storage\framework\sessions'),
    (Join-Path $Root 'storage\framework\views'),
    (Join-Path $Root 'storage\pail')
)
$excludeFiles = @(
    '.env', '.env.*', 'teacher_system', 'database.sqlite', '*.log',
    '.phpunit.result.cache', 'phpunit.xml', '.mcp.json', 'boost.json',
    'CLAUDE.md', 'AGENTS.md'
)

$rc = @($Root, $Release, '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/XD') + $excludeDirs + @('/XF') + $excludeFiles
& robocopy @rc | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed (exit $LASTEXITCODE)" }

# Writable folders Laravel expects
'storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions',
'storage\framework\views', 'storage\app\public', 'bootstrap\cache', 'public\storage' |
    ForEach-Object { New-Item -ItemType Directory -Force -Path (Join-Path $Release $_) | Out-Null }

# Local caches list dev-only packages (pail, boost, collision) -> remove so they are rebuilt
Get-ChildItem (Join-Path $Release 'bootstrap\cache') -Filter '*.php' | Remove-Item -Force

Copy-Item $envProd (Join-Path $Release '.env') -Force

Step 'composer install --no-dev'
Push-Location $Release
try {
    & composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

    if ($Migrate) {
        Step 'Running migrations + seeders on the LIVE database'
        # The server uses the DB's internal hostname; from this PC we need the public one
        # (MonsterASP -> Databases -> Remote access must be Enabled).
        $remote = (Select-String -Path '.env' -Pattern '^DB_REMOTE_HOST=(.+)$').Matches.Groups[1].Value
        if ($remote) { $env:DB_HOST = $remote.Trim('"'); Write-Host "Using DB host $env:DB_HOST" }
        & php artisan migrate --force
        if ($LASTEXITCODE -ne 0) { throw 'migrate failed (is remote access to the MonsterASP DB allowed?)' }
        & php artisan db:seed --force
        if ($LASTEXITCODE -ne 0) { throw 'db:seed failed' }
        Remove-Item Env:DB_HOST -ErrorAction SilentlyContinue
    }

    # Clear anything artisan cached with local (D:\...) paths
    Get-ChildItem (Join-Path $Release 'bootstrap\cache') -Filter '*.php' |
        Where-Object { $_.Name -notin 'packages.php', 'services.php' } | Remove-Item -Force
}
finally { Pop-Location; Remove-Item Env:DB_HOST -ErrorAction SilentlyContinue }

# ---------------------------------------------------------------- upload
Step "Uploading to $($pub.destinationAppUrl)"

$dest = "contentPath=`"$($pub.msdeploySite)`"," +
        "computerName=`"https://$($pub.publishUrl):8172/msdeploy.axd?site=$($pub.msdeploySite)`"," +
        "userName=`"$($pub.userName)`",password=`"$($pub.userPWD)`",authType=`"Basic`""

$msArgs = @(
    '-verb:sync',
    "-source:contentPath=`"$Release`"",
    "-dest:$dest",
    '-allowUntrusted',
    '-enableRule:DoNotDeleteRule',   # keep server-side logs, sessions, uploaded files
    '-retryAttempts:3'
)
if ($DryRun) { $msArgs += '-whatif' }

$p = Start-Process -FilePath $msdeploy -ArgumentList ($msArgs -join ' ') -NoNewWindow -Wait -PassThru
if ($p.ExitCode -ne 0) { throw "msdeploy failed (exit $($p.ExitCode))" }

Write-Host "`nDone -> $($pub.destinationAppUrl)" -ForegroundColor Green
