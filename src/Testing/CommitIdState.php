<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Testing;

enum CommitIdState
{
    /**
     * The database has applied every checked commit ID.
     */
    case CaughtUp;

    /**
     * The database has not yet applied any checked commit ID.
     */
    case Lagging;
}
