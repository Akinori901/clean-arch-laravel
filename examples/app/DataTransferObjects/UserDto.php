<?php

namespace App\DataTransferObjects;

/**
 * Dto は末端ノード。Model にも Enum にも依存しない（fromModel() を置かない）。
 * Model からの変換は Repository の中で行う。
 */
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $status,
    ) {
    }
}
