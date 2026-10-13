<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SatisfactionInvitation;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SatisfactionIntegrationController extends Controller
{
    public function options(Request $request, string $token): Response
    {
        $invite = $this->externalInvitation($token);
        $origin = $this->origin($invite->delivery_url);
        abort_unless($origin !== '' && hash_equals($origin, (string) $request->header('Origin')), 403);

        return $this->withCors(response()->noContent(), $request, $invite);
    }

    public function show(Request $request, string $token, SatisfactionController $satisfaction): Response
    {
        $invite = $this->externalInvitation($token);
        try {
            $response = $satisfaction->show($request, $token);
        } catch (\Throwable $error) {
            $response = app(ExceptionHandler::class)->render($request, $error);
        }

        return $this->withCors($response, $request, $invite);
    }

    public function submit(Request $request, string $token, SatisfactionController $satisfaction): Response
    {
        $invite = $this->externalInvitation($token);
        try {
            $response = $satisfaction->submit($request, $token);
        } catch (\Throwable $error) {
            $response = app(ExceptionHandler::class)->render($request, $error);
        }

        return $this->withCors($response, $request, $invite);
    }

    private function externalInvitation(string $token): SatisfactionInvitation
    {
        $invite = SatisfactionInvitation::where('token', $token)->firstOrFail();
        abort_unless($invite->delivery_mode === 'external', 404);

        return $invite;
    }

    private function origin(?string $url): string
    {
        $parts = parse_url((string) $url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return '';

        return strtolower($parts['scheme']).'://'.strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function withCors(Response $response, Request $request, SatisfactionInvitation $invite): Response
    {
        $origin = $this->origin($invite->delivery_url);
        $response->headers->set('Vary', 'Origin');
        if ($origin !== '' && hash_equals($origin, (string) $request->header('Origin'))) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
            $response->headers->set('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
