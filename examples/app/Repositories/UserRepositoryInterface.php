<?php

namespace App\Repositories;

use App\DataTransferObjects\UserDto;

/** 契約は Model ではなく Dto を返す。 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?UserDto;
}
