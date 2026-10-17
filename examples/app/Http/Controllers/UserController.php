<?php

namespace App\Http\Controllers;

use App\Exceptions\UserNotFoundException;
use App\Http\Formatters\UserFormatter;
use App\Http\Requests\GetUserRequest;
use App\Http\Responses\JsonResponder;
use App\UseCases\GetUserUseCase;
use Illuminate\Http\JsonResponse;

/** UseCase へ渡す・例外を catch する・出し分ける、だけ。 */
final class UserController
{
    public function __construct(
        private GetUserUseCase $getUser,
        private UserFormatter $formatter,
        private JsonResponder $respond,
    ) {
    }

    public function show(GetUserRequest $request): JsonResponse
    {
        try {
            $user = $this->getUser->execute((int) $request->validated('id'));
        } catch (UserNotFoundException $e) {
            return $this->respond->notFound($e->getMessage());
        }

        return $this->respond->ok($this->formatter->format($user));
    }
}
