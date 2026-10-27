<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Helpers;

use Illuminate\Database\Connection;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use RuntimeException;
use Tpetry\MultiRegion\Helpers\CommitId\MariaDb;
use Tpetry\MultiRegion\Helpers\CommitId\MySql;
use Tpetry\MultiRegion\Helpers\CommitId\Pgsql;

/**
 * Commit ID support for database connections until laravel/framework provides it natively.
 */
class CommitId
{
    /**
     * Get the ID of the last committed transaction.
     *
     * @throws RuntimeException
     */
    public function getLastCommitId(Connection $connection): string
    {
        // MariaDbConnection extends MySqlConnection, so it has to be checked first.
        return match (true) {
            $connection instanceof MariaDbConnection => MariaDb::getLastCommitId($connection),
            $connection instanceof MySqlConnection => MySql::getLastCommitId($connection),
            $connection instanceof PostgresConnection => Pgsql::getLastCommitId($connection),
            default => throw new RuntimeException('The database connection does not support retrieving the last commit ID.'),
        };
    }

    /**
     * Calculate the commit ID for a set of commit IDs that includes all of them.
     *
     * @param  string[]  $values
     *
     * @throws RuntimeException
     */
    public function mergeCommitIds(Connection $connection, array $values): string
    {
        return match (true) {
            $connection instanceof MariaDbConnection => MariaDb::mergeCommitIds($values),
            $connection instanceof MySqlConnection => MySql::mergeCommitIds($connection, $values),
            $connection instanceof PostgresConnection => Pgsql::mergeCommitIds($connection, $values),
            default => throw new RuntimeException('The database connection does not support managing commit IDs.'),
        };
    }

    /**
     * Determine if the given commit ID has been applied.
     *
     * @throws RuntimeException
     */
    public function hasCommitId(Connection $connection, string $value, int $wait = 0): bool
    {
        return match (true) {
            $connection instanceof MariaDbConnection => MariaDb::hasCommitId($connection, $value, $wait),
            $connection instanceof MySqlConnection => MySql::hasCommitId($connection, $value, $wait),
            $connection instanceof PostgresConnection => Pgsql::hasCommitId($connection, $value, $wait),
            default => throw new RuntimeException('The database connection does not support checking a commit ID.'),
        };
    }
}
