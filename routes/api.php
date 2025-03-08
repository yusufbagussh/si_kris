<?php

use App\Http\Controllers\QRISController;
use App\Http\Controllers\QRISNotifyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/snap/v1.0/access-token/b2b', [QRISNotifyController::class, 'generateToken']);
Route::post('/snap/v1.1/qr/qr-mpm-notify', [QRISNotifyController::class, 'paymentNotification']);
Route::get('/generate/client-credential', function () {
    return response()->json([
        'client_id' => \Illuminate\Support\Str::random(32),
        'client_secret' => \Illuminate\Support\Str::random(16),
    ]);
});

//Generate Signature
Route::post('/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);
