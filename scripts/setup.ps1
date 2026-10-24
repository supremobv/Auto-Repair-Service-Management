param([switch]$Demo)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
& composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { throw 'Composer installation failed.' }
$workshopEnv = Get-Content -LiteralPath '.env' -Raw
if ($workshopEnv -notmatch '(?m)^APP_KEY=base64:') { & php artisan key:generate; if ($LASTEXITCODE -ne 0) { throw 'Key generation failed.' } }
& php artisan workshop:create-local-database
if ($LASTEXITCODE -ne 0) { throw 'Database connection failed. Check Laragon and your .env database settings.' }
& php artisan migrate --seed --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Migrations failed.' }
if (-not (Test-Path -LiteralPath 'public/storage')) { & php artisan storage:link }
if ($Demo) {
    & php artisan db:seed --class=DemoSeeder --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Demo seeding failed. It requires an empty local workshop.' }
}
Write-Host 'Setup complete. Run composer dev, then visit http://127.0.0.1:8000'
Write-Host 'Without demo data, create your admin: php artisan workshop:admin you@example.com'
