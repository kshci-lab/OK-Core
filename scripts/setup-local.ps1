[CmdletBinding()]
param(
    [string]$PhpBin = $env:PHP_BIN,
    [string]$ComposerBin = $env:COMPOSER_BIN,
    [string]$BaseUrl = 'http://localhost:8888/OK-Core',
    [string]$ClientId = $env:HCIMLAB_SSO_CLIENT_ID,
    [string]$ClientSecret = $env:HCIMLAB_SSO_CLIENT_SECRET,
    [string]$IdpUrl = $(if ($env:HCIMLAB_SSO_IDP_URL) { $env:HCIMLAB_SSO_IDP_URL } else { 'https://kshci-lab.net/software/hcimlab_auth' }),
    [switch]$Force,
    [switch]$SkipComposerInstall
)

$ErrorActionPreference = 'Stop'

$rootDir = Split-Path -Parent $PSScriptRoot
Set-Location $rootDir

function Resolve-PhpBin {
    param([string]$Candidate)

    if ($Candidate) { return $Candidate }

    $mampPhpRoots = @(
        'C:\MAMP\bin\php\php8.3.14\php.exe',
        'C:\MAMP\bin\php\php8.3.1\php.exe',
        'C:\MAMP\bin\php\php8.2.0\php.exe'
    )

    foreach ($path in $mampPhpRoots) {
        if (Test-Path $path) { return $path }
    }

    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($phpCommand) { return $phpCommand.Source }

    throw 'PHP was not found. Set PHP_BIN and rerun this script.'
}

function Get-PhpRuntimeOptions {
    param([string]$ResolvedPhpBin)

    $phpDir = Split-Path -Parent $ResolvedPhpBin
    $extDir = Join-Path $phpDir 'ext'
    $options = @('-n')

    if (Test-Path $extDir) {
        $options += '-d'
        $options += "extension_dir=$extDir"
    }

    foreach ($extension in @('openssl', 'curl', 'zip', 'mbstring')) {
        $dll = Join-Path $extDir "php_$extension.dll"
        if (Test-Path $dll) {
            $options += '-d'
            $options += "extension=$extension"
        }
    }

    return $options
}

function Ensure-ComposerPhar {
    param([string]$ResolvedPhpBin, [string]$ProjectRoot, [string[]]$PhpRuntimeOptions)

    $composerPharPath = Join-Path $ProjectRoot 'composer.phar'
    if (Test-Path $composerPharPath) { return $composerPharPath }

    $installerPath = Join-Path $ProjectRoot 'composer-setup.php'
    try {
        Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile $installerPath
        & $ResolvedPhpBin @PhpRuntimeOptions $installerPath --install-dir=$ProjectRoot --filename=composer.phar
        if ($LASTEXITCODE -ne 0) { throw 'Composer installer execution failed.' }
    } finally {
        if (Test-Path $installerPath) { Remove-Item $installerPath -Force }
    }

    if (!(Test-Path $composerPharPath)) { throw 'Composer download failed. Set COMPOSER_BIN and rerun this script.' }
    return $composerPharPath
}

function Resolve-ComposerInvocation {
    param([string]$Candidate, [string]$ResolvedPhpBin, [string]$ProjectRoot, [string[]]$PhpRuntimeOptions)

    if ($Candidate) {
        if ($Candidate.ToLowerInvariant().EndsWith('.phar')) {
            return @{ FilePath = $ResolvedPhpBin; ArgumentList = @($Candidate, 'install') }
        }
        return @{ FilePath = $Candidate; ArgumentList = @('install') }
    }

    $projectComposerPhar = Join-Path $ProjectRoot 'composer.phar'
    if (Test-Path $projectComposerPhar) {
        return @{ FilePath = $ResolvedPhpBin; ArgumentList = @($projectComposerPhar, 'install') }
    }

    $composerCommand = Get-Command composer -ErrorAction SilentlyContinue
    if ($composerCommand) {
        return @{ FilePath = $composerCommand.Source; ArgumentList = @('install') }
    }

    $composerPharPath = Ensure-ComposerPhar -ResolvedPhpBin $ResolvedPhpBin -ProjectRoot $ProjectRoot -PhpRuntimeOptions $PhpRuntimeOptions
    return @{ FilePath = $ResolvedPhpBin; ArgumentList = @($composerPharPath, 'install') }
}

