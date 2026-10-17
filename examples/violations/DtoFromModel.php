<?php
// 違反例 2: Dto に fromModel() を置く（Dto は Model に依存しない末端ノード）
// 期待: App\DataTransferObjects\... must not depend on App\Models\User (Model)

namespace App\DataTransferObjects;

use App\Models\User;

final readonly class ViolatingUserDto
{
    public function __construct(public int $id)
    {
    }

    public static function fromModel(User $user): self
    {
        return new self($user->id);
    }
}
