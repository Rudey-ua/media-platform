<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\ProfileAvatarData;
use App\DataTransferObjects\User\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileAvatarRequest;
use App\Http\Requests\UpdateProfileNameRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }

        return ApiResponse::success(
            request: $request,
            data: [
                'user' => UserData::fromModel($user),
            ],
        );
    }

    public function updateAvatar(UpdateProfileAvatarRequest $request, UserService $userService): JsonResponse
    {
        $user = $userService->updateProfileAvatar(
            user: Auth::user(),
            data: ProfileAvatarData::fromArray($request->validated())
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'user' => UserData::fromModel($user),
            ],
        );
    }

    public function deleteAvatar(Request $request, UserService $userService): JsonResponse
    {
        $user = $userService->deleteProfileAvatar(
            user: Auth::user()
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'user' => UserData::fromModel($user),
            ],
        );
    }

    public function updateName(UpdateProfileNameRequest $request, UserService $userService): JsonResponse
    {
        $user = $userService->updateName(
            user: Auth::user(),
            name: $request->profileName(),
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'user' => UserData::fromModel($user),
            ],
        );
    }
}
