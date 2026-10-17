<?php
// 違反例 1: Service が Model を直接触る（Model を触れるのは Repository だけ）
// 期待: App\Services\... must not depend on App\Models\User (Model)

namespace App\Services;

use App\Models\User;

final class ViolatingUserLookup
{
    public function name(int $id): string
    {
        return User::query()->findOrFail($id)->name;
    }
}
