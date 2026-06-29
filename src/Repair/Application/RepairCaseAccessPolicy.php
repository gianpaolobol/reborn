<?php

declare(strict_types=1);

namespace Reborn\Repair\Application;

use Reborn\Identity\Domain\User;
use Reborn\Repair\Domain\RepairCase;

final class RepairCaseAccessPolicy
{
    public function canCreate(User $user): bool
    {
        return $user->hasAnyRole([
            User::ROLE_REPAIR_USER,
            User::ROLE_ENTERPRISE,
            User::ROLE_ADMIN,
        ]);
    }

    public function canView(User $user, RepairCase $case): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if ($case->ownerId !== null && $case->ownerId === $user->id) {
            return true;
        }

        return $user->hasAnyRole([
            User::ROLE_MAKER,
            User::ROLE_PROVIDER,
            User::ROLE_ENTERPRISE,
        ]);
    }

    public function canMutate(User $user, RepairCase $case): bool
    {
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        return $case->ownerId !== null && $case->ownerId === $user->id;
    }

    public function ownerScopeForList(User $user): ?string
    {
        return $user->role === User::ROLE_REPAIR_USER ? $user->id : null;
    }
}
