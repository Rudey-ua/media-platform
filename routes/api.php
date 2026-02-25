<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MemberController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\VideoController;
use App\Http\Controllers\API\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
        Route::post('/refresh', 'refreshToken');

        Route::group(['middleware' => ['auth:api']], function () {
            Route::post('/logout', 'logout');
        });
    });

    Route::group(['middleware' => ['auth:api']], function () {
        Route::controller(UserController::class)->group(function () {
            Route::get('/profile', 'profile');
            Route::patch('/profile', 'updateName');
            Route::post('/profile/avatar', 'updateAvatar');
            Route::delete('/profile/avatar', 'deleteAvatar');
        });

        Route::controller(VideoController::class)->group(function () {
            Route::post('/videos/uploads', 'initiateUpload');
            Route::post('/videos/{videoId}/uploads/complete', 'completeUpload')->whereUuid('videoId');
            Route::post('/videos', 'store');
            Route::get('/videos', 'index');
            Route::get('/videos/{videoId}', 'show')->whereUuid('videoId');
            Route::patch('/videos/{videoId}', 'update')->whereUuid('videoId');
            Route::delete('/videos/{videoId}', 'destroy')->whereUuid('videoId');
        });

        Route::controller(MemberController::class)->group(function () {
            Route::get('/members', 'index');
            Route::post('/members', 'store');
            Route::patch('/members/{memberId}/access-mode', 'updateAccessMode')->whereNumber('memberId');
            Route::put('/members/{memberId}/video-access', 'syncVideoAccess')->whereNumber('memberId');
        });
    });
    Route::post('/webhooks/video-encoding', [WebhookController::class, 'handle']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/videos/{videoId}/playback', [VideoController::class, 'playback'])->whereUuid('videoId');
    });
    Route::get('/videos/{videoId}/playback/asset', [VideoController::class, 'playbackAsset'])->whereUuid('videoId')->middleware('throttle:1200,1')->name('videos.playback.asset');
});
