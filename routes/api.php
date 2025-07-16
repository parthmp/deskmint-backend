<?php

use App\Http\Controllers\LoginController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::post('/login', [LoginController::class, 'login']);

/*
Route::post('/debug-csrf', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'session_started' => session()->has('_token'),
        'session_csrf_token' => session()->token(),
        'header_csrf_token' => $request->header('X-XSRF-TOKEN'),
        'cookie_csrf_token' => $request->cookie('XSRF-TOKEN'),
        'all_cookies' => request()->cookies->all(),
        'all_headers' => request()->headers->all(),
        'session_id' => session()->getId(),
    ]);
});*/