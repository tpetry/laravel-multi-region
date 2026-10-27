<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Helpers\CommitId;

use Illuminate\Database\PostgresConnection;
use RuntimeException;

class Pgsql
{
    /**
     * Get the ID of the last committed transaction.
     */
    public static function getLastCommitId(PostgresConnection $connection): string
    {
        // Some examples recommend using pg_current_wal_insert_lsn(), but it can't be used reliably: When the last WAL
        // record ends exactly at a page boundary, it points behind the header of the next page. This is a WAL position
        // that a replica can't ever reach. The only way to have a replica acknowledging to have received this WAL is
        // when more WAL records are written and that impossible position is superseded by new WAL blocks. So a simple
        // read-after-write couldn't work until some other connection also commits a transaction.
        return $connection->scalar('select pg_current_wal_lsn()', useReadPdo: false);
    }

    /**
     * Calculate the commit ID for a set of commit IDs that includes all of them.
     *
     * @param  string[]  $values
     *
     * @throws RuntimeException
     */
    public static function mergeCommitIds(PostgresConnection $connection, array $values): string
    {
        if ($values === []) {
            throw new RuntimeException("The list of commit IDs can't be empty.");
        }

        // LSNs are linear, so the newest one includes all older ones.
        return $connection->scalar('select value::pg_lsn from json_array_elements_text(?) order by 1 desc limit 1', [json_encode($values)]);
    }

    /**
     * Determine if the given commit ID has been applied.
     *
     * @throws RuntimeException
     */
    public static function hasCommitId(PostgresConnection $connection, string $value, int $wait = 0): bool
    {
        if ($wait > 0) {
            if (version_compare($connection->getServerVersion(), '19', '<')) {
                throw new RuntimeException('The PostgreSQL version does not support waiting for a commit ID. PostgreSQL 19 or newer is required.');
            }

            $status = $connection->scalar("wait for lsn {$connection->escape($value)} with (timeout {$connection->escape("{$wait}s")}, no_throw)");

            // A primary can't wait for its own LSNs, the normal wait = 0 check must be used.
            if ($status !== 'not in recovery') {
                return $status === 'success';
            }
        }

        return (bool) $connection->scalar('select case when pg_is_in_recovery() then pg_last_wal_replay_lsn() else pg_current_wal_insert_lsn() end >= ?::pg_lsn', [$value]);
    }
}
