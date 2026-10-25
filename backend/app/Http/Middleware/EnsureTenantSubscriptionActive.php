<?php

namespace App\Http\Middleware;

use App\Services\TenantSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function __construct(private readonly TenantSubscriptionService $subscriptions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isRecoveryRoute($request)) {
            return $next($request);
        }

        if ($this->subscriptions->isExpired(tenant())) {
            $subscription = $this->subscriptions->statusData(tenant());
            return response()->json([
                'message' => 'اشتراک این مجموعه به پایان رسیده است. برای ادامه، اشتراک را تمدید کنید.',
                'code' => 'subscription_expired',
                'subscription' => $subscription,
            ], 402);
        }

        return $next($request);
    }

    private function isRecoveryRoute(Request $request): bool
    {
        return ($request->is('api/auth/user') && $request->isMethod('GET'))
            || ($request->is('logout') && $request->isMethod('POST'))
            || $request->is('api/subscription', 'api/subscription/*')
            || $request->is('api/store/terms')
            || $request->is('api/service-tickets', 'api/service-tickets/*');
    }
}
