<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'playerHomeUrl' => route('player.home', absolute: false),
            'profileUrl' => route('profile.edit', absolute: false),
            'webLogoutUrl' => route('logout', absolute: false),
            'canManageMembers' => $user instanceof User && $user->isOwner(),
        ]);
    }
}
