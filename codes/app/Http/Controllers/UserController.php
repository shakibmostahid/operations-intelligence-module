<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $actor = $request->user()->load('role');
        $perPage = $request->integer('per_page', 10);
        $search = trim($validated['search'] ?? '');

        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        return view('app', [
            'page' => 'user-list',
            'props' => [
                'user' => $this->authenticatedUser($actor),
                'users' => $this->userService->paginatedUsers($actor, $perPage, $search),
                'perPage' => $perPage,
                'search' => $search,
                'success' => session('success'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user()->load('role');

        return view('app', [
            'page' => 'user-create',
            'props' => [
                'user' => $this->authenticatedUser($user),
                'roles' => $this->userService->assignableRoles($user),
                'errors' => session('errors')?->getBag('default')->toArray() ?? [],
                'old' => old(),
                'createdUser' => session('created_user'),
                'temporaryPassword' => $this->temporaryPassword(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $assignableRoleIds = $this->userService
            ->assignableRoles($request->user())
            ->modelKeys();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', Rule::in($assignableRoleIds)],
        ]);

        $result = $this->userService->createUser($request->user(), $validated);

        return redirect()
            ->to(route('users.create', absolute: false))
            ->with('created_user', $result['user']->only('name', 'email'))
            ->with('temporary_password', Crypt::encryptString($result['temporary_password']));
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->userService->deactivateUser($request->user(), $user);

        return redirect()
            ->back()
            ->with('success', "{$user->name} has been deactivated.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->userService->reactivateUser($request->user(), $user);

        return redirect()
            ->back()
            ->with('success', "{$user->name} has been reactivated.");
    }

    private function temporaryPassword(): ?string
    {
        $password = session('temporary_password');

        return is_string($password) ? Crypt::decryptString($password) : null;
    }

    private function authenticatedUser(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->role,
        ];
    }
}
