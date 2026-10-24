<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get('database.connections.'.$connection.'.database');
        if ($database !== ':memory:' && ! str_ends_with((string) $database, '_test')) {
            throw new \RuntimeException('Tests may only use :memory: or a database ending in _test. No workshop data was changed.');
        }

        return $app;
    }
}
