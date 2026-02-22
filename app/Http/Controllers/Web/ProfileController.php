<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function show(Request $request): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'playerHomeUrl' => route('player.home', absolute: false),
            'profileUrl' => route('profile.show', absolute: false),
            'profileEditUrl' => route('profile.edit', absolute: false),
            'profileMembersUrl' => route('profile.members', absolute: false),
            'profileVideoAccessUrl' => route('profile.video-access', absolute: false),
            'webLogoutUrl' => route('logout', absolute: false),
            'canManageMembers' => $user instanceof User && $user->isOwner(),
        ]);
    }

    public function edit(): InertiaResponse
    {
        return Inertia::render('Profile/EditName', [
            'profileUrl' => route('profile.show', absolute: false),
            'profileShowUrl' => route('profile.show', absolute: false),
        ]);
    }

    public function members(): InertiaResponse
    {
        return Inertia::render('Profile/Members', [
            'profileUrl' => route('profile.show', absolute: false),
            'profileShowUrl' => route('profile.show', absolute: false),
            'profileVideoAccessUrl' => route('profile.video-access', absolute: false),
        ]);
    }

    public function videoAccess(): InertiaResponse
    {
        return Inertia::render('Profile/VideoAccess', [
            'profileUrl' => route('profile.show', absolute: false),
            'profileShowUrl' => route('profile.show', absolute: false),
            'profileMembersUrl' => route('profile.members', absolute: false),
        ]);
    }
}
