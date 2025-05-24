<?php

namespace App\Libraries\BRI;

use App\Events\APM\EDC\PaymentCompletedEvent;
use App\Events\APM\EDC\PaymentFailedEvent;
use App\Traits\MessageResponseTrait;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

class ECRLinkService
{
    use MessageResponseTrait;

    private string $secretKey = "ECR2022secretKey";
    private string $edcAddress;
    private int $port;
    private bool $isSecure;
    private string $posAddress;

    public function __construct(string $edcAddress, string $posAddress, bool $isSecure = false)
    {
        $this->edcAddress = $edcAddress;
        $this->posAddress = $posAddress;
        $this->isSecure = $isSecure;
        $this->port = $isSecure ? 6746 : 6745;
    }

    /**
     * Process a sale transaction asynchronously
     *
     * @param int $amount Transaction amount
     * @param string $transactionId Unique transaction ID from POS
     * @param string $method Payment method (purchase, brizzi, qris)
     * @return void
     */
    public function sale(int $amount, string $transactionId, string $method = 'purchase'): void
    {
        $data = [
            'amount' => $amount,
            'action' => 'Sale',
            'trx_id' => $transactionId,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $transactionId);
    }

    /**
     * Process a contactless transaction (tap payment) asynchronously
     *
     * @param int $amount Transaction amount
     * @param string $transactionId Unique transaction ID from POS
     * @return void
     */
    public function contactless(int $amount, string $transactionId): void
    {
        $data = [
            'amount' => $amount,
            'action' => 'Contactless',
            'trx_id' => $transactionId,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => 'purchase'
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $transactionId);
    }

    /**
     * Process a contactless transaction (tap payment) asynchronously
     *
     * @param string $transactionId Unique transaction ID from POS
     * @return void
     */
    public function void(string $traceNumber, $method): void
    {
        $data = [
            'action' => 'Void',
            'trace_number' => $traceNumber,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method //purchase / brizzi
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $traceNumber);
    }

