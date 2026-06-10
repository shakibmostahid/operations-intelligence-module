<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    public function paginatedUsers(
        User $actor,
        int $perPage = 15,
        ?string $search = null,
    ): LengthAwarePaginator
    {
        $actor->loadMissing('role');

        return User::query()
            ->with(['role:id,role', 'creator:id,name'])
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->role,
                'status' => $user->status,
                'created_by' => $user->creator?->name,
                'created_at' => $user->created_at?->format('M j, Y'),
                'can_deactivate' => $this->canDeactivate($actor, $user),
                'can_reactivate' => $this->canReactivate($actor, $user),
            ]);
    }

    public function assignableRoles(User $actor): Collection
    {
        $actor->loadMissing('role');

        $allowedRoles = $actor->role->role === 'super_admin'
            ? ['admin', 'support_engineer', 'viewer']
            : ['support_engineer', 'viewer'];

        return Role::query()
            ->whereIn('role', $allowedRoles)
            ->orderBy('role')
            ->get(['id', 'role']);
    }

    /**
     * @param  array{name: string, email: string, role_id: int|string}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function createUser(User $actor, array $data): array
    {
        $assignableRoleIds = $this->assignableRoles($actor)->modelKeys();
        $data['role_id'] = (int) $data['role_id'];

        if (! in_array($data['role_id'], $assignableRoleIds, true)) {
            throw new AuthorizationException;
        }

        $temporaryPassword = Str::password(14);
        $user = User::query()->create([
            ...$data,
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'email_verified_at' => now(),
            'status' => 'active',
            'created_by' => $actor->id,
        ]);

        return [
            'user' => $user,
            'temporary_password' => $temporaryPassword,
        ];
    }

    /**
     * @throws AuthorizationException
     */
    public function deactivateUser(User $actor, User $target): void
    {
        $actor->loadMissing('role');
        $target->loadMissing('role');

        if (! $this->canDeactivate($actor, $target)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($target): void {
            $target->update(['status' => 'inactive']);

            DB::table('sessions')
                ->where('user_id', $target->id)
                ->delete();
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function reactivateUser(User $actor, User $target): void
    {
        $actor->loadMissing('role');
        $target->loadMissing('role');

        if (! $this->canReactivate($actor, $target)) {
            throw new AuthorizationException;
        }

        $target->update(['status' => 'active']);
    }

    private function canDeactivate(User $actor, User $target): bool
    {
        if ($target->status !== 'active' || $actor->is($target)) {
            return false;
        }

        return $this->canManageTarget($actor, $target);
    }

    private function canReactivate(User $actor, User $target): bool
    {
        return $target->status === 'inactive'
            && $this->canManageTarget($actor, $target);
    }

    private function canManageTarget(User $actor, User $target): bool
    {
        if ($target->role->role === 'super_admin') {
            return false;
        }

        return $actor->role->role === 'super_admin'
            || ! in_array($target->role->role, ['super_admin', 'admin'], true);
    }
}
