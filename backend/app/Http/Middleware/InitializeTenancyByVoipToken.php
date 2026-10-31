<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\VoipIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Picks the tenant from the call-center token ("dg_<tenant>.<secret>"), whatever the Host is. */
class InitializeTenancyByVoipToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = VoipIntegration::tenantIdFrom((string) $request->bearerToken());
        $tenant = $id === null ? null : Tenant::find($id);
        if (! $tenant) {
            \Log::warning('voip api: unknown token');
            abort(401, 'توکن نامعتبر است');
        }
        tenancy()->initialize($tenant);

        return $next($request);
    }
}
