<?php

namespace App\Http\Controllers;

use App\Models\QrisNotification;
use App\Models\QrisToken;
use App\Models\QrisTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QRISNotificationController extends Controller
{
    private $briClientKey;
    private $briClientSecret;
    private $briPublicKeyPath;
    private $briPartnerId;
    private $briPublicKey;

    public function __construct()
    {
        $this->briPartnerId = config('qris.clients.bri.partner_id');
        $this->briClientKey = config('qris.clients.bri.client_id');
        $this->briClientSecret = config('qris.clients.bri.client_secret');
        $this->briPublicKeyPath = storage_path('app/public/keys/public_key.pem');
        $this->briPublicKey = config('qris.clients.bri.public_key');
    }

    /**
     * Generate dan berikan token akses untuk BRI
     */
    public function generateToken(Request $request)
    {
        try {
            Log::info('Token Request', ['headers' => $request->header(), 'body' => $request->all()]);

            $requiredHeaders = ['X-CLIENT-KEY', 'X-TIMESTAMP', 'X-SIGNATURE'];
            foreach ($requiredHeaders as $header) {
                if (!$request->header($header)) {
                    return response()->json([
                        'responseCode' => '5201',
                        'responseMessage' => "Invalid Mandatory Field: Header $header missing"
                    ], 400);
                }
            }

            $clientKey = $request->header('X-CLIENT-KEY');
            $timestamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');

            if ($this->briClientKey != $clientKey) {
                return response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Client'
                ], 401);
            }

            $stringToSign = "{$clientKey}|{$timestamp}";

            $publicKey = openssl_pkey_get_public($this->briPublicKey);
            if (!$publicKey) {
                Log::error("Failed to get public key for client: $clientKey");
                return response()->json([
                    'responseCode' => '500000',
                    'responseMessage' => 'General Error'
                ], 500);
            }

            $isVerified = openssl_verify(
                $stringToSign,
                base64_decode($signature),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );
            if ($isVerified !== 1) {
                return response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Signature'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'grantType' => 'required|in:client_credentials',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'responseCode' => '4007301',
                    'responseMessage' => 'Invalid Field Format'
                ], 400);
            }

            $token = Str::random(32);
            $expiresIn = 899; // 15 menit - 1 detik

            $this->saveToken($token, $clientKey, $expiresIn);

            return response()->json([
                'accessToken' => $token,
                'tokenType' => 'BearerToken',
                'expiresIn' => (string)$expiresIn
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in token generation: ' . $e->getMessage());
            return response()->json([
                'responseCode' => '500000',
                'responseMessage' => 'General Error : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan token ke database/cache
     */
    private function saveToken($token, $clientKey, $expiresIn)
    {
        $expiration = now()->addSeconds($expiresIn);
        //Cache::put("qris_token_{$token}", [
        //    'client_key' => $clientKey,
        //    'expires_at' => $expiration
        //], $expiration);

        QrisToken::create([
            'token' => $token,
            'client_key' => $clientKey,
            'expires_at' => $expiration
        ]);
    }

    /**
     * Handle notifikasi pembayaran dari BRI
     */
    public function paymentNotification(Request $request)
    {
        try {
            Log::info('Payment Notify Request', ['headers' => $request->header(), 'body' => $request->all()]);

            $authHeader = $request->header('Authorization');
            if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
                return response()->json([
                    'responseCode' => '4017301',
                    'responseMessage' => 'Invalid Token (B2B)'
                ], 401);
            }

            $token = Str::substr($authHeader, 7);
            if (!$this->validateToken($token)) {
                return response()->json([
                    'responseCode' => '4017301',
                    'responseMessage' => 'Invalid Token (B2B)'
                ], 401);
            }

            $requiredHeaders = [
                'X-SIGNATURE',
                'X-TIMESTAMP',
                'X-PARTNER-ID',
                'X-EXTERNAL-ID'
            ];

            foreach ($requiredHeaders as $header) {
                if (!$request->header($header)) {
                    return response()->json([
                        'responseCode' => '4005202',
                        'responseMessage' => "Invalid Mandatory Field: Header $header missing"
                    ], 400);
                }
            }

            if (preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})[+-](\d{2}):(\d{2})$/',
                $request->header('X-TIMESTAMP')
            ) !== 1) {
                return response()->json([
                    'responseCode' => '4005201',
                    'responseMessage' => 'Invalid Field Format: timestamp must be in ISO 8601 format'
                ], 400);
            }

            $validatorExternalID = Validator::make($request->header(), [
                'x-external-id' => 'unique:qris_notifications,external_id',
            ]);

            if ($validatorExternalID->fails()) {
                return response()->json([
                    'responseCode' => '4095200',
                    'responseMessage' => 'conflict'
                ], 409);
            }

            if (!$this->verifyNotificationSignature($request)) {
                return response()->json([
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Signature'
                ], 401);
            }

            $requiredFields = [
                'originalReferenceNo',
                'originalPartnerReferenceNo',
                'customerNumber',
                'destinationAccountName',
                'amount'
            ];

            foreach ($requiredFields as $field) {
                if (!$request->has($field)) {
                    return response()->json([
                        'responseCode' => '4005202',
                        'responseMessage' => "Invalid Mandatory Field: $field"
                    ], 400);
                }
            }

            if (!$request->has('amount.value') || !$request->has('amount.currency')) {
                return response()->json([
                    'responseCode' => '4005202',
                    'responseMessage' => 'Invalid Mandatory Field in amount object'
                ], 400);
            }

            // Validate required fields
            $validator = Validator::make($request->all(), [
                'originalReferenceNo' => 'string',
                'originalPartnerReferenceNo' => 'string',
                'customerNumber' => 'string|max:64',
                'destinationAccountName' => 'string|max:25',
                'amount.value' => 'numeric',
                'amount.currency' => 'string|size:3',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'responseCode' => '4005201',
                    'responseMessage' => 'Invalid Field Format: ' . implode(', ', $validator->errors()->all())
                ], 400);
            }

            $transaction = QrisTransaction::where('partner_reference_no', $request->originalPartnerReferenceNo)->first();

            if ($transaction == null) {
                return response()->json([
                    'responseCode' => '4045200',
                    'responseMessage' => 'Transaction Not Found. Invalid Number'
                ], 404);
            }

            $headers = [
                'x-signature' => $request->header('x-signature') ?? null,
                'x-timestamp' => $request->header('x-timestamp') ?? null,
                'origin' => $request->header('origin') ?? null,
                'x-partner-id' => $request->header('x-partner-id') ?? null,
                'x-external-id' => $request->header('x-external-id') ?? null,
                'x-ip-address' => $request->header('x-ip-address') ?? null,
                'x-device-id' => $request->header('x-device-id') ?? null,
                'x-latitude' => $request->header('x-latitude') ?? null,
                'x-longitude' => $request->header('x-longitude') ?? null,
                'channel-id' => $request->header('channel-id') ?? null,
            ];

            $this->processPayment($headers, $request->all());

            return response()->json([
                'responseCode' => '2005200',
                'responseMessage' => 'Successfull',
                'additionalInfo' => $request->additionalInfo ?? []
            ], 200);
        } catch (\Exception $e) {
            Log::error('Payment Notify Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'responseCode' => '5005200',
                'responseMessage' => 'General Error'
            ], 500);
        }
    }

    /**
     * Validasi token
     */
    private function validateToken($token)
    {
        // Pilihan 1: Validasi menggunakan cache
        $tokenData = Cache::get("qris_token_{$token}");
        if ($tokenData) {
            return true;
        }

        // Pilihan 2: Validasi menggunakan database
        $tokenRecord = QrisToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        return $tokenRecord !== null;
    }

    /**
     * Verifikasi signature untuk notifikasi pembayaran
     */
    private function verifyNotificationSignature(Request $request)
    {
        try {
            $partnerId = $request->header('X-PARTNER-ID');
            $timestamp = $request->header('X-TIMESTAMP');
            $signature = $request->header('X-SIGNATURE');
            $token = Str::substr($request->header('Authorization'), 7);

            // Validasi partner ID
            if ($this->briPartnerId != $partnerId) {
                Log::error("Unknown partner ID: $partnerId");
                return false;
            }

            // Pastikan request body terurut dengan benar
            $requestBody = json_encode($request->all());

            // Buat string to sign (sama seperti di generateSignature)
            $method = 'POST';
            $endpoint = '/snap/v1.1/qr/qr-mpm-notify';
            $hashedBody = strtolower(hash('sha256', $requestBody));
            $stringToSign = "$method:$endpoint:$token:$hashedBody:$timestamp";

            Log::info("StringToSign (Verification): " . $stringToSign);
            Log::info("KEY HMAC: " . $this->briClientSecret);

            // Buat signature lokal untuk dibandingkan
            $expectedSignature = base64_encode(hash_hmac('sha512', $stringToSign, $this->briClientSecret, true));

            Log::info("Expected Signature: " . $expectedSignature);
            Log::info("Received Signature: " . $signature);

            // Bandingkan signature
            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            Log::error('Error in signature verification: ' . $e->getMessage());
            return false;
        }
    }
    // private function verifyNotificationSignature(Request $request)
    // {
    //     try {
    //         $partnerId = $request->header('X-PARTNER-ID');
    //         $timestamp = $request->header('X-TIMESTAMP');
    //         $signature = $request->header('X-SIGNATURE');
    //         $token = Str::substr($request->header('Authorization'), 7);

    //         // Dapatkan partner berdasarkan partner ID
    //         // $partners = config('qris.partners');
    //         // if (!isset($partners[$partnerId])) {
    //         if ($this->briPartnerId != $partnerId) {
    //             Log::error("Unknown partner ID: $partnerId");
    //             return false;
    //         }

    //         // $partnerInfo = $partners[$partnerId];

    //         // Buat string to sign sesuai dokumentasi
    //         $method = 'POST';
    //         $endpoint = '/snap/v1.1/qr/qr-mpm-notify';

    //         // Hash request body
    //         $requestBody = json_encode($request->all());
    //         $bodyHash = strtolower(hash('sha256', $requestBody));

    //         // String to sign
    //         $stringToSign = "$method:$endpoint:$token:$bodyHash:$timestamp";

    //         // Verifikasi signature
    //         // $publicKey = openssl_pkey_get_public($partnerInfo['public_key']);
    //         $briPublicKeyContent = file_get_contents($this->briPublicKeyPath);
    //         $publicKey = openssl_pkey_get_public($briPublicKeyContent);
    //         if (!$publicKey) {
    //             Log::error("Failed to get public key for partner: $partnerId");
    //             return false;
    //         }

    //         $isVerified = openssl_verify(
    //             $stringToSign,
    //             base64_decode($signature),
    //             // $this->briClientSecret,
    //             $publicKey,
    //             OPENSSL_ALGO_SHA256
    //         );

    //         return $isVerified === 1;
    //     } catch (\Exception $e) {
    //         Log::error('Error in signature verification: ' . $e->getMessage());
    //         return false;
    //     }
    // }

    /**
     * Proses pembayaran
     */
    private function processPayment(array $headers, $paymentData)
    {
        Log::info('Payment notification received', [
            'ref_no' => $paymentData['originalReferenceNo'],
            'partner_ref_no' => $paymentData['originalPartnerReferenceNo'],
            'status' => $paymentData['latestTransactionStatus'] ?? 'Not provided',
            'amount' => $paymentData['amount']['value'],
            'currency' => $paymentData['amount']['currency']
        ]);

        $transactionId = null;
        $transaction = QrisTransaction::where('original_reference_no', $paymentData['originalReferenceNo'])->first();
        if ($transaction != null) {
            $transactionId = $transaction->id;
        }

        $data = [
            'qris_transaction_id' => $transactionId,
            'original_reference_no' => $paymentData['originalReferenceNo'],
            'partner_reference_no' => $paymentData['originalPartnerReferenceNo'],
            'external_id' => $headers['x-external-id'],
            'latest_transaction_status' => $paymentData['latestTransactionStatus'] ?? null,
            'transaction_status_desc' => $paymentData['transactionStatusDesc'] ?? null,
            'customer_number' => $paymentData['customerNumber'],
            'account_type' => $paymentData['accountType'] ?? null,
            'destination_account_name' => $paymentData['destinationAccountName'],
            'amount' => $paymentData['amount']['value'],
            'currency' => $paymentData['amount']['currency'],
            'bank_code' => $paymentData['bankCode'] ?? null,
            'session_id' => $paymentData['sessionID'] ?? null,
            'external_store_id' => $paymentData['externalStoreID'] ?? null,
            'reff_id' => $paymentData['additionalInfo']['reffId'] ?? null,
            'issuer_name' => $paymentData['additionalInfo']['issuerName'] ?? null,
            'issuer_rrn' => $paymentData['additionalInfo']['issuerRrn'] ?? null,
            'raw_request' => json_encode($paymentData),
            'raw_header' => json_encode($headers),
        ];
        // Simpan data pembayaran ke database
        QrisNotification::create($data);

        // Di sini Anda bisa tambahkan logika bisnis lainnya
        // Misalnya: memperbarui status pesanan, mengirim notifikasi, dll.
        /*
         * Code here
         */
    }
}
