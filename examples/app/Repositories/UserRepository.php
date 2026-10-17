<?php

namespace App\Repositories;

use App\DataTransferObjects\UserDto;
use App\Models\User;

/** Model を use してよい唯一の層。返す直前に Dto へ変換する。 */
final class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?UserDto
    {
        $user = User::query()->find($id);
        if ($user === null) {
            return null;
        }

        return new UserDto($user->id, $user->name, $user->status->value);
    }
}
