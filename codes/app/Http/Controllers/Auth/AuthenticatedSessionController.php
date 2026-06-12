<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('app', [
            'page' => 'login',
            'props' => [
                'email' => old('email'),
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'The provided credentials do not match our records.']);
        }

        $user = $request->user();

        if ($user->status !== 'active') {
            return $this->rejectLogin(
                $request,
                'Your account is inactive. Please contact support.',
            );
        }

        if ($user->must_change_password) {
            $request->session()->regenerate();

            return redirect()->to(route('password.change', absolute: false));
        }

        $request->session()->regenerate();

        $request->session()->forget('url.intended');

        return redirect()->to(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to(route('login', absolute: false))
            ->withCookie(cookie()->forget('live_health_enabled'));
    }

    private function rejectLogin(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->to(route('login', absolute: false))
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}
