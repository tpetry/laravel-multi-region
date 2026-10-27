<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Tpetry\MultiRegion\Facades\CommitId;
use Tpetry\MultiRegion\MultiRegionManager;
use Tpetry\MultiRegion\Testing\CommitIdState;

beforeEach(function () {
    config([
        'session.driver' => 'cookie',
        'multiregion.verification.milliseconds' => 60_000,
        'multiregion.wait.seconds' => 0,
        'multiregion.wait.timeout' => 500,
    ]);
});

describe('isPrimary()', function () {
    it('is the primary region without a primary url', function (?string $primaryUrl) {
        config(['multiregion.primary_url' => $primaryUrl]);

        expect((new MultiRegionManager)->isPrimary())
            ->toBeTrue();
    })->with([
        'no url' => [null],
        'empty url' => [''],
    ]);

    it('is not the primary region with a primary url', function () {
        config(['multiregion.primary_url' => 'https://primary.test']);

        expect((new MultiRegionManager)->isPrimary())
            ->toBeFalse();
    });
});

describe('cleanupOldCommitIds()', function () {
    it('removes a commit id after the verification period', function () {
        $this->freezeTime(function (Carbon $now) {
            session([
                'multiregion.commit_id' => 'commit-973',
                'multiregion.saved_at' => $now->getTimestampMs() - config()->integer('multiregion.verification.milliseconds') - 1,
            ]);

            (new MultiRegionManager)->cleanupOldCommitIds();

            expect(session()->has('multiregion'))
                ->toBeFalse();
        });
    });

    it('keeps the commit id within the verification period', function () {
        $this->freezeTime(function (Carbon $now) {
            session([
                'multiregion.commit_id' => 'commit-369',
                'multiregion.saved_at' => $now->getTimestampMs() - config()->integer('multiregion.verification.milliseconds'),
            ]);

            (new MultiRegionManager)->cleanupOldCommitIds();

            expect(session('multiregion'))
                ->commit_id->toBe('commit-369')
                ->saved_at->toBe($now->getTimestampMs() - config()->integer('multiregion.verification.milliseconds'));
        });
    });
});

describe('recordWrite()', function () {
    it('fails when the session driver is not cookie', function (string $driver) {
        config(['session.driver' => $driver]);

        expect(fn () => (new MultiRegionManager)->recordWrite())
            ->toThrow(RuntimeException::class, 'The multi region middleware requires the "cookie" session driver to share the last commit id with all regions.');
    })->with(['array', 'database', 'redis']);

    it('fails when a transaction is still open', function () {
        DB::beginTransaction();

        expect(fn () => (new MultiRegionManager)->recordWrite())
            ->toThrow(RuntimeException::class, 'The multi region middleware can\'t guarantee read-after-write behavior if a database transaction is still open when trying to get the last commit id.');
    });

    it('stores the last commit id in the session', function () {
        CommitId::fake(CommitIdState::CaughtUp);

        $this->freezeTime(function (Carbon $now) {
            (new MultiRegionManager)->recordWrite();

            expect(session('multiregion'))
                ->commit_id->toBe('commit-4')
                ->saved_at->toBe($now->getTimestampMs());
        });
    });

    it('stores the last commit id in the session (merged with existing commit id)', function () {
        CommitId::fake(CommitIdState::CaughtUp);

        $this->freezeTime(function (Carbon $now) {
            session(['multiregion.commit_id' => 'commit-1', 'multiregion.saved_at' => $now->getTimestampMs() - 1_000]);

            (new MultiRegionManager)->recordWrite();

            expect(session('multiregion'))
                ->commit_id->toBe('commit-1,commit-4')
                ->saved_at->toBe($now->getTimestampMs());
        });
    });
});

