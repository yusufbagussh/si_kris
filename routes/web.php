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

Route::get('/', function () {
    return view('welcome');
});

/**
 * QRIS Feature Routes
 */
Route::get('/qris', function () {
    return view('qris.generate-qr');
});

Route::get('/inquiry', function () {
    return view('qris.inquiry');
});

Route::get('/', function () {
    return view('qris.index');
});

Route::get('/qris/get-token', [QRISController::class, 'getToken'])->name('qris.generate');
Route::post('/qris/generate', [QRISController::class, 'generateQR'])->name('qris.generate');
Route::post('/qris/generate-qr', [QRISController::class, 'generateQrPatient'])->name('qris.generate-qr');
Route::post('/qris/inquiry', [QRISController::class, 'inquiryPayment'])->name('qris.inquiry');
Route::post('/qris/query-payment', [QRISController::class, 'inquiryPaymentPatient'])->name('qris.query-payment');

//Generate Signature
Route::post('/qris/signature-token', [QRISController::class, 'getSignatureToken']);
Route::post('/qris/signature-notify', [QRISController::class, 'generateSignatureNotify']);

Route::post('/qris/snap/v1.0/access-token/b2b', [QRISNotifyController::class, 'generateToken']);
Route::post('/qris/snap/v1.1/qr/qr-mpm-notify', [QRISNotifyController::class, 'paymentNotification']);

Route::get('/patient', function () {
    return view('qris.patient');
});
Route::post('/patient/check', [QRISController::class, 'checkPatient'])->name('patient.check');

/**
 * Route Test Connection to Database
 */
Route::get('/test-connection', function () {
    try {
        DB::connection('sqlsrv_ws')->getPdo();
        echo "Connected successfully to the database!";
    } catch (\Exception $e) {
        die("Could not connect to the database. Error: " . $e->getMessage());
    }
    // $users = DB::table('Address')
    //     ->select('StreetName')
    //     ->get();
    // var_dump($users);
});
