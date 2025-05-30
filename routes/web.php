<?php

use App\Http\Controllers\APM\EDC\EDCController;
use App\Http\Controllers\APM\QRIS\QRISController;
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

Route::get('/edc', function () {
    return view('edc');
});

Route::get('/qr', function () {
    return view('qris.generate-qr');
});

Route::get('/inquiry', function () {
    return view('qris.inquiry');
});

Route::prefix('/apm/ecrlink')->group(function () {
    Route::post('/sale', [EDCController::class, 'sale'])->name('ecrlink.sale');
    Route::post('/contactless', [EDCController::class, 'contactless'])->name('ecrlink.contactless');
    Route::post('/void', [EDCController::class, 'void'])->name('ecrlink.void');
    Route::post('/chech-status-qr', [EDCController::class, 'chech-status-qr'])->name('ecrlink.chech-status-qr');
    Route::post('/refund-qr', [EDCController::class, 'refund-qr'])->name('ecrlink.refund-qr');
    Route::post('/last-print', [EDCController::class, 'last-print'])->name('ecrlink.last-print');
    Route::post('/print-any', [EDCController::class, 'print-any'])->name('ecrlink.print-any');
    Route::post('/settlement', [EDCController::class, 'settlement'])->name('ecrlink.settlement');
});

Route::post('/snap/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/snap/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.inquiry');
Route::post('/snap/list/payment-info', [QRISController::class, 'getListInfoPatientPayment'])->name('qris.notification');

Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::get('/snap/generate/client-credential', function () {
    return response()->json([
        'client_id' => \Illuminate\Support\Str::random(32),
        'client_secret' => \Illuminate\Support\Str::random(16),
    ]);
});

Route::get('/test-medinfras', function () {
    try {
        DB::connection('medinfras_dev')->getPdo();
        echo "Connected successfully to the database sql server!";
    } catch (\Exception $e) {
        die("Could not connect to the database. Error: " . $e->getMessage());
    }
});

Route::post('/contactless', [EDCController::class, 'contactless']);

Route::get('form', function () {
    return view('form');
});
Route::get('/', function () {
    return view('received');
});
Route::get('reverb', function () {
    return view('reverb');
});

// Route::get('public', function () {
//     event(new \App\Events\PublicEvent('hello from Web.php'));
//     return 'done';
// });

// Route::get('test', function () {
//     event(new \App\Events\PublicEvent('792632950548'));
//     event(new \App\Events\QrisNotificationEvent('792632950548'));
//     return 'done';
// });

//Route::get('private', function () {
//    event(new PrivateEvent('hello from Web.php by Admin', 1));
//    return 'done';
//});

// Route::post('hit', [\App\Http\Controllers\NotificationController::class, 'hit']);
