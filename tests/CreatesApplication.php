<?php

namespace Tests;

use Illuminate\Foundation\Application;

trait CreatesApplication
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__.'/../bootstrap/app.php';

        \Illuminate\Container\Container::setInstance($app);

        return $app;
    }
}
