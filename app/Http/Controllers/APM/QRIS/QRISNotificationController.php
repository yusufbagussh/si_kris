<?php

namespace App\Http\Controllers\APM\QRIS;

use App\Events\QrisNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\QrisNotification;
use App\Models\QrisPayment;
use App\Models\QrisToken;
use App\Services\BRI\QRISNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QRISNotificationController extends Controller
{
    private $briClientKey;
    private $briClientSecret;
    private $briPartnerId;
    private $briPublicKey;
    private $apmWebhookUrl;
    private $apmWebhookSecret;

    private QrisPayment $qrisPayment;
    private QrisToken $qrisToken;

    private $qrisNotificationService;

    public function __construct()
    {
        $this->briPartnerId = config('qris.partners.bri.webhook.partner_id');
        $this->briClientKey = config('qris.partners.bri.webhook.client_id');
        $this->briClientSecret = config('qris.partners.bri.webhook.client_secret');
        $this->briPublicKey = config('qris.partners.bri.webhook.public_key');

        $this->apmWebhookUrl = config('qris.soba.webhook.url');
        $this->apmWebhookSecret = config('qris.soba.webhook.secret');

        $this->qrisPayment = new QrisPayment();
        $this->qrisToken = new QrisToken();

        $this->qrisNotificationService = new QRISNotificationService();
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
                        'responseCode' => '4007302',
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
                    'responseMessage' => 'Invalid Field Format Grant Type'
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
            Log::error('[' . $e->getCode() . '][generateToken] ' . $e->getMessage());
            return response()->json([
                'responseCode' => '500000',
                'responseMessage' => 'General Error'
            ], 500);
        }
    }

    /**
     * Menyimpan token ke database/cache
     */
    private function saveToken($token, $clientKey, $expiresIn)
    {
        $expiration = now()->addSeconds($expiresIn);
        QrisToken::create([
            'token' => $token,
            'client_key' => $clientKey,
            'expires_at' => $expiration
        ]);
    }

    public function paymentNotification(Request $request)
    {
        try {
            Log::info('Payment Notify Request', ['headers' => $request->header(), 'body' => $request->all()]);

            $authHeader = $request->header('Authorization');
            if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
                $response = [
                    'responseCode' => '4017301',
                    'responseMessage' => 'Invalid Token (B2B)'
                ];
                Log::info('Payment Notify Response', ['status' => 401, 'response' => $response]);
                return response()->json($response, 401);
            }

            $token = Str::substr($authHeader, 7);
            if (!$this->validateToken($token)) {
                $response = [
                    'responseCode' => '4017301',
                    'responseMessage' => 'Invalid Token (B2B)'
                ];
                Log::info('Payment Notify Response', ['status' => 401, 'response' => $response]);
                return response()->json($response, 401);
            }

            $requiredHeaders = [
                'X-SIGNATURE',
                'X-TIMESTAMP',
                'X-PARTNER-ID',
                'X-EXTERNAL-ID'
            ];

            foreach ($requiredHeaders as $header) {
                if (!$request->header($header)) {
                    $response = [
                        'responseCode' => '4005202',
                        'responseMessage' => "Invalid Mandatory Field: Header $header missing"
                    ];
                    Log::info('Payment Notify Response', ['status' => 400, 'response' => $response]);
                    return response()->json($response, 400);
                }
            }

            if (preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})[+-](\d{2}):(\d{2})$/',
                $request->header('X-TIMESTAMP')
            ) !== 1) {
                $response = [
                    'responseCode' => '4005201',
                    'responseMessage' => 'Invalid Field Format: timestamp must be in ISO 8601 format'
                ];
                Log::info('Payment Notify Response', ['status' => 400, 'response' => $response]);
                return response()->json($response, 400);
            }

            $validatorExternalID = Validator::make($request->header(), [
                'x-external-id' => 'unique:qris_notifications,external_id',
            ]);

            if ($validatorExternalID->fails()) {
                $response = [
                    'responseCode' => '4095200',
                    'responseMessage' => 'conflict'
                ];
                Log::info('Payment Notify Response', ['status' => 409, 'response' => $response]);
                return response()->json($response, 409);
            }

            Log::info("CLIENT SECRET REQUEST: " . $this->briClientSecret);


            if (!$this->verifyNotificationSignature($request)) {
                $response = [
                    'responseCode' => '4017300',
                    'responseMessage' => 'Unauthorized Signature'
                ];
                Log::info('Payment Notify Response', ['status' => 401, 'response' => $response]);
                return response()->json($response, 401);
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
                    $response = [
                        'responseCode' => '4005202',
                        'responseMessage' => "Invalid Mandatory Field: $field"
                    ];
                    Log::info('Payment Notify Response', ['status' => 400, 'response' => $response]);
                    return response()->json($response, 400);
                }
            }

            if (!$request->has('amount.value') || !$request->has('amount.currency')) {
                $response = [
                    'responseCode' => '4005202',
                    'responseMessage' => 'Invalid Mandatory Field in amount object'
                ];
                Log::info('Payment Notify Response', ['status' => 400, 'response' => $response]);
                return response()->json($response, 400);
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
                $response = [
                    'responseCode' => '4005201',
                    'responseMessage' => 'Invalid Field Format: ' . implode(', ', $validator->errors()->all())
                ];
                Log::info('Payment Notify Response', ['status' => 400, 'response' => $response]);
                return response()->json($response, 400);
            }

            $transaction = $this->qrisPayment->where('partner_reference_no', $request->originalPartnerReferenceNo)->first();

            if ($transaction == null) {
                $response = [
                    'responseCode' => '4045200',
                    'responseMessage' => 'Transaction Not Found. Invalid Number'
                ];
                Log::info('Payment Notify Response', ['status' => 404, 'response' => $response]);
                return response()->json($response, 404);
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

            $this->processPayment($headers, $request->all(), $transaction);

            // Jika status transaksi adalah sukses, kirimkan notifikasi ke webhook Internal
            if ($request->transactionStatusDesc == 'success') {
                $transaction->load(['patientPayment.patientPaymentDetail']);

                $data = [
                    "registration_no" => $transaction->patientPayment->registration_no,
                    "remarks" => "Pembayaran melalui {$request->input('AdditionalInfo.issuerName')} oleh {$request->destinationAccountName}",
                    "reference_no" => $transaction->original_reference_no,
                    "status" => $request->transactionStatusDesc,
                    "issuer_name" => $request->input('AdditionalInfo.issuerName'),
                    "payment_amount" => intval($request->input('amount.value')),
                    "card_type" => "001", //Debit Card
                    "card_provider" => "003", //BRI
                    "machine_code" => "EDC013", //BRI
                    "bank_code" => "007", //BRI
                    "shift" => "001", //Pagi
                    "cashier_group" => "012", //KASIR RAWAT JALAN
                ];

                $billingList = [];
                foreach ($transaction->patientPayment->patientPaymentDetail as $detail) {
                    $billingAmount = intval($detail->billing_amount);
                    $billingList[] = "{$detail->billing_no}-{$billingAmount}";
                }

                $data['bill_list'] = implode(',', $billingList);

                $headersWebhook = [
                    'Content-Type' => 'application/json',
                    'X-Signature' =>  hash_hmac('sha256', json_encode($data), $this->apmWebhookSecret),
                ];

                Log::info('Callback APM Request', [
                    'headers' => $headersWebhook,
                    'data' => $data,
                ]);

                $response = Http::withHeaders($headersWebhook)->post($this->apmWebhookUrl, $data);

                Log::info('Callback APM Response', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            }

            $response = [
                'responseCode' => '2005200',
                'responseMessage' => 'Successfull',
                'additionalInfo' => [
                    'reffId' => $request->input('AdditionalInfo.ReffId') ?? null,
                    'issuerName' => $request->input('AdditionalInfo.issuerName') ?? null,
                    'issuerRrn' => $request->input('AdditionalInfo.issuerRrn') ?? null,
                ] ?? []
            ];

            Log::info('Payment Notify Response', ['status' => 200, 'response' => $response]);
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][generateToken] ' . $e->getMessage());
            $response = [
                'responseCode' => '5005200',
                'responseMessage' => 'General Error'
            ];
            Log::info('Payment Notify Response', ['status' => 500, 'response' => $response]);
            return response()->json($response, 500);
        }
    }


    public function receiveNotification(Request $request)
    {
        try {
            $transaction = $this->qrisPayment->where('partner_reference_no', $request->originalPartnerReferenceNo)->first();

            if ($transaction == null) {
                $response = [
                    'responseCode' => '4045200',
                    'responseMessage' => 'Transaction Not Found. Invalid Number'
                ];
                Log::info('Payment Notify Response', ['status' => 404, 'response' => $response]);
                return response()->json($response, 404);
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

            $this->processPayment($headers, $request->all(), $transaction);

            // Jika status transaksi adalah sukses, kirimkan notifikasi ke webhook Internal
            if ($request->transactionStatusDesc == 'success') {
                $transaction->load(['patientPayment.patientPaymentDetail']);

                $data = [
                    "registration_no" => $transaction->patientPayment->registration_no,
                    "remarks" => "Pembayaran melalui {$request->input('AdditionalInfo.issuerName')} oleh {$request->destinationAccountName}",
                    "reference_no" => $transaction->original_reference_no,
                    "status" => $request->transactionStatusDesc,
                    "issuer_name" => $request->input('AdditionalInfo.issuerName'),
                    "payment_amount" => intval($request->input('amount.value')),
                    "card_type" => "001", //Debit Card
                    "card_provider" => "003", //BRI
                    "machine_code" => "EDC013", //BRI
                    "bank_code" => "007", //BRI
                    "shift" => "001", //Pagi
                    "cashier_group" => "012", //KASIR RAWAT JALAN
                ];

                $billingList = [];
                foreach ($transaction->patientPayment->patientPaymentDetail as $detail) {
                    $billingAmount = intval($detail->billing_amount);
                    $billingList[] = "{$detail->billing_no}-{$billingAmount}";
                }

                $data['bill_list'] = implode(',', $billingList);

                $headersWebhook = [
                    'Content-Type' => 'application/json',
                    'X-Signature' =>  hash_hmac('sha256', json_encode($data), $this->apmWebhookSecret),
                ];

                Log::info('Callback APM Request', [
                    'headers' => $headersWebhook,
                    'data' => $data,
                ]);

                $response = Http::withHeaders($headersWebhook)->post($this->apmWebhookUrl, $data);

                Log::info('Callback APM Response', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
            }

            $response = [
                'responseCode' => '2005200',
                'responseMessage' => 'Successfull',
                'additionalInfo' => [
                    'reffId' => $request->input('AdditionalInfo.ReffId') ?? null,
                    'issuerName' => $request->input('AdditionalInfo.issuerName') ?? null,
                    'issuerRrn' => $request->input('AdditionalInfo.issuerRrn') ?? null,
                ] ?? []
            ];

            Log::info('Payment Notify Response', ['status' => 200, 'response' => $response]);
            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][generateToken] ' . $e->getMessage());
            $response = [
                'responseCode' => '5005200',
                'responseMessage' => 'General Error'
            ];
            Log::info('Payment Notify Response', ['status' => 500, 'response' => $response]);
            return response()->json($response, 500);
        }
    }


    /**
     * Validasi token
     */
    private function validateToken($token)
    {
        $tokenRecord = $this->qrisToken->checkExpiredToken($token);
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
            $endpoint = '/api/snap/v1.1/qr/qr-mpm-notify';
            $hashedBody = bin2hex(strtolower(hash('sha256', $requestBody)));
            $stringToSign = "$method:$endpoint:$token:$hashedBody:$timestamp";

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

    /**
     * Proses pembayaran
     */
    private function processPayment(array $headers, $paymentData, $transaction)
    {
        $data = [
            'qris_transaction_id' => $transaction->id,
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
            'reff_id' => $paymentData['AdditionalInfo']['ReffId'] ?? null,
            'issuer_name' => $paymentData['AdditionalInfo']['issuerName'] ?? null,
            'issuer_rrn' => $paymentData['AdditionalInfo']['issuerRrn'] ?? null,
            'raw_request' => json_encode($paymentData),
            'raw_header' => json_encode($headers),
        ];
        // Simpan data pembayaran ke database
        QrisNotification::create($data);

        $statusMap = [
            '00' => 'SUCCESS',
            '01' => 'INITIATED',
            '02' => 'PAYING',
            '03' => 'PENDING',
            '04' => 'REFUNDED',
            '05' => 'CANCELED',
            '06' => 'FAILED',
            '07' => 'NOT_FOUND',
        ];

        //Update data QRIS Transaction
        $status = $statusMap[$paymentData['latestTransactionStatus']] ?? 'UNKNOWN';
        $transaction->status = $status;
        if ($status == 'SUCCESS') {
            $transaction->paid_at = now();
        }
        $transaction->save();
    }

    public function generateSignatureToken()
    {
        return response()->json($this->qrisNotificationService->generateSignatureAccessToken());
    }

    public function generateSignatureNotify(Request $request)
    {
        return response()->json(
            $this->qrisNotificationService->generateSignatureNotify(
                $request
            )
        );
    }


    /**
     * Test fungsi paymentNotification dengan data static dan auto generate token
     * Tambahkan method ini ke dalam QRISNotificationController
     */
    public function testPaymentNotification(Request $request)
    {
        try {
            // Validasi input yang diperlukan
            $validator = Validator::make($request->all(), [
                'originalReferenceNo' => 'required|string',
                'originalPartnerReferenceNo' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Validation failed',
                    'messages' => $validator->errors()->all()
                ], 400);
            }

            // Step 1: Generate token otomatis
            $token = $this->generateTestTokenWithBypass();
            if (!$token) {
                return response()->json([
                    'error' => 'Failed to generate test token'
                ], 500);
            }

            // Step 2: Prepare static test data
            $testData = [
                'originalReferenceNo' => $request->input('originalReferenceNo'),
                'originalPartnerReferenceNo' => $request->input('originalPartnerReferenceNo'),
                'latestTransactionStatus' => '00', // SUCCESS
                'transactionStatusDesc' => 'success',
                'customerNumber' => '6281234567890',
                'accountType' => 'SVGS',
                'destinationAccountName' => 'TEST USER',
                'amount' => [
                    'value' => '1.00',
                    'currency' => 'IDR'
                ],
                'bankCode' => '002',
                'sessionID' => 'TEST_SESSION_' . time(),
                'externalStoreID' => 'STORE001',
                'AdditionalInfo' => [
                    'ReffId' => 'REF_' . time(),
                    'issuerName' => 'Bank BRI',
                    'issuerRrn' => 'RRN' . time()
                ]
            ];

            // Step 3: Prepare headers dengan signature yang valid
            $headers = $this->generateTestHeaders($token, $testData);

            // Step 4: Simulasi request ke paymentNotification
            $testRequest = $this->createTestRequest($headers, $testData);

            // Call the actual paymentNotification method
            $response = $this->paymentNotification($testRequest);

            // Return response dengan informasi tambahan untuk debugging
            return response()->json([
                'message' => 'Test payment notification executed',
                'generated_token' => $token,
                'test_data' => $testData,
                'test_headers' => $headers,
                'payment_response' => $response->getData(),
                'payment_status_code' => $response->getStatusCode()
            ]);
        } catch (\Exception $e) {
            Log::error('[testPaymentNotification] ' . $e->getMessage());
            return response()->json([
                'error' => 'Test failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate token untuk testing
     */
    /**
     * Generate token dengan memodifikasi sementara verification untuk testing
     */
    private function generateTestTokenWithBypass()
    {
        try {
            $timestamp = now()->toISOString();

            // Create mock request untuk generateToken
            $tokenRequest = new Request();
            $tokenRequest->merge([
                'grantType' => 'client_credentials'
            ]);

            $signature = $this->qrisNotificationService->generateSignatureAccessToken();

            // Set headers dengan signature dummy
            $tokenRequest->headers->set('X-CLIENT-KEY', $signature['X-CLIENT-KEY']);
            $tokenRequest->headers->set('X-TIMESTAMP', $signature['X-TIMESTAMP']);
            $tokenRequest->headers->set('X-SIGNATURE', $signature['X-SIGNATURE']);
            $tokenRequest->headers->set('Content-Type', 'application/json');
            $tokenRequest->headers->set('Accept', 'application/json');


            // Call generateToken method
            $tokenResponse = $this->generateToken($tokenRequest);

            $responseData = json_decode($tokenResponse->getContent(), true);

            if ($tokenResponse->getStatusCode() === 200 && isset($responseData['accessToken'])) {
                return $responseData['accessToken'];
            } else {
                Log::error('Failed to generate token via generateToken method', [
                    'status' => $tokenResponse->getStatusCode(),
                    'response' => $responseData
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate test token with bypass: ' . $e->getMessage());
            // Fallback to simple method
            return $this->generateTestTokenSimple();
        }
    }
    private function generateExternalId()
    {
        return (string)mt_rand(1000000000000000, 9999999999999999) . mt_rand(1000000000000000, 9999999999999999);
    }

    /**
     * Generate headers untuk test dengan signature yang valid
     */
    private function generateTestHeaders($token, $testData)
    {
        $timestamp = now()->toIso8601String();; // Format ISO 8601
        $externalId = $this->generateExternalId();

        // Generate signature sesuai dengan algoritma di verifyNotificationSignature
        $requestBody = json_encode($testData);
        $method = 'POST';
        $endpoint = '/api/snap/v1.1/qr/qr-mpm-notify';
        $hashedBody = bin2hex(strtolower(hash('sha256', $requestBody)));
        $stringToSign = "$method:$endpoint:$token:$hashedBody:$timestamp";
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->briClientSecret, true));

        return [
            'Authorization' => 'Bearer ' . $token,
            'X-SIGNATURE' => $signature,
            'X-TIMESTAMP' => $timestamp,
            'X-PARTNER-ID' => $this->briPartnerId,
            'X-EXTERNAL-ID' => $externalId,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Create mock request object untuk testing
     */
    private function createTestRequest($headers, $data)
    {
        // Create a new request instance
        $request = new Request();

        // Set the request data
        $request->merge($data);

        // Set headers
        foreach ($headers as $key => $value) {
            $request->headers->set($key, $value);
        }

        // Set method
        $request->setMethod('POST');

        return $request;
    }
}