describe('hasCaughtUp()', function () {
    it('has caught up without a previous write', function () {
        CommitId::fake(CommitIdState::Lagging);

        expect((new MultiRegionManager)->hasCaughtUp())
            ->toBeTrue();

        CommitId::assertHasCommitIdCalled(null);
    });

    it('has caught up after the verification period', function () {
        CommitId::fake(CommitIdState::Lagging);

        $this->freezeTime(function (Carbon $now) {
            session([
                'multiregion.commit_id' => 'commit-407',
                'multiregion.saved_at' => $now->getTimestampMs() - config()->integer('multiregion.verification.milliseconds') - 1,
            ]);

            expect((new MultiRegionManager)->hasCaughtUp())
                ->toBeTrue();
        });

        CommitId::assertHasCommitIdCalled(null);
    });

    it('has caught up when the data has arrived (inside wait timeout)', function () {
        CommitId::fake(CommitIdState::CaughtUp);

        $this->freezeTime(function (Carbon $now) {
            session(['multiregion.commit_id' => 'commit-302', 'multiregion.saved_at' => $now->getTimestampMs()]);

            expect((new MultiRegionManager)->hasCaughtUp())
                ->toBeTrue();
        });

        CommitId::assertHasCommitIdCalled(['commit-302', config()->integer('multiregion.wait.seconds')]);
    });

    it('has caught up when the data has arrived (outside wait timeout)', function () {
        CommitId::fake(CommitIdState::CaughtUp);
        config(['multiregion.wait.seconds' => 2]);

        $this->freezeTime(function (Carbon $now) {
            session(['multiregion.commit_id' => 'commit-440', 'multiregion.saved_at' => $now->getTimestampMs() - config()->integer('multiregion.wait.timeout')]);

            expect((new MultiRegionManager)->hasCaughtUp())
                ->toBeTrue();
        });

        CommitId::assertHasCommitIdCalled(['commit-440', 0]);
    });

    it('has not caught up when the data has not arrived (inside wait timeout)', function () {
        CommitId::fake(CommitIdState::Lagging);

        $this->freezeTime(function (Carbon $now) {
            session(['multiregion.commit_id' => 'commit-655', 'multiregion.saved_at' => $now->getTimestampMs()]);

            expect((new MultiRegionManager)->hasCaughtUp())
                ->toBeFalse();
        });

        CommitId::assertHasCommitIdCalled(['commit-655', config()->integer('multiregion.wait.seconds')]);
    });

    it('has not caught up when the data has not arrived (outside wait timeout)', function () {
        CommitId::fake(CommitIdState::Lagging);
        config(['multiregion.wait.seconds' => 2]);

        $this->freezeTime(function (Carbon $now) {
            session(['multiregion.commit_id' => 'commit-329', 'multiregion.saved_at' => $now->getTimestampMs() - config()->integer('multiregion.wait.timeout')]);

            expect((new MultiRegionManager)->hasCaughtUp())
                ->toBeFalse();
        });

        CommitId::assertHasCommitIdCalled(['commit-329', 0]);
    });
});

describe('createRedirectToPrimaryRegion()', function () {
    it('redirects to the same url in the primary region', function (string $primaryUrl, string $expected) {
        config(['multiregion.primary_url' => $primaryUrl]);
        Request::swap(Request::create('http://replica.test:8080/posts/%C3%A4?tags[]=x&tags[]=y&a=1&a=2&flag&user.name=x&q=a+b'));

        $response = (new MultiRegionManager)->createRedirectToPrimaryRegion();

        expect($response)
            ->getStatusCode()->toBe(307)
            ->getTargetUrl()->toBe($expected);
    })->with([
        'host without slash' => ['https://primary.test', 'https://primary.test/posts/%C3%A4?tags%5B%5D=x&tags%5B%5D=y&a=1&a=2&flag&user.name=x&q=a+b'],
        'host with slash' => ['https://primary.test/', 'https://primary.test/posts/%C3%A4?tags%5B%5D=x&tags%5B%5D=y&a=1&a=2&flag&user.name=x&q=a+b'],
        'host with port' => ['http://primary.test:8080', 'http://primary.test:8080/posts/%C3%A4?tags%5B%5D=x&tags%5B%5D=y&a=1&a=2&flag&user.name=x&q=a+b'],
        'host with path' => ['https://primary.test/ignored', 'https://primary.test/posts/%C3%A4?tags%5B%5D=x&tags%5B%5D=y&a=1&a=2&flag&user.name=x&q=a+b'],
    ]);
});
