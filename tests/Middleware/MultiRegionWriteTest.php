<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tpetry\MultiRegion\Facades\MultiRegion;
use Tpetry\MultiRegion\Middleware\MultiRegionWrite;
use Tpetry\MultiRegion\Testing\MultiRegionState;

beforeEach(function () {
    Route::post('/posts', fn () => 'ok')->middleware(MultiRegionWrite::class);
});

describe('primary region', function () {
    it('passes requests through and records the write', function () {
        MultiRegion::fake(MultiRegionState::Primary);

        $this->post('/posts')
            ->assertOk()
            ->assertSee('ok');

        MultiRegion::assertOldCommitIdsCleanedUp();
        MultiRegion::assertWriteRecorded(true);
    });

    it('records the write for failed requests', function () {
        MultiRegion::fake(MultiRegionState::Primary);
        Route::post('/failing', fn () => throw new RuntimeException('failed'))->middleware(MultiRegionWrite::class);

        $this->post('/failing')
            ->assertServerError();

        MultiRegion::assertOldCommitIdsCleanedUp();
        MultiRegion::assertWriteRecorded(true);
    });
});

describe('replica region', function () {
    beforeEach(function () {
        config(['multiregion.primary_url' => 'https://primary.test']);
    });

    it('redirects to the primary region', function () {
        MultiRegion::fake(MultiRegionState::ReplicaCaughtUp);

        $this->post('/posts?b=2&a=1')
            ->assertStatus(307)
            ->assertHeader('Location', 'https://primary.test/posts?b=2&a=1');

        MultiRegion::assertOldCommitIdsCleanedUp();
        MultiRegion::assertWriteRecorded(false);
    });
});
