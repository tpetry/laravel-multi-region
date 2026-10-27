<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Testing;

use Illuminate\Support\Testing\Fakes\Fake;
use PHPUnit\Framework\Assert as PHPUnit;
use Tpetry\MultiRegion\MultiRegionManager;

class MultiRegionFake extends MultiRegionManager implements Fake
{
    protected bool $calledRecordWrite = false;

    protected bool $calledCleanupOldCommitIds = false;

    protected bool $isPrimary;

    protected bool $hasCaughtUp;

    public function __construct(MultiRegionState $state = MultiRegionState::Primary)
    {
        [$this->isPrimary, $this->hasCaughtUp] = match ($state) {
            MultiRegionState::Primary => [true, true],
            MultiRegionState::ReplicaCaughtUp => [false, true],
            MultiRegionState::ReplicaLagging => [false, false],
        };
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function cleanupOldCommitIds(): void
    {
        $this->calledCleanupOldCommitIds = true;
    }

    public function recordWrite(): void
    {
        $this->calledRecordWrite = true;
    }

    public function hasCaughtUp(): bool
    {
        return $this->hasCaughtUp;
    }

    public function assertWriteRecorded(bool $value): void
    {
        PHPUnit::assertSame($value, $this->calledRecordWrite, match ($value) {
            true => 'No write operation was recorded.',
            false => 'A write operation was recorded unexpectedly.',
        });
    }

    public function assertOldCommitIdsCleanedUp(): void
    {
        PHPUnit::assertTrue($this->calledCleanupOldCommitIds, 'The old commit IDs were not cleaned up.');
    }
}
