<?php

use App\Http\Controllers\APM\EDC\EDCController;
use App\Http\Controllers\APM\QRIS\QRISController;
use App\Http\Controllers\APM\QRIS\QRISNotificationController;
use App\Http\Controllers\Kasir\KasirMainController;
use App\Http\Controllers\Kasir\KasirPembayaranController;
use App\Http\Controllers\Kasir\KasirTagihanController;
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

//Main QRIS Route
Route::post('/snap/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/snap/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.inquiry');
Route::post('/snap/list/payment-info', [QRISController::class, 'getListInfoPatientPayment'])->name('qris.notification');

//Webhook Notification For BRI
Route::post('/snap/v1.0/access-token/b2b', [QRISNotificationController::class, 'generateToken']);
Route::post('/snap/v1.1/qr/qr-mpm-notify', [QRISNotificationController::class, 'paymentNotification']);

//Generate Token & Signature
Route::post('/snap/generate/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/snap/generate/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::prefix('apm')->group(function () {
    Route::prefix('qris')->group(function () {
        Route::post('generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
        Route::post('query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.inquiry');
        Route::post('list/payment-info', [QRISController::class, 'getListInfoPatientPayment'])->name('qris.notification');
    });

    Route::prefix('edc')->group(function () {
        Route::post('sale', [EDCController::class, 'sale'])->name('ecrlink.sale');
        Route::post('contactless', [EDCController::class, 'contactless'])->name('ecrlink.contactless');
        Route::post('void', [EDCController::class, 'void'])->name('ecrlink.void');
        Route::post('chech-status-qr', [EDCController::class, 'chech-status-qr'])->name('ecrlink.chech-status-qr');
        Route::post('refund-qr', [EDCController::class, 'refund-qr'])->name('ecrlink.refund-qr');
        Route::post('last-print', [EDCController::class, 'last-print'])->name('ecrlink.last-print');
        Route::post('print-any', [EDCController::class, 'print-any'])->name('ecrlink.print-any');
        Route::post('settlement', [EDCController::class, 'settlement'])->name('ecrlink.settlement');
    });

    Route::prefix('medinfras')->group(function () {
        Route::post('clinic', [KasirMainController::class, 'klinikRawatJalan']);
        Route::post('doctor', [KasirMainController::class, 'dokterRawatJalan']);
        Route::post('payment-info', [KasirMainController::class, 'kasirRajalPembayaranInfo']);
        Route::post('list-mesin-edc', [KasirMainController::class, 'listMesinEDCBank']);
        Route::post('list-tipe-kartu', [KasirMainController::class, 'listTipeKartuBank']);

        Route::post('list-patient', [KasirTagihanController::class, 'listPatient']);
        Route::post('detail-payment', [KasirTagihanController::class, 'detailPayment']);
        Route::post('list-patient-bill', [KasirTagihanController::class, 'listTagihanPasien']);
        Route::post('generate-payment-bill', [KasirTagihanController::class, 'generatePaymentBill']);
        Route::post('lock-transaction', [KasirTagihanController::class, 'lockTransaction']);

        Route::post('get-patient-bill', [KasirPembayaranController::class, 'getPatientBill']);
        Route::post('pay-bill', [KasirPembayaranController::class, 'doPaymentBill']);

        Route::post('list-patient-transaction', [KasirTagihanController::class, 'getListTransaksiByRegistrationNo']);
        Route::post('list-patient-bill', [KasirPembayaranController::class, 'getPatientBillByRegistrationNo']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
