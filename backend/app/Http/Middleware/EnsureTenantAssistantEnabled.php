<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAssistantEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenant()?->assistantEnabled()) {
            return response()->json(['message' => 'دستیار هوش مصنوعی برای این سایت فعال نیست.'], 403);
        }

        return $next($request);
    }
}
