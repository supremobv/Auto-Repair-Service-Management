$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$priorWorkshopDatabase = $env:DB_DATABASE
try {
    $env:DB_DATABASE = 'torque_workshop_test'
    & php artisan workshop:create-local-database
    if ($LASTEXITCODE -ne 0) { throw 'Could not create isolated MySQL test database.' }
} finally {
    if ($null -eq $priorWorkshopDatabase) { Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue }
    else { $env:DB_DATABASE = $priorWorkshopDatabase }
}
& php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml
if ($LASTEXITCODE -ne 0) { throw 'MySQL tests failed.' }
