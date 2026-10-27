<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Testing;

use Illuminate\Database\Connection;
use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert as PHPUnit;
use Tpetry\MultiRegion\Helpers\CommitId;

class CommitIdFake extends CommitId implements Fake
{
    /**
     * The arguments passed to every hasCommitId() call.
     *
     * @var list<array{string, int}>
     */
    protected array $caughtUpCalled = [];

    protected bool $hasCommitId;

    public function __construct(CommitIdState $state)
    {
        $this->hasCommitId = match ($state) {
            CommitIdState::CaughtUp => true,
            CommitIdState::Lagging => false,
        };
    }

    public function getLastCommitId(Connection $connection): string
    {
        return 'commit-4'; // https://xkcd.com/221/
    }

    /**
     * @param  string[]  $values
     */
    public function mergeCommitIds(Connection $connection, array $values): string
    {
        sort($values);

        return implode(',', $values);
    }

    public function hasCommitId(Connection $connection, string $value, int $wait = 0): bool
    {
        $this->caughtUpCalled[] = [$value, $wait];

        return $this->hasCommitId;
    }

    /**
     * Assert that hasCommitId() was called with the commit ID and wait time, or never called when null is passed.
     *
     * @param  array{string, int}|null  $arguments
     */
    public function assertHasCommitIdCalled(?array $arguments): void
    {
        match ($arguments) {
            null => PHPUnit::assertSame([], $this->caughtUpCalled, 'hasCommitId() was called unexpectedly.'),
            default => PHPUnit::assertContains($arguments, $this->caughtUpCalled, "hasCommitId() was not called with the commit ID [{$arguments[0]}] and a wait of {$arguments[1]} seconds."),
        };
    }
}
