<?php

namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property UserStatus $status
 */
class User extends Model
{
    protected $casts = ['status' => UserStatus::class];
}
