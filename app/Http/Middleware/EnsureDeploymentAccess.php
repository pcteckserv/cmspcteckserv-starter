<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pcteckserv\CmsCore\Services\Auth\PendingTwoFactorLogin;
use Pcteckserv\CmsCore\Services\Auth\TwoFactorManager;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeploymentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->isCmsSuperAdmin(), 403);
        // Versões anteriores do Core ainda não disponibilizam autenticação de dois fatores.
        $factor = class_exists(TwoFactorManager::class) ? app(TwoFactorManager::class)->enabled($user) : null;
        if ($factor && ! hash_equals($factor->version, (string) $request->session()->get(TwoFactorManager::VERIFIED, ''))) {
            Auth::guard('web')->logout();
            app(PendingTwoFactorLogin::class)->begin($request, $user, false);

            return redirect()->route('two-factor.challenge');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
