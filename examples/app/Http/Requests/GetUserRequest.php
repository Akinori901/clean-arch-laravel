<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GetUserRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'min:1']];
    }
}
