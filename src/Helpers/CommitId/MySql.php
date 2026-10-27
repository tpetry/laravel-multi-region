<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Helpers\CommitId;

use Illuminate\Database\MySqlConnection;
use RuntimeException;

class MySql
{
    /**
     * Get the ID of the last committed transaction.
     *
     * @throws RuntimeException
     */
    public static function getLastCommitId(MySqlConnection $connection): string
    {
        $gtid = $connection->selectOne('select @@log_bin as log_bin, @@global.gtid_mode as mode, @@global.gtid_executed as executed', useReadPdo: false);

        if (! $gtid->log_bin || $gtid->mode !== 'ON') {
            throw new RuntimeException("The MySQL configuration 'log_bin = ON' and 'gtid_mode = ON' are required to retrieve the last commit ID.");
        }

        return $gtid->executed;
    }

    /**
     * Calculate the commit ID for a set of commit IDs that includes all of them.
     *
     * @param  string[]  $values
     *
     * @throws RuntimeException
     */
    public static function mergeCommitIds(MySqlConnection $connection, array $values): string
    {
        if ($values === []) {
            throw new RuntimeException("The list of commit IDs can't be empty.");
        }

        // For MySQL, commit IDs could be merged simply by joining them. But with the `gtid_subtract` function, many
        // commit IDs can be normalized into one by merging ranges, removing duplicates, etc. So the produced string is
        // not growing infinitely but kept as small as possible.
        return $connection->scalar("select gtid_subtract(?, '')", [implode(',', $values)]);
    }

    /**
     * Determine if the given commit ID has been applied.
     */
    public static function hasCommitId(MySqlConnection $connection, string $value, int $wait = 0): bool
    {
        if ($wait <= 0) {
            return (bool) $connection->scalar('select gtid_subset(?, @@global.gtid_executed)', [$value]);
        }

        return (int) $connection->scalar('select wait_for_executed_gtid_set(?, ?)', [$value, $wait]) === 0;
    }
}
