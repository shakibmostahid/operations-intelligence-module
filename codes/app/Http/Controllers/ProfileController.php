<?php

namespace App\Http\Controllers;

use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService)
    {
    }

    public function edit(Request $request): View
    {
        $user = $request->user()->load('role');

        return view('app', [
            'page' => 'profile',
            'props' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->role,
                ],
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
                'success' => session('success'),
            ],
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->profileService->updateName($request->user(), $validated['name']);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profile name updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
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
            ->route('profile.edit')
            ->with('success', 'Password updated successfully.');
    }
}
