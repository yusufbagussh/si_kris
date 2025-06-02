<?php

namespace App\Http\Middleware;

use App\Traits\MessageResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySignatureApi
{
    use MessageResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil header yang diperlukan
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $apiKey = $request->header('X-API-Key');

        // Validasi header yang wajib ada
        if (!$signature || !$timestamp || !$apiKey) {
            return response()->json([
                'error' => 'Missing required headers',
                'required_headers' => ['X-Signature', 'X-Timestamp', 'X-API-Key']
            ], 401);
        }

        // Cari secret key berdasarkan API key
        $secretKey = $this->getSecretKey($apiKey);
        if (!$secretKey) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }
        Log::info('[200][Middleware][VerifySignatureApi][handle] API Key: ' . $secretKey);
        // Validasi timestamp untuk mencegah replay attacks
        if (!$this->isTimestampValid($timestamp)) {
            return response()->json(['error' => 'Request timestamp expired'], 401);
        }

        // Ambil semua parameter untuk signature
        $params = $request->all();

        // Verify signature
        if (!$this->verifySignature($signature, $params, $secretKey, $timestamp)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Simpan informasi client untuk digunakan di controller
        $request->attributes->set('api_client', [
            'api_key' => $apiKey,
            'timestamp' => $timestamp
        ]);

        return $next($request);
    }

    /**
     * Ambil secret key berdasarkan API key
     * Bisa dari database, cache, atau config
     */
    private function getSecretKey(string $apiKey): ?string
    {
        $clients = config('signature.soba');
        Log::info('[200][Middleware][VerifySignatureApi][getSecretKey] API Key: ' . $apiKey);
        return $clients[$apiKey]['secret_key'] ?? null;
    }

    /**
     * Generate signature untuk request
     */
    private static function generateSignature(array $params, string $secretKey, string $timestamp): string
    {
        //hashing the parameters to create a consistent string
        $hashString = strtolower(hash('sha256', json_encode($params)));

        // Create string to sign: HTTP_METHOD|URI|QUERY_STRING|TIMESTAMP
        $stringToSign = request()->method() . '|' .
            request()->getPathInfo() . '|' .
            $hashString . '|' .
            $timestamp;

        // Generate HMAC-SHA256 signature
        return hash_hmac('sha512', $stringToSign, $secretKey);
    }

    /**
     * Verify signature dari request
     */
    private static function verifySignature(string $receivedSignature, array $params, string $secretKey, string $timestamp): bool
    {
        $expectedSignature = self::generateSignature($params, $secretKey, $timestamp);
        Log::info('Comparing signatures: ' . $expectedSignature . ' vs ' . $receivedSignature);
        // Use hash_equals untuk mencegah timing attacks
        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Check apakah timestamp masih valid (untuk mencegah replay attacks)
     */
    private static function isTimestampValid(string $timestamp, int $toleranceInSeconds = 300): bool
    {
        $currentTime = time();
        $requestTime = strtotime($timestamp);

        return abs($currentTime - $requestTime) <= $toleranceInSeconds;
    }
}
