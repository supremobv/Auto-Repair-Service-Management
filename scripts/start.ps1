$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& php artisan serve --host=127.0.0.1 --port=8000
