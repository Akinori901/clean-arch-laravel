<?php

namespace App\UseCases;

use App\DataTransferObjects\UserDto;
use App\Services\UserService;

/** Service のオーケストレーション。トランザクション境界はここ。 */
final class GetUserUseCase
{
    public function __construct(private UserService $users)
    {
    }

    public function execute(int $id): UserDto
    {
        return $this->users->findActive($id);
    }
}
