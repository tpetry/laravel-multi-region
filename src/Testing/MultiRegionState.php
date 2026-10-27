<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Testing;

enum MultiRegionState
{
    /**
     * The instance is part of the primary region.
     */
    case Primary;

    /**
     * The instance is part of a replica region which has received the last write of the user or has no write within the
     * verification period.
     */
    case ReplicaCaughtUp;

    /**
     * The instance is part of a replica region which has not yet received the last write of the user.
     */
    case ReplicaLagging;
}
