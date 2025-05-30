<?php

namespace App\Services\BRI;

use App\Traits\MessageResponseTrait;
use Exception;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

class EDCService
{
    use MessageResponseTrait;

    private string $secretKey = "ECR2022secretKey";
    private string $edcAddress;
    private int $port;
    private bool $isSecure;
    private string $posAddress;
    private int $timeout = 30; // timeout in seconds

    public function __construct(string $edcAddress, string $posAddress, bool $isSecure = false)
    {
        $this->edcAddress = $edcAddress;
        $this->posAddress = $posAddress;
        $this->isSecure = $isSecure;
        $this->port = $isSecure ? 6746 : 6745;
    }

    public function sale($data)
    {
        $data = [
            'amount' => $data['amount'],
            'action' => 'Sale',
            'trx_id' => $this->generateTrxID($data['registration_no']),
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'],
        ];

        return $this->sendRequest($data);
    }

    public function contactless($data): void
    {
        $data = [
            'amount' => $data['amount'],
            'action' => 'Contactless',
            'trx_id' => $this->generateTrxID($data['registration_no']),
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => 'purchase'
        ];

        $this->sendRequest($data);
    }

    public function void($data)
    {
        $data = [
            'action' => 'Void',
            'trace_number' => $data['trace_number'],
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //purchase / brizzi
        ];

        $this->sendRequest($data);
    }

    public function checkStatusQR($data)
    {
        $data = [
            'action' => 'Check Status',
            'reference_number' => $data['reference_number'],
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //qris
        ];

        $this->sendRequest($data);
    }

    public function refundQR($data)
    {
        $data = [
            'action' => 'Refund Qris',
            'reff_id' => $data['reff_id'],
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //qris
        ];

        $this->sendRequest($data);
    }

    public function reprintLast($data)
    {
        $data = [
            'action' => 'Reprint Last',
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //Purchase / Brizzi
        ];

        $this->sendRequest($data);
    }

    public function reprintAnyTransaction($data)
    {
        $data = [
            'action' => 'Reprint Any',
            'trace_number' => $data['trace_number'],
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //Purchase / Brizzi
        ];

        $this->sendRequest($data);
    }

    public function settlement($data)
    {
        $data = [
            'action' => 'Settlement',
            'pos_address' => $this->posAddress,
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //Purchase / Brizzi
        ];

        $this->sendRequest($data);
    }

    /**
     * Send request to ECRLink via WebSocket synchronously
     *
     * @param array $data Request data
     * @param string $transactionId Transaction ID for tracking
     * @return array Response from EDC
     * @throws Exception
     */
    private function sendRequest(array $data): array
    {
        $encryptedData = $this->encryptData(json_encode($data));
        $protocol = $this->isSecure ? 'wss' : 'ws';
        $uri = "$protocol://{$this->edcAddress}:{$this->port}/app/qwerty";

        Log::info('Connecting to ECRLink synchronously', [
            'uri' => $uri,
            'data' => $data,
        ]);

        $loop = Loop::get();
        $connector = new Connector($loop);

        // Variable to store the response
        $response = null;
        $error = null;
        $completed = false;

        // Start WebSocket connection
        $connection = $connector($uri);

        // Set timeout
        $timeoutTimer = $loop->addTimer($this->timeout, function () use (&$error, &$completed, $data) {
            if (!$completed) {
                $error = 'Connection timeout after ' . $this->timeout . ' seconds';
                $completed = true;

                Log::error('Connection timeout', [
                    'data' => $data,
                    'timeout' => $this->timeout
                ]);
            }
        });

        // Handle successful connection
        $connection->then(
            function ($conn) use ($encryptedData, $data, $loop, &$response, &$error, &$completed, $timeoutTimer) {
                Log::info('Connected to ECRLink WebSocket', [
                    'data' => $data,
                ]);

                // Handle incoming messages
                $conn->on('message', function ($msg) use ($conn, $data, $loop, &$response, &$completed, $timeoutTimer) {
                    Log::info('Received message from ECRLink', [
                        'data' => $data,
                        'message_length' => strlen($msg),
                    ]);

                    // Cancel timeout timer
                    $loop->cancelTimer($timeoutTimer);

                    // Parse JSON response
                    $parsedResponse = json_decode($msg, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $response = $parsedResponse;
                    } else {
                        $response = [
                            'status' => 'error',
                            'message' => 'Invalid JSON response from EDC',
                            'raw_response' => $msg
                        ];
                    }

                    $completed = true;
                    $conn->close();
                });

                // Handle connection errors
                $conn->on('error', function ($connectionError) use (&$error, &$completed, $data, $loop, $timeoutTimer) {
                    Log::error('WebSocket error', [
                        'data' => $data,
                        'error' => $connectionError->getMessage()
                    ]);

                    $loop->cancelTimer($timeoutTimer);
                    $error = 'WebSocket error: ' . $connectionError->getMessage();
                    $completed = true;
                });

                // Handle connection close
                $conn->on('close', function () use ($data, &$completed) {
                    Log::info('WebSocket connection closed', [
                        'data' => $data,
                    ]);

                    if (!$completed) {
                        $completed = true;
                    }
                });

                // Send data to server
                $conn->send($encryptedData);

                Log::info('Sent encrypted data to ECRLink', [
                    'data' => $data
                ]);
            },
            function ($connectionError) use (&$error, &$completed, $data, $loop, $timeoutTimer) {
                Log::error('Failed to connect to ECRLink', [
                    'data' => $data,
                    'error' => $connectionError->getMessage()
                ]);

                $loop->cancelTimer($timeoutTimer);
                $error = 'Connection failed: ' . $connectionError->getMessage();
                $completed = true;
            }
        );

        // Run the event loop until completion
        while (!$completed) {
            //$loop->tick();
            $loop->run(true);
            usleep(10000); // Berikan jeda 10ms untuk mengurangi penggunaan CPU
        }
        //if ($loop->futureTick(function () {})) {
        //    // Jalankan loop hanya jika ada event tertunda
        //    $loop->run();
        //}

        // Handle the result
        if ($error) {
            // Trigger payment failed event
            //event(new PaymentFailedEvent([
            //    'transaction_id' => $transactionId,
            //    'method' => $data['method'],
            //    'action' => $data['action'],
            //    'error' => $error,
            //]));

            throw new Exception($error);
        }

        if ($response) {
            // Check if payment was successful
            $success = isset($response['status']) &&
                in_array(strtolower($response['status']), ['success', 'paid', 'refund']);

            if ($success) {
                Log::info('Transaction completed successfully', [
                    'data' => $data,
                    'response' => $response
                ]);
                // Trigger payment completed event
                // event(new PaymentCompletedEvent([
                //     'transaction_id' => $transactionId,
                //     'response' => $response,
                // ]));
            } else {
                // Trigger payment failed event
                // event(new PaymentFailedEvent([
                //     'transaction_id' => $transactionId,
                //     'response' => $response,
                //     'error' => $response['msg'] ?? $response['message'] ?? 'Unknown error',
                // ]));
            }

            return $response;
        }

        // If we reach here, something went wrong
        throw new Exception('No response received from EDC');
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

    /**
     * Set timeout for synchronous operations
     *
     * @param int $timeout Timeout in seconds
     * @return void
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Generate a transaction ID based on the registration number
     */
    private function generateTrxID($registrationNo)
    {
        $exploadRegistrationNo = explode("/", trim($registrationNo)); //13 digit
        $randomSuffix = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $registrationNo = $exploadRegistrationNo[1] . $exploadRegistrationNo[2] . $randomSuffix;
        return $registrationNo;
    }
}
