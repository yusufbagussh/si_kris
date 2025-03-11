<?php

use App\Http\Controllers\QRISController;
use App\Http\Controllers\QRISNotifyController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/**
 * QRIS Feature Routes
 */
Route::get('/qr', function () {
    return view('qris.generate-qr');
});

Route::get('/inquiry', function () {
    return view('qris.inquiry');
});

Route::post('/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.query-payment');


Route::post('/snap/v1.0/access-token/b2b', [QRISNotifyController::class, 'generateToken']);
Route::post('/snap/v1.1/qr/qr-mpm-notify', [QRISNotifyController::class, 'paymentNotification']);

//Generate Credential
Route::get('/snap/generate/client-credential', function () {
    return response()->json([
        'client_id' => \Illuminate\Support\Str::random(32),
        'client_secret' => \Illuminate\Support\Str::random(16),
    ]);
});

//Generate Signature
Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);
