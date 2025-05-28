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

    public function getAccessToken()
    {
        $headers = $this->getBaseHeaders();
        $response = Http::withHeaders($headers)->post($this->baseUrl . '/snap/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            Log::error('Failed to get access token', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }
        return $response->json();
    }

    public function generateQR($token, $registrationNo, $totalAmount)
    {
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
        Log::info("INFO REQUEST GEN-QR QRIS BRIS :", [
            'headers' => $headers,
            'body' => $body,
        ]);

        $response = Http::withHeaders($headers)->post($this->baseUrl . $endpoint, $body);

        if (!$response->successful()) {
            Log::error('Failed to generate QR', [
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

        $headers = $this->getAuthHeaders($token, $endpoint, $body, $timestamp);
        //Log::info("INFO REQUEST INQUIRY QRIS BRIS : ", [
        //    'headers' => $headers,
        //    'body' => $body,
        //]);

        $response = Http::withHeaders($headers)->post($this->baseUrl . $endpoint, $body);
        //Log::info("INFO RESPONSE INQUIRY QRIS BRIS");
        //Log::info("response : " . $response);
        if (!$response->successful()) {
            Log::error('Failed to query payment', [
                'response' => $response->json(),
                'status' => $response->status()
            ]);
        }

        return $response->json();
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
        $hashedBody = strtolower(hash("sha256", json_encode($body)));
        $stringToSign = "$method:$endpoint:$token:$hashedBody:$timestamp";
        $signature = base64_encode(hash_hmac('sha512', $stringToSign, $this->clientSecret, true));
        return $signature;
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

    public function generateSignatureAccessToken()
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
