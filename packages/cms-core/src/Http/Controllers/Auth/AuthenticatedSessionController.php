<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('cms-core::auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'As credenciais indicadas não correspondem aos nossos registos.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if (method_exists($user, 'isCmsActive') && ! $user->isCmsActive()) {
            Auth::guard('web')->logout();

            return back()
                ->withErrors(['email' => 'Esta conta encontra-se inativa.'])
                ->onlyInput('email');
        }

        if (method_exists($user, 'cmsState')) {
            $user->cmsState()->updateOrCreate([], [
                'state' => method_exists($user, 'cmsAccessState') ? $user->cmsAccessState() : 'active',
                'last_login_at' => now(),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(config('cms-core.auth.logout_redirect', '/'));
    }
}
