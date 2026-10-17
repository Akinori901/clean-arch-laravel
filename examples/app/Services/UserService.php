<?php

namespace App\Services;

use App\DataTransferObjects\UserDto;
use App\Enums\UserStatus;
use App\Exceptions\UserNotFoundException;
use App\Helpers\NameHelper;
use App\Repositories\UserRepositoryInterface;

/** ビジネスロジック。Repository は契約（Interface）経由でだけ使う。 */
final class UserService
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function findActive(int $id): UserDto
    {
        $user = $this->users->findById($id);
        if ($user === null || $user->status !== UserStatus::Active->value) {
            throw new UserNotFoundException("user {$id} not found");
        }

        return new UserDto($user->id, NameHelper::display($user->name), $user->status);
    }
}
