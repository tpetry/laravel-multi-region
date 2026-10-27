<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tpetry\MultiRegion\MultiRegionServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MultiRegionServiceProvider::class,
        ];
    }
}
