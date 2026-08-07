<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $status = tenant()?->status ?? 'active';

        abort_if($status !== 'active', 423, 'این سیستم غیرفعال است.');

        return $next($request);
    }
}
