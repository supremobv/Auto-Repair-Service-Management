<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rules\Password;

Artisan::command('workshop:admin {email} {--name=Administrator}', function () {
    $email = $this->argument('email');
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Enter a valid email.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('That account already exists. Use Team & access to manage it.');

        return 1;
    }
    $password = $this->secret('Temporary password (12+ characters, upper/lowercase, number, symbol)');
    $validator = validator(['password' => $password], ['password' => [Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'admin', 'active' => true, 'must_change_password' => true]);
    $this->info('Administrator created. Change the temporary password on first login.');
})->purpose('Create an administrator without putting a password in shell history');

Artisan::command('workshop:create-local-database', function () {
    if (! app()->environment('local')) {
        $this->error('Only allowed in local environment.');

        return 1;
    }
    $config = config('database.connections.mysql');
    $name = $config['database'];
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        $this->error('Use only letters, digits and underscores in the database name.');

        return 1;
    }
    if (! in_array($config['host'], ['127.0.0.1', 'localhost'])) {
        $this->error('Local database host required.');

        return 1;
    }
    $pdo = new PDO('mysql:host='.$config['host'].';port='.$config['port'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $this->info('Local MySQL database is ready: '.$name);
})->purpose('Create the configured local MySQL database if it does not exist');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
