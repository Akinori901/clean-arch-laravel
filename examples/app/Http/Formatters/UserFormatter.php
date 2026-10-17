<?php

namespace App\Http\Formatters;

/** 配列を返す。JsonResponse は返さない（それは Response の仕事）。 */
final class UserFormatter
{
    /** @return array{id: int, name: string, status: string} */
    public function format(object $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'status' => $user->status];
    }
}
