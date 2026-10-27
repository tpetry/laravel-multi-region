<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tpetry\MultiRegion\Facades\MultiRegion;
use Tpetry\MultiRegion\Middleware\MultiRegionRead;
use Tpetry\MultiRegion\Testing\MultiRegionState;

beforeEach(function () {
    Route::get('/posts', fn () => 'ok')->middleware(MultiRegionRead::class);
});

describe('primary region', function () {
    it('passes requests through', function () {
        MultiRegion::fake(MultiRegionState::Primary);

        $this->get('/posts')
            ->assertOk()
            ->assertSee('ok');

        MultiRegion::assertOldCommitIdsCleanedUp();
    });
});

describe('replica region', function () {
    beforeEach(function () {
        config(['multiregion.primary_url' => 'https://primary.test']);
    });

    it('passes requests through when the data has arrived', function () {
        MultiRegion::fake(MultiRegionState::ReplicaCaughtUp);

        $this->get('/posts')
            ->assertOk()
            ->assertSee('ok');

        MultiRegion::assertOldCommitIdsCleanedUp();
    });

    it('redirects to the primary region when the data has not arrived', function () {
        MultiRegion::fake(MultiRegionState::ReplicaLagging);

        $this->get('/posts?b=2&a=1')
            ->assertStatus(307)
            ->assertHeader('Location', 'https://primary.test/posts?b=2&a=1');

        MultiRegion::assertOldCommitIdsCleanedUp();
    });
});
