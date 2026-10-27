<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Facades;

use Illuminate\Support\Facades\Facade;
use Tpetry\MultiRegion\MultiRegionManager;
use Tpetry\MultiRegion\Testing\MultiRegionFake;
use Tpetry\MultiRegion\Testing\MultiRegionState;

/**
 * @method static bool isPrimary()
 * @method static void cleanupOldCommitIds()
 * @method static bool hasCaughtUp()
 * @method static void recordWrite()
 * @method static \Illuminate\Http\RedirectResponse createRedirectToPrimaryRegion()
 * @method static void assertWriteRecorded(bool $value)
 * @method static void assertOldCommitIdsCleanedUp()
 *
 * @see MultiRegionManager
 * @see MultiRegionFake
 */
class MultiRegion extends Facade
{
    public static function fake(MultiRegionState $state): MultiRegionFake
    {
        return tap(new MultiRegionFake($state), function (MultiRegionFake $fake) {
            static::swap($fake);
        });
    }

    protected static function getFacadeAccessor(): string
    {
        return MultiRegionManager::class;
    }
}
