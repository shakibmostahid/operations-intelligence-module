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
            return $this->rejectLogin(
                $request,
                'Your password must be changed before you can sign in. Please contact support.',
            );
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function rejectLogin(Request $request, string $message): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}
