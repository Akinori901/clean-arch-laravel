<?php

namespace App\Helpers;

/** Model / DB に依存しない純粋関数。 */
final class NameHelper
{
    public static function display(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }
}