function Ensure-CaBundle {
    param([string]$ProjectRoot)

    $certsDir = Join-Path $ProjectRoot 'certs'
    $caBundlePath = Join-Path $certsDir 'cacert.pem'
    if (Test-Path $caBundlePath) { return }

    if (!(Test-Path $certsDir)) { New-Item -ItemType Directory -Path $certsDir | Out-Null }

    try {
        Invoke-WebRequest -Uri 'https://curl.se/ca/cacert.pem' -OutFile $caBundlePath
        Write-Host 'Downloaded certs/cacert.pem.'
    } catch {
        Write-Host 'Could not download certs/cacert.pem. If SSO shows cURL error 60, download it manually from https://curl.se/ca/cacert.pem.'
    }
}

function Write-SsoLocal {
    param(
        [string]$ProjectRoot,
        [string]$BaseUrl,
        [string]$ClientId,
        [string]$ClientSecret,
        [string]$IdpUrl,
        [bool]$ForceWrite
    )

    $ssoLocalPath = Join-Path $ProjectRoot 'php\sso_local.php'
    if ((Test-Path $ssoLocalPath) -and !$ForceWrite) {
        Write-Host 'php/sso_local.php already exists. Use -Force to recreate it.'
        return
    }

    if (!$ClientId) { $ClientId = 'your-ok-core-client-id' }
    if (!$ClientSecret) { $ClientSecret = 'your-ok-core-client-secret' }

    $base = $BaseUrl.TrimEnd('/')
    $redirectUri = $base + '/auth/callback'
    $content = @"
<?php

return array(
    'idp_url' => '$IdpUrl',
    'client_id' => '$ClientId',
    'client_secret' => '$ClientSecret',
    'base_url' => '$base',
    'redirect_uri' => '$redirectUri',
    'scope' => 'openid profile email lab',
);

"@

    Set-Content -Path $ssoLocalPath -Value $content -Encoding UTF8
    Write-Host 'Created php/sso_local.php.'
    Write-Host "Register this redirect URI in HCIMLab SSO: $redirectUri"
}

$resolvedPhpBin = Resolve-PhpBin -Candidate $PhpBin
$phpRuntimeOptions = Get-PhpRuntimeOptions -ResolvedPhpBin $resolvedPhpBin

if (!$SkipComposerInstall) {
    $env:COMPOSER_HOME = Join-Path $rootDir '.composer'
    if (!(Test-Path $env:COMPOSER_HOME)) { New-Item -ItemType Directory -Path $env:COMPOSER_HOME | Out-Null }

    $composerInvocation = Resolve-ComposerInvocation -Candidate $ComposerBin -ResolvedPhpBin $resolvedPhpBin -ProjectRoot $rootDir -PhpRuntimeOptions $phpRuntimeOptions
    if ($composerInvocation.FilePath -eq $resolvedPhpBin) {
        & $composerInvocation.FilePath @phpRuntimeOptions @($composerInvocation.ArgumentList)
    } else {
        & $composerInvocation.FilePath @($composerInvocation.ArgumentList)
    }
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }

    if (!(Test-Path (Join-Path $rootDir 'vendor\autoload.php'))) {
        throw 'Composer install did not create vendor/autoload.php.'
    }
}

Ensure-CaBundle -ProjectRoot $rootDir
Write-SsoLocal -ProjectRoot $rootDir -BaseUrl $BaseUrl -ClientId $ClientId -ClientSecret $ClientSecret -IdpUrl $IdpUrl -ForceWrite:$Force.IsPresent

Write-Host 'OK-Core local setup complete.'

