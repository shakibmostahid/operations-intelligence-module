<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function __construct(private readonly ProfileService $profileService)
    {
    }

    public function edit(Request $request): View
    {
        return view('app', [
            'page' => 'password-change',
            'props' => [
                'user' => $request->user()->only('name', 'email'),
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $this->profileService->updatePassword($request->user(), $validated['password']);

        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed successfully.');
    }
}
