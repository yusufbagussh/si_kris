<?php

namespace App\Services\BRI;

use App\Models\QrisBriToken;
use App\Models\QrisInquiry;
use App\Models\QrisPayment;
use App\Traits\MessageResponseTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QRISNotificationService
{
    private $clientIdBri;
    private $clientSecretBri;
    private $channelIdBri;
    private $partnerIdBri;
    private $timeStamp;
    private $externalId;
    private $privateKeyPath;

    public function __construct()
    {
        $this->clientIdBri = config('qris.partners.bri.webhook.client_id');
        $this->clientSecretBri = config('qris.partners.bri.webhook.client_secret');
        $this->partnerIdBri = config('qris.partners.bri.webhook.partner_id');
        $this->channelIdBri = config('services.bri.channel_id');
        $this->timeStamp = now()->toIso8601String();
        $this->externalId = $this->generateExternalId();
        $this->privateKeyPath = storage_path('app/private/keys/private_key.pem');

    }

    private function generateExternalId()
    {
        return (string)mt_rand(1000000000000000, 9999999999999999) . mt_rand(1000000000000000, 9999999999999999);
    }

    public function generateSignatureNotify($request)
    {
        $authHeader = $request->header('Authorization');
        $token = Str::substr($authHeader, 7);
        $timestamp = $this->timeStamp;
        $endpoint = "/api/snap/v1.1/qr/qr-mpm-notify";
        $body = $request->all();

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
}
