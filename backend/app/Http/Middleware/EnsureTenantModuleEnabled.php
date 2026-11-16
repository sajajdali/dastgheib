<?php

namespace App\Http\Middleware;

use App\Services\ModuleSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('tenant.module:group_chat') */
class EnsureTenantModuleEnabled
{
    public function __construct(private readonly ModuleSubscriptionService $modules)
    {
    }

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! $this->modules->tenantHasModule(tenant(), $moduleKey)) {
            return response()->json([
                'message' => 'این ماژول برای کلینیک شما فعال نیست.',
                'code' => 'module_disabled',
                'module' => $moduleKey,
            ], 403);
        }

        return $next($request);
    }
}
