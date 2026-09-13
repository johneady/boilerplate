<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Registered from bootstrap/app.php under the "api" middleware group, the
| "api/v1" prefix and the "api.v1." route-name prefix. The version lives in
| the file name so a v2 is a new file beside this one rather than a rewrite of
| it -- consumers of v1 keep working while v2 is built.
|
| Routes here are stateless: the api group carries no session and no CSRF
| token, so nothing in this file may rely on auth()->user() being populated by
| a cookie. Adding token authentication means installing Sanctum and wrapping
| the routes below in an auth:sanctum group; it is deliberately not installed
| yet, since an unused authentication surface is a liability rather than a
| convenience.
|
*/

/**
 * A liveness probe for the API surface itself.
 *
 * Deliberately a real, tested route rather than an empty file: it pins the
 * prefix, the name prefix and the JSON envelope, so the wiring is proven to
 * work before anyone adds the first real endpoint to it. /up already covers
 * the application as a whole and is not a substitute -- it is registered
 * outside this group and proves nothing about it.
 */
Route::get('ping', fn (): JsonResponse => response()->json([
    'data' => [
        'status' => 'ok',
        'version' => 'v1',
    ],
]))->name('ping');

/*
 * Example of an authenticated group, left commented until token auth exists:
 *
 * Route::middleware('auth:sanctum')->group(function () {
 *     Route::get('user', fn (Request $request) => $request->user()->toResource());
 * });
 */