    public function checkStatusQR(string $referenceNumber, $method): void
    {
        $data = [
            'action' => 'Check Status',
            'reference_number' => $referenceNumber,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method
            //'amount' => $amount,
            //'trx_id' => $transactionId,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $referenceNumber);
    }

    public function refundQR(string $reffID, $method): void
    {
        $data = [
            'action' => 'Refund Qris',
            'reff_id' => $reffID,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method
            //'amount' => $amount,
            //'trx_id' => $transactionId,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $reffID);
    }

    public function reprintLast(string $method): void
    {
        $data = [
            'action' => 'Reprint Last',
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method //Purchase / Brizzi
            //'amount' => $amount,
            //'trx_id' => $transactionId,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, '');
    }

    public function reprintAnyTransaction(string $referenceNumber, $method): void
    {
        $data = [
            'action' => 'Reprint Any',
            'reference_number' => $referenceNumber,
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method //Purchase / Brizzi
            //'amount' => $amount,
            //'trx_id' => $transactionId,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, $referenceNumber);
    }

    public function settlement(string $method): void
    {
        $data = [
            'action' => 'Settlement',
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $method //Purchase / Brizzi
            //'amount' => $amount,
            //'trx_id' => $transactionId,
        ];

        // Mulai proses asinkron tanpa menunggu hasil
        $this->sendRequestAsync($data, '');
    }

    /**
     * Send request to ECRLink via WebSocket asynchronously
     *
     * @param array $data Request data
     * @param string $transactionId Transaction ID for tracking
     * @return void
     */
    private function sendRequestAsync(array $data, string $transactionId): void
    {
        $encryptedData = $this->encryptData(json_encode($data));
        $protocol = $this->isSecure ? 'wss' : 'ws';
        $uri = "$protocol://{$this->edcAddress}:{$this->port}/app/qwerty";

        Log::info('Connecting to ECRLink asynchronously', [
            'uri' => $uri,
            'transaction_id' => $transactionId,
            'method' => $data['method'],
            'action' => $data['action'],

        ]);

        // Gunakan loop terpisah yang tidak akan memblokir thread utama
        $loop = Loop::get();
        $connector = new Connector($loop);

        // Mulai koneksi WebSocket asinkron
        $connection = $connector($uri);

        // Menangani koneksi berhasil
        $connection->then(
            function ($conn) use ($encryptedData, $data, $transactionId, $loop) {
                Log::info('Connected to ECRLink WebSocket', [
                    'transaction_id' => $transactionId,
                    'method' => $data['method'],
                    'action' => $data['action'],

                ]);

                // Set timeout untuk koneksi
                $timeoutTimer = $loop->addTimer(30, function () use ($conn, $transactionId, $data) {
                    Log::error('Connection timeout after 30 seconds', [
                        'transaction_id' => $transactionId,
                        'method' => $data['method'],
                        'action' => $data['action'],
                    ]);

                    // Trigger event payment failed
                    event(new PaymentFailedEvent([
                        'transaction_id' => $transactionId,
                        'error' => 'Connection timeout after 30 seconds',
                    ]));

                    if ($conn && $conn->isConnected()) {
                        $conn->close();
                    }
                });

                // Menangani pesan yang diterima
                $conn->on('message', function ($msg) use ($conn, $transactionId, $data, $loop, $timeoutTimer) {
                    Log::info('Received message from ECRLink', [
                        'transaction_id' => $transactionId,
                        'method' => $data['method'],
                        'action' => $data['action'],
                        'message_length' => strlen($msg),
                    ]);

                    // Batalkan timer timeout
                    $loop->cancelTimer($timeoutTimer);

                    // Parse respons JSON
                    $response = json_decode($msg, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Cek status transaksi
                        $success = isset($response['status']) &&
                            in_array($response['status'], ['success', 'paid', 'approved']);

                        if ($success) {
                            Log::info('Payment completed successfully', [
                                'transaction_id' => $transactionId,
                                'method' => $data['method'],
                                'action' => $data['action'],
                                'response' => $response
                            ]);
                            // Trigger event payment completed
                            event(new PaymentCompletedEvent([
                                'transaction_id' => $transactionId,
                                'response' => $response,
                            ]));
                        } else {
                            // Trigger event payment failed
                            event(new PaymentFailedEvent([
                                'transaction_id' => $transactionId,
                                'response' => $response,
                                'error' => $response['msg'] ?? 'Unknown error',
                            ]));
                        }
                    } else {
                        // Trigger event payment failed dengan error parsing JSON
                        event(new PaymentFailedEvent([
                            'transaction_id' => $transactionId,
                            'error' => 'Invalid JSON response from EDC',
                            'raw_response' => $msg,
                            'timestamp' => now()->format('Y-m-d H:i:s')
                        ]));
                    }

                    // Tutup koneksi setelah menerima respons
                    $conn->close();
                });

                // Menangani kesalahan koneksi
                $conn->on('error', function ($error) use ($transactionId, $data, $loop, $timeoutTimer) {
                    Log::error('WebSocket error', [
                        'transaction_id' => $transactionId,
                        'method' => $data['method'],
                        'action' => $data['action'],
                        'error' => $error->getMessage()
                    ]);

                    // Batalkan timer timeout
                    $loop->cancelTimer($timeoutTimer);

                    // Trigger event payment failed
                    event(new PaymentFailedEvent([
                        'transaction_id' => $transactionId,
                        'error' => 'WebSocket error: ' . $error->getMessage(),
                    ]));
                });

                // Menangani penutupan koneksi
                $conn->on('close', function () use ($transactionId, $timeoutTimer, $loop, $data) {
                    Log::info('WebSocket connection closed', [
                        'transaction_id' => $transactionId,
                        'method' => $data['method'],
                        'action' => $data['action'],
                    ]);

                    // Batalkan timer timeout
                    if (isset($timeoutTimer)) {
                        $loop->cancelTimer($timeoutTimer);
                    }
                });

                // Kirim data ke server
                $conn->send($encryptedData);

                Log::info('Sent encrypted data to ECRLink', [
                    'transaction_id' => $transactionId,
                    'method' => $data['method'],
                    'action' => $data['action'],
                ]);
            },
            function ($error) use ($transactionId, $data) {
                Log::error('Failed to connect to ECRLink', [
                    'transaction_id' => $transactionId,
                    'method' => $data['method'],
                    'action' => $data['action'],
                    'error' => $error->getMessage()
                ]);

                // Trigger event payment failed
                event(new PaymentFailedEvent([
                    'transaction_id' => $transactionId,
                    'method' => $data['method'] ?? 'unknown',
                    'error' => 'Connection failed: ' . $error->getMessage(),
                    'timestamp' => now()->format('Y-m-d H:i:s')
                ]));
            }
        );

        // PERUBAHAN KRITIS: Gunakan metode yang berbeda untuk menjalankan loop
        // tanpa memblokir thread utama

        // Jadwalkan loop untuk dijalankan setelah request selesai (di background)
        // Ini mencegah pemblokiran thread utama
        // register_shutdown_function(function() use ($loop) {
        //     // Periksa apakah masih ada event yang tertunda
        if ($loop->futureTick(function () {})) {
            // Jalankan loop hanya jika ada event tertunda
            $loop->run();
        }
        // });
    }

    /**
     * Encrypt data using AES/ECB/PKCS5Padding
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data in Base64
     */
    private function encryptData(string $data): string
    {
        $key = $this->generateKey($this->secretKey);
        $encryptedData = openssl_encrypt($data, 'aes-128-ecb', $key, OPENSSL_RAW_DATA, "");
        return base64_encode($encryptedData);
    }

    /**
     * Generate encryption key using SHA-1 and trimming to 16 bytes
     *
     * @param string $secretKey Secret key
     * @return string Generated key
     */
    private function generateKey(string $secretKey): string
    {
        $key = $secretKey;
        $sha = sha1($key, true);
        return substr($sha, 0, 16);
    }
}
