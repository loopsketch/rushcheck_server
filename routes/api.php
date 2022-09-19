<?php

use App\Http\Controllers\ShareController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// rushcheck application API
Route::prefix('v')->name('v.')->group(function () {
    Route::prefix('0')->name('0.')->group(function () {
        Route::prefix('share')->name('share.')->group(function () {
            Route::post('/start', [ShareController::class, 'start'])->name('start');
            Route::post('/stop', [ShareController::class, 'stop'])->name('stop');
            Route::post('/push_event', [ShareController::class, 'push_event'])->name('push_event');
            Route::post('/wait_event', [ShareController::class, 'wait_event'])->name('wait_event');
        });    
    });
});
