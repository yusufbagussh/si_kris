<?php

namespace App\Libraries\BRI;

use App\Models\QrisBriToken;
use App\Models\QrisInquiry;
use App\Models\QrisPayment;
use App\Traits\MessageResponseTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QRISService
{
    use MessageResponseTrait;

    private $baseUrl;
    private $clientId;
    private $clientSecret;
    private $clientIdBri;
    private $channelIdBri;
    private $clientSecretBri;
    private $partnerIdBri;
    private $partnerId;
    private $channelId;
    private $externalId;
    private $merchantId;
    private $terminalId;
    private $privateKeyPath;
    private $publicKeyPath;
    private $timeStamp;

    private QrisBriToken $qrisBriToken;

    public function __construct()
    {
        $this->clientIdBri = env('BRI_CLIENT_ID');
        $this->clientSecretBri = env('BRI_CLIENT_SECRET');
        $this->partnerIdBri = env('BRI_PARTNER_ID');
        $this->channelIdBri = env('BRI_CHANNEL_ID');
        $this->baseUrl = env('QRIS_BASE_URL');
        $this->clientId = env('QRIS_CLIENT_ID');
        $this->clientSecret = env('QRIS_CLIENT_SECRET');
        $this->partnerId = env('QRIS_PARTNER_ID');
        $this->channelId = env('QRIS_CHANNEL_ID'); //env('QRIS_CHANNEL_ID'); // Channel ID untuk QRIS
        $this->externalId = $this->generateExternalId(); //env('QRIS_EXTERNAL_ID'); // External ID untuk QRIS
        $this->terminalId = env('QRIS_TERMINAL_ID');
        $this->merchantId = env('QRIS_MERCHANT_ID');
        $this->privateKeyPath = storage_path('app/private/keys/private_key.pem'); // Simpan private key di storage/keys
        $this->publicKeyPath = storage_path('app/private/keys/public_key.pem');; // Simpan private key di storage/keys
        $this->timeStamp = now()->toIso8601String();
        // $this->timeStamp = now()->format('Y-m-d\TH:i:s.vP');

        $this->qrisBriToken = new QrisBriToken();
    }

    // 1. GET ACCESS TOKEN

    // GENERATE EXTERNAL ID
    public function getAccessToken()
    {
        try {
            $headers = $this->getBaseHeaders();
            $response = Http::withHeaders($headers)->post($this->baseUrl . '/snap/v1.0/access-token/b2b', [
                'grantType' => 'client_credentials',
            ]);

            //return $response->json()['accessToken'];
            if ($response->successful()) {
                $data = $response->json();

                // Save token to database for future use
                $this->saveAccessToken($data);

                return $data['accessToken'];
            }

            Log::error('Failed to get access token', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Error getting access token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function generateQR($token, $registrationNo, $totalAmount)
    {
        //$token = $this->getAccessTokenFromDB() ?? $this->getAccessToken();
        $partnerReferenceNo = $this->generatePartnerReferenceNoFromReg($registrationNo);
        $timestamp = $this->timeStamp;
        $endpoint = "/snap/v1.1/qr/qr-mpm-generate-qr";

        $formattedAmount = number_format($totalAmount, 2, '.', '');
        $body = [
            'partnerReferenceNo' => $partnerReferenceNo,
            'amount' => [
                'value' => $formattedAmount,
                'currency' => 'IDR',
            ],
            'merchantId' => $this->merchantId,
            'terminalId' => $this->terminalId,
        ];

        $headers = $this->getAuthHeaders($token, $endpoint, $body, $timestamp);
        //Log::info("INFO REQUEST GEN-QR QRIS BRIS");
        //Log::info("headers : " . json_encode($headers));
        //Log::info("body : " . json_encode($body));

        //return [
        //    'headers' => $headers,
        //    'body' => $body,
        //];

        $response = Http::withHeaders($headers)->post($this->baseUrl . $endpoint, $body);
        //Log::info("INFO RESPONSE GEN-QR QRIS BRIS");
        //Log::info("response : " . $response);
        if (!$response->successful()) {
            Log::error('Failed to query payment', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }

        return $response->json();
    }

    public function inquiryPayment($token, $originalReferenceNo)
    {
        $timestamp = $this->timeStamp;
        $endpoint = "/snap/v1.1/qr/qr-mpm-query";

        $body = [
            'originalReferenceNo' => $originalReferenceNo,
            'serviceCode' => '17', //17, 47, 51
            'additionalInfo' => [
                'terminalId' => $this->terminalId,
            ],
        ];

        $headers = $this->getAuthHeadersInquiry($token, $endpoint, $body, $timestamp);
        //Log::info("INFO REQUEST INQUIRY QRIS BRIS");
        //Log::info("headers : " . json_encode($headers));
        //Log::info("body : " . json_encode($body));
        //return [
        //    'headers' => $headers,
        //    'body' => $body,
        //];

        $response = Http::withHeaders($headers)->post($this->baseUrl . $endpoint, $body);

        //Log::info("INFO RESPONSE INQUIRY QRIS BRIS");
        //Log::info("response : " . $response);

        if ($response->successful()) {
            $this->savePaymentInquiry($response->json(), $originalReferenceNo, $this->terminalId, $originalReferenceNo);
        } else {
            Log::error('Failed to query payment', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }

        return $response->json();

        ////Dummy for testing
        // $response = [
        //     "responseCode" => "2005100",
        //     "responseMessage" => "Successful",
        //     "originalReferenceNo" => $originalReferenceNo,
        //     "serviceCode" => "17",
        //     "latestTransactionStatus" => "00",
        //     "transactionStatusDesc" => "Successfully",
        //     "amount" => [
        //         "value" => "100.00",
        //         "currency" => "IDR"
        //     ],
        //     "terminalId" => $this->terminalId,
        //     "additionalInfo" => [
        //         "customerName" => "I GEDE TONI DHARMAWAN",
        //         "customerNumber" => "9360015723456789",
        //         "invoiceNumber" => "10009121031000912103",
        //         "issuerName" => "Finnet 2",
        //         "issuerRrn" => "110002756582",
        //         "mpan" => "9360000201102921379"
        //     ]
        // ];
        // $this->savePaymentInquiry($response, $originalReferenceNo, $this->terminalId, $transaction);
        // return $response;
    }

    private function generatePartnerReferenceNo($medicalRecordNo)
    {

        $timestamp = now()->format('YmdHis'); // Contoh: 20250208123045 (12 digit)

        $medicalId = substr(str_replace("-", "", $medicalRecordNo), -8); // Contoh: 123456

        return $medicalId . $timestamp;
    }

    private function generatePartnerReferenceNoFromReg($registrationNo)
    {
        $exploadRegistrationNo = explode("/", trim($registrationNo)); //13 digit
        $randomSuffix = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $registrationNo = $exploadRegistrationNo[1] . $exploadRegistrationNo[2] . $randomSuffix;
        return $registrationNo;
    }


    private function generateExternalId()
    {
        return (string)mt_rand(1000000000000000, 9999999999999999) . mt_rand(1000000000000000, 9999999999999999);

        // 1. Ambil tanggal & waktu sekarang dalam format YYYYMMDDHHmmss
        //$timestamp = now()->format('YmdHis'); // Contoh: 20250208123045 (12 digit)

        //// 2. Gunakan 6 digit terakhir dari Nomor Rekam Medis
        //$medicalId = substr($medicalRecordNo, -6); // Contoh: 123456

        //// 3. Gunakan 3 digit terakhir dari Nomor Antrian
        //$queueId = str_pad(substr($queueNumber, -3), 3, '0', STR_PAD_LEFT); // Contoh: 789

        //// 4. Generate angka acak 6 digit untuk menghindari duplikasi
        //$randomPart = mt_rand(100000, 999999); // Contoh: 654321

        // 5. Gabungkan semua bagian untuk membentuk X-EXTERNAL-ID (maks 32 digit)
        //$externalId = $timestamp . $randomPart;

        // 6. Pastikan panjang maksimal 32 karakter
        //return substr($externalId, 0, 32);
    }

    /**
     * Get access token from database if not expired
     *
     * @return string|null
     */
    protected function getAccessTokenFromDB()
    {
        $token = $this->qrisBriToken->getAccessToken();

        return $token ? $token->token : null;
    }

    // 2. GENERATE QR


    // 3. INQUIRY PAYMENT

    private function getBaseHeaders()
    {
        // $timestamp = now()->toIso8601String();
        $timestamp = $this->timeStamp;
        $stringToSign = $this->clientId . "|" . $timestamp;
        $signature = $this->generateRSASignature($stringToSign);
        return [
            'Content-Type' => 'application/json',
            'X-CLIENT-KEY' => $this->clientId,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }

    // 4. GENERATE SIGNATURE (X-SIGNATURE)

    private function generateRSASignature($stringToSign)
    {
        $privateKey = file_get_contents($this->privateKeyPath);
        $keyResource = openssl_pkey_get_private($privateKey);

        if (!$keyResource) {
            throw new \Exception("Private key is invalid");
        }

        openssl_sign($stringToSign, $signature, $keyResource, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    // 5. HEADERS BASE (DIGUNAKAN UNTUK GET TOKEN)

    /**
     * Save access token to database
     *
     * @param array $tokenData
     * @return void
     */
    protected function saveAccessToken(array $tokenData)
    {
        QrisBriToken::create([
            'token' => $tokenData['accessToken'],
            'token_type' => $tokenData['tokenType'],
            'expires_in' => $tokenData['expiresIn'],
            'expires_at' => now()->addSeconds((int)$tokenData['expiresIn']),
        ]);
    }

    // 6. HEADERS AUTHENTICATION (UNTUK REQUEST API QRIS)

    private function getAuthHeaders($token, $endpoint, $body, $timestamp)
    {
        return [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-PARTNER-ID' => $this->partnerId,
            'CHANNEL-ID' => $this->channelId,
            'X-EXTERNAL-ID' => $this->externalId,
            'X-SIGNATURE' => $this->generateSignature('POST', $endpoint, $token, $body, $timestamp),
        ];
    }

    private function generateSignature($method, $endpoint, $token, $body, $timestamp)
    {
        // $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        // $hashedBody = hash('sha256', strtolower($bodyJson));

        $hashedBody = strtolower(hash("sha256", json_encode($body)));

        $stringToSign = "$method:$endpoint:$token:$hashedBody:$timestamp";
        Log::info("StringToSign: " . $stringToSign);
        Log::info("KEY HMAC: " . $this->clientSecret);
        // $signature = hash_hmac('sha512', $stringToSign, $this->clientSecret);
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));
        Log::info("Generated Signature: " . $signature);
        return $signature;
    }

    // 5. GENERATE CHANNEL ID (5-digit angka)


    // 6. GENERATE EXTERNAL ID (UUID v4 - 36 karakter)

    // 8. GENERATE SIGNATURE RSA (DIGUNAKAN UNTUK GET TOKEN)

    // 9. VERIFY SIGNATURE


    private function getAuthHeadersInquiry($token, $endpoint, $body, $timestamp)
    {
        // $token = "RuEZus895GRLSiXEHm5FvUQt7FJA";
        return [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-PARTNER-ID' => $this->partnerId,
            'CHANNEL-ID' => $this->channelId,
            'X-EXTERNAL-ID' => $this->externalId, //"25217973866465936505081444670742", //
            'X-SIGNATURE' => $this->generateSignature('POST', $endpoint, $token, $body, $timestamp),
        ];
    }

    /**
     * Save payment inquiry data to database
     *
     * @param array $responseData
     * @param string $referenceNo
     * @param string $terminalId
     * @return void
     */
    protected function savePaymentInquiry(array $responseData, string $referenceNo, string $terminalId, $originalReferenceNo)
    {
        // Find transaction by reference number
        $transaction = QrisPayment::where('original_reference_no', $originalReferenceNo)->first();

        // Convert transaction status code to readable status
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

        //Update data QRIS Transaction`
        $status = $statusMap[$responseData['latestTransactionStatus']] ?? 'UNKNOWN';
        $transaction->status = $status;
        $transaction->last_inquiry_at = now();
        $transaction->save();

        //// Update transaction status
        // $transaction->latest_transaction_status = $responseData['latestTransactionStatus'];
        // $transaction->transaction_status_desc = $responseData['transactionStatusDesc'] ?? null;

        // // Save additional info if present
        // if (isset($responseData['additionalInfo'])) {
        //     $transaction->customer_name = $responseData['additionalInfo']['customerName'] ?? null;
        //     $transaction->customer_number = $responseData['additionalInfo']['customerNumber'] ?? null;
        //     $transaction->invoice_number = $responseData['additionalInfo']['invoiceNumber'] ?? null;
        //     $transaction->issuer_name = $responseData['additionalInfo']['issuerName'] ?? null;
        //     $transaction->issuer_rrn = $responseData['additionalInfo']['issuerRrn'] ?? null;
        // }

        // Log inquiry
        QrisInquiry::create([
            'qris_transaction_id' => $transaction->id,
            'original_reference_no' => $referenceNo,
            'terminal_id' => $terminalId,
            'response_code' => $responseData['responseCode'],
            'response_message' => $responseData['responseMessage'],
            'service_code' => $responseData['serviceCode'],
            //'transaction_status' => $status,
            'latest_transaction_status' => $responseData['latestTransactionStatus'],
            'transaction_status_desc' => $responseData['transactionStatusDesc'] ?? null,
            'customer_name' => $responseData['additionalInfo']['customerName'] ?? null,
            'customer_number' => $responseData['additionalInfo']['customerNumber'] ?? null,
            'invoice_number' => $responseData['additionalInfo']['invoiceNumber'] ?? null,
            'issuer_name' => $responseData['additionalInfo']['issuerName'] ?? null,
            'issuer_rrn' => $responseData['additionalInfo']['issuerRrn'] ?? null,
            'mpan' => $responseData['additionalInfo']['mpan'] ?? null,
            //'raw_response' => json_encode($responseData),
        ]);
    }

    private function verifyRSASignature($encodedSignature, $stringToSign)
    {
        $publicKey = file_get_contents($this->publicKeyPath);
        $keyResource = openssl_pkey_get_public($publicKey);
        $signature = base64_decode($encodedSignature);

        if (!$keyResource) {
            throw new \Exception("Private key is invalid");
        }

        openssl_verify($stringToSign, $signature, $keyResource, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    public function generateSignatureNotify($request)
    {
        $authHeader = $request->header('Authorization');
        $token = Str::substr($authHeader, 7);
        $timestamp = $this->timeStamp;
        $endpoint = "/api/snap/v1.1/qr/qr-mpm-notify";
        $body = $request->all();


        //$hashedBody = strtolower(hash("sha256", json_encode($body)));
        $hashedBody = bin2hex(strtolower(hash('sha256', json_encode($body))));

        $stringToSign = "POST:$endpoint:$token:$hashedBody:$timestamp";
        //$stringToSign2 = "POST:/api/snap/v1.1/qr/qr-mpm-notify:WElcNRChF8zyCxDaaiWTooqWrENNWMhI:$hashedBody2:2025-05-05T10:33:38+07:00";

        Log::info("StringToSign: " . $stringToSign);
        Log::info("KEY HMAC: " . $this->clientSecretBri);
        // $signature = hash_hmac('sha512', $stringToSign, $this->$this->clientSecretBri);
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecretBri, true));
        //$signature2 = base64_encode(hash_hmac('sha512', $stringToSign2, $this->clientSecretBri, true));
        Log::info("Generated Signature: " . $signature);

        // $decodedHash = hex2bin('66656131363339303731356462323364663762346439376634643563386632333939336331336435393066326635363061663861386362646238366663313534');

        $headers = [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json',
            'X-TIMESTAMP' => $timestamp,
            'X-PARTNER-ID' => $this->partnerIdBri,
            'CHANNEL-ID' => $this->channelIdBri,
            'X-EXTERNAL-ID' => $this->externalId,
            'X-SIGNATURE' => $signature,
            //'X-SIGNATURE-2' => $signature2,
            //'HASHED' => $hashedBody,
            //'HASHEDV2' => $hashedBody2,
        ];

        return [
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function getSignatureAccessToken()
    {
        $timestamp = $this->timeStamp;
        $stringToSign = $this->clientIdBri . "|" . $timestamp;
        $signature = $this->generateRSASignature($stringToSign);
        return [
            'Content-Type' => 'application/json',
            'X-CLIENT-KEY' => $this->clientIdBri,
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $signature,
        ];
    }
}
