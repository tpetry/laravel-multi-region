<?php

declare(strict_types=1);

namespace Tpetry\MultiRegion\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tpetry\MultiRegion\Facades\MultiRegion;

class MultiRegionWrite
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        MultiRegion::cleanupOldCommitIds();

        if (! MultiRegion::isPrimary()) {
            return MultiRegion::createRedirectToPrimaryRegion();
        }

        try {
            $response = $next($request);
        } finally {
            MultiRegion::recordWrite();
        }

        return $response;
    }
}
