<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PlayerController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Tools/VideoPlayer', [
            'videoUploadUrl' => route('player.upload', absolute: false),
        ]);
    }

    public function upload(): InertiaResponse
    {
        return Inertia::render('Tools/VideoUpload', [
            'videoPlayerUrl' => route('player.home', absolute: false),
        ]);
    }
}
