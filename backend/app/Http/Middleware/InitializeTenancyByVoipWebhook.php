<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByVoipWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::find((string) $request->route('tenantId'));
        abort_unless($tenant, 404);

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
