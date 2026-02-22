<?php

namespace App\Http\Controllers\API;

use App\DataTransferObjects\User\MemberData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\SyncMemberVideoAccessRequest;
use App\Http\Requests\UpdateMemberAccessModeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    public function index(Request $request, MemberService $memberService): JsonResponse
    {
        $owner = Auth::user();

        if (! $owner instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $members = $memberService->listForOwner($owner);

        return ApiResponse::success(
            request: $request,
            data: [
                'members' => $members->map(static fn (User $member): MemberData => MemberData::fromModel($member))->all(),
            ],
        );
    }

    public function store(StoreMemberRequest $request, MemberService $memberService): JsonResponse
    {
        $owner = Auth::user();

        if (! $owner instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $createdMember = $memberService->createForOwner(
            owner: $owner,
            email: $request->memberEmail(),
            accessMode: $request->accessMode(),
        );

        return ApiResponse::created(
            request: $request,
            data: [
                'member' => MemberData::fromModel($createdMember['member']),
                'generated_password' => $createdMember['generated_password'],
            ],
        );
    }

    public function updateAccessMode(UpdateMemberAccessModeRequest $request, int $memberId, MemberService $memberService): JsonResponse
    {
        $owner = Auth::user();

        if (! $owner instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $member = $memberService->updateAccessModeForOwner(
            owner: $owner,
            memberId: $memberId,
            accessMode: $request->accessMode(),
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'member' => MemberData::fromModel($member),
            ],
        );
    }

    public function syncVideoAccess(SyncMemberVideoAccessRequest $request, int $memberId, MemberService $memberService): JsonResponse
    {
        $owner = Auth::user();

        if (! $owner instanceof User) {
            return ApiResponse::error(
                message: 'Unauthenticated',
                status: Response::HTTP_UNAUTHORIZED,
            );
        }
        $member = $memberService->syncVideoAccessForOwner(
            owner: $owner,
            memberId: $memberId,
            videoUuids: $request->videoUuids(),
        );

        return ApiResponse::success(
            request: $request,
            data: [
                'member' => MemberData::fromModel($member),
            ],
        );
    }
}
