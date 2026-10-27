<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Primary Instance URL
    |--------------------------------------------------------------------------
    |
    | The URL of the primary region's instance. Leave it empty in the primary
    | region itself. When it's set, this instance is part of a replica region
    | and forwards requests to the primary region.
    |
    */

    'primary_url' => env('MULTIREGION_PRIMARY_URL'),

    /*
    |--------------------------------------------------------------------------
    | Waiting Time
    |--------------------------------------------------------------------------
    |
    | When the written data hasn't arrived in the replica region yet, the user
    | is redirected to the primary region. But a new request to the primary
    | region usually takes longer than the replication lag, so it is often
    | faster to wait a moment for the data to arrive instead.
    |
    | You can specify an optional maximum number of seconds a request waits
    | for the data. As data is replicated usually within a few milliseconds,
    | one second is more than enough. But when the replication is lagging
    | (e.g. network problem) every read request waits the full time before it
    | is redirected. The application then becomes slow for the user, not just
    | once but on every page view.
    |
    | The timeout prevents this by only waiting up to a number of milliseconds
    | after the primary region has written data. With the default of 500ms,
    | only requests that are made within the first 500ms after a write
    | operation wait for the data. Later ones are redirected immediately.
    |
    */

    'wait' => [
        'seconds' => (int) env('MULTIREGION_WAIT_SECONDS', 0),
        'timeout' => (int) env('MULTIREGION_WAIT_TIMEOUT', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification Duration
    |--------------------------------------------------------------------------
    |
    | After a write operation, a user must see their own changes even though
    | the replica region may not have received them yet. A replica region
    | therefore verifies on every request that the data has arrived. It must
    | do this on every request because when a replica region has multiple
    | database servers, each one of them can have a different replication lag.
    | Marking the data as arrived would only confirm it for one of them, not
    | for the database server the next request is routed to.
    |
    | Verifying costs one tiny additional query on every request in a replica
    | region, which you obviously don't want to run forever. You must
    | therefore define how long after every write operation a replica region
    | should verify the data's existence. Once this duration has passed, the
    | data is expected to have arrived.
    |
    | In rare cases, the data takes very long to arrive, and you don't want to
    | redirect users to the primary region for that long. When the database
    | replication has problems, a replica region may even be unable to ever
    | verify the data. Although it may have arrived. Verifying forever is
    | therefore not recommended. Choose a value well above the worst
    | replication lag you expect.
    |
    */

    'verification' => [
        'milliseconds' => (int) env('MULTIREGION_VERIFICATION_MILLISECONDS', 30_000),
    ],

];
