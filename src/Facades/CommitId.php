<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Facades;

use Illuminate\Support\Facades\Facade;
use Tpetry\MultiRegion\Helpers\CommitId as CommitIdHelper;
use Tpetry\MultiRegion\Testing\CommitIdFake;
use Tpetry\MultiRegion\Testing\CommitIdState;

/**
 * @method static string getLastCommitId(\Illuminate\Database\Connection $connection)
 * @method static string mergeCommitIds(\Illuminate\Database\Connection $connection, string[] $values)
 * @method static bool hasCommitId(\Illuminate\Database\Connection $connection, string $value, int $wait = 0)
 * @method static void assertHasCommitIdCalled(array{string, int}|null $arguments)
 *
 * @see CommitIdHelper
 * @see CommitIdFake
 */
class CommitId extends Facade
{
    public static function fake(CommitIdState $state): CommitIdFake
    {
        return tap(new CommitIdFake($state), function (CommitIdFake $fake) {
            static::swap($fake);
        });
    }

    protected static function getFacadeAccessor(): string
    {
        return CommitIdHelper::class;
    }
}
