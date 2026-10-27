<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Helpers\CommitId;

use Brick\Math\BigInteger;
use Illuminate\Database\MariaDbConnection;
use RuntimeException;

class MariaDb
{
    /**
     * Get the ID of the last committed transaction.
     *
     * @throws RuntimeException
     */
    public static function getLastCommitId(MariaDbConnection $connection): string
    {
        $gtid = $connection->selectOne('select @@log_bin as log_bin, @@gtid_binlog_pos as gtid_binlog_pos', useReadPdo: false);

        if (! $gtid->log_bin) {
            throw new RuntimeException("The MariaDB configuration 'log_bin = ON' is required to retrieve the last commit ID.");
        }

        return $gtid->gtid_binlog_pos;
    }

    /**
     * Calculate the commit ID for a set of commit IDs that includes all of them.
     *
     * @param  string[]  $values
     *
     * @throws RuntimeException
     */
    public static function mergeCommitIds(array $values): string
    {
        if ($values === []) {
            throw new RuntimeException("The list of commit IDs can't be empty.");
        }

        return implode(',', array_keys(self::parseCommitIds($values)));
    }

    /**
     * Determine if the given commit ID has been applied.
     *
     * @throws RuntimeException
     */
    public static function hasCommitId(MariaDbConnection $connection, string $value, int $wait = 0): bool
    {
        // MASTER_GTID_WAIT only checks the GTIDs this server received through replication (@@gtid_slave_pos), so it
        // can't find GTIDs the server wrote itself: on a primary, or on a server mixing replicated and own writes (e.g.
        // multi-source replication) a wait would wait for their replicas having the commit applied. That's not what we
        // want here. So a check first with zero wait is used, and @@gtid_current_pos which combines replicated and own
        // GTIDs as a fallback is loaded for a local check on primary servers.
        $check = $connection->selectOne('select master_gtid_wait(?, 0) as master_gtid_wait, @@gtid_current_pos as gtid_current_pos', [$value]);

        if ((int) $check->master_gtid_wait === 0) {
            return true;
        }

        $expected = array_column(self::parseCommitIds([$value]), 'sequence', 'domain');
        foreach (self::parseCommitIds([$check->gtid_current_pos]) as $gtid) {
            if (isset($expected[$gtid['domain']]) && $gtid['sequence']->isGreaterThanOrEqualTo($expected[$gtid['domain']])) {
                unset($expected[$gtid['domain']]);
            }
        }

        if ($expected === [] || $wait <= 0) {
            return $expected === [];
        }

        return (int) $connection->scalar('select master_gtid_wait(?, ?)', [$value, $wait]) === 0;
    }

    /**
     * Parse a set of commit IDs (GTIDs) into the newest GTID of every MariaDB replication domain.
     *
     * Every GTID has the structure "{domain}-{server}-{sequence}" and every commit ID can be a comma-separated list
     * of GTIDs. But MariaDB only compares the sequence within a domain, so older GTIDs of a domain are dropped.
     *
     * @param  string[]  $values
     * @return array<string, array{domain: string, sequence: BigInteger}>
     *
     * @throws RuntimeException
     */
    protected static function parseCommitIds(array $values): array
    {
        $gtids = $parsed = [];
        foreach (explode(',', implode(',', $values)) as $gtid) {
            $gtid = trim($gtid);

            if ($gtid === '') {
                continue;
            }

            if (! preg_match('/^(?<domain>\d+)-(?<server>\d+)-(?<sequence>\d+)$/', $gtid, $matches)) {
                throw new RuntimeException("The commit ID '{$gtid}' is not a valid MariaDB GTID.");
            }

            $domain = $matches['domain'];
            $sequence = BigInteger::of($matches['sequence']); // The sequence is a 64-bit unsigned integer and can exceed PHP's signed integers.

            if (! isset($parsed[$domain]) || $sequence->isGreaterThan($parsed[$domain]['sequence'])) {
                $gtids[$domain] = $gtid;
                $parsed[$domain] = compact('domain', 'sequence');
            }
        }
        ksort($gtids);
        ksort($parsed);

        return array_combine($gtids, $parsed);
    }
}
