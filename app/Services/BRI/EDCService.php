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
    private string $connectionType = 'wifi';
    private array $connectionConfig = [];
    private string $connectionSecret;
    private string $connectionTimeOut;

    public function __construct()
    {
        $connectionConfig = [];
        switch (config('edc.connection')) {
            case 'wifi':
                $connectionConfig = $this->getWifiConfig();
                break;
            case 'bluetooth':
                $connectionConfig = $this->getBluetoothConfig();
                break;
            case 'serialUSB':
                $connectionConfig = $this->getUSBConfig();
                break;
            default:
                throw new Exception('Invalid EDC connection type: ' . config('edc.connection'));
        }

        $this->connectionType = config('edc.connection', 'wifi');
        $this->connectionSecret = config('edc.secret_key');
        $this->connectionTimeOut = config('edc.timeout', 30); // Default timeout is 30 seconds
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * Auto detect USB serial ports (Linux/Unix systems)
     */
    public function detectUSBPorts(): array
    {
        $ports = [];

        // Check common USB serial device paths
        $commonPaths = ['/dev/ttyUSB*', '/dev/ttyACM*', '/dev/cu.usbserial*', '/dev/cu.usbmodem*'];

        foreach ($commonPaths as $pattern) {
            $found = glob($pattern);
            if ($found) {
                $ports = array_merge($ports, $found);
            }
        }

        return $ports;
    }

    public function sale($data)
    {
        $data = [
            'amount' => $data['amount'],
            'action' => 'Sale',
            'trx_id' => $this->generateTrxID($data['registration_no']),
            'pos_address' => $this->connectionConfig['pos_address'],
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
            'pos_address' => $this->connectionConfig['pos_address'],
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
            'pos_address' => $this->connectionConfig['pos_address'],
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
            'pos_address' => $this->connectionConfig['pos_address'],
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
            'pos_address' => $this->connectionConfig['pos_address'],
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //qris
        ];

        $this->sendRequest($data);
    }

    public function reprintLast($data)
    {
        $data = [
            'action' => 'Reprint Last',
            'pos_address' => $this->connectionConfig['pos_address'],
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
            'pos_address' => $this->connectionConfig['pos_address'],
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //Purchase / Brizzi
        ];

        $this->sendRequest($data);
    }

    public function settlement($data)
    {
        $data = [
            'action' => 'Settlement',
            'pos_address' => $this->connectionConfig['pos_address'],
            'time_stamp' => now()->format('Y-m-d H:i:s'),
            'method' => $data['method'] //Purchase / Brizzi
        ];

        $this->sendRequest($data);
    }

    /**
     * Send request to ECRLink via WebSocket or USB Serial
     */
    private function sendRequest(array $data): array
    {
        if ($this->connectionType === 'usb') {
            return $this->sendUSBRequest($data);
        } else {
            return $this->sendWebSocketRequest($data);
        }
    }

    /**
     * Send request via USB Serial Connection
     */
    private function sendUSBRequest(array $data): array
    {
        $encryptedData = $this->encryptData(json_encode($data));

        Log::info('Sending data via USB Serial', [
            'port' => $this->connectionConfig['port'],
            'data' => $data,
        ]);

        try {
            // Check if port exists and is accessible
            if (!file_exists($this->connectionConfig['port'])) {
                throw new Exception("USB port {$this->connectionConfig['port']} not found. Available ports: " . implode(', ', $this->detectUSBPorts()));
            }

            // Configure serial port using stty command
            $this->configureUSBPort();

            // Open serial port for writing and reading
            $handle = fopen($this->connectionConfig['port'], 'r+b');

            if (!$handle) {
                throw new Exception("Failed to open USB port: {$this->connectionConfig['port']}");
            }

            // Set stream timeout
            stream_set_timeout($handle, $this->connectionTimeOut);

            // Send encrypted data with newline terminator
            $bytesWritten = fwrite($handle, $encryptedData . "\n");

            if ($bytesWritten === false) {
                throw new Exception("Failed to write data to USB port");
            }

            Log::info('Data sent via USB', [
                'bytes_written' => $bytesWritten,
                'data_length' => strlen($encryptedData)
            ]);

            // Read response
            $response = $this->readUSBResponse($handle, $data);

            fclose($handle);

            return $response;
        } catch (Exception $e) {
            Log::error('USB communication error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Configure USB port settings using stty
     */
    private function configureUSBPort(): void
    {
        $paritySetting = $this->connectionConfig['parity'] === 'none' ? '-parenb' : 'parenb';
        $stopBitsSetting = $this->connectionConfig['stop_bits'] == 2 ? 'cstopb' : '-cstopb';

        $command = sprintf(
            'stty -F %s %d cs%d %s %s -echo raw',
            escapeshellarg($this->connectionConfig['port']),
            $this->connectionConfig['baud_rate'],
            $this->connectionConfig['data_bits'],
            $paritySetting,
            $stopBitsSetting
        );

        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Failed to configure USB port: " . implode("\n", $output));
        }

        Log::info('USB port configured', [
            'port' => $this->connectionConfig['port'],
            'baud_rate' => $this->connectionConfig['baud_rate'],
            'data_bits' => $this->connectionConfig['data_bits'],
            'stop_bits' => $this->connectionConfig['stop_bits'],
            'parity' => $this->connectionConfig['parity']
        ]);
    }

    /**
     * Read response from USB port
     */
    private function readUSBResponse($handle, array $originalData): array
    {
        $response = '';
        $startTime = time();

        Log::info('Reading USB response', ['timeout' => $this->connectionTimeOut]);

        while (time() - $startTime < $this->connectionTimeOut) {
            $data = fread($handle, 1024);

            if ($data !== false && strlen($data) > 0) {
                $response .= $data;

                // Check if we have a complete message (ends with newline)
                if (strpos($response, "\n") !== false) {
                    break;
                }
            }

            // Small delay to prevent excessive CPU usage
            usleep(20000); // 20ms delay, same as Java code
        }

        if (empty($response)) {
            throw new Exception('No response received from EDC via USB after ' . $this->connectionTimeOut . ' seconds');
        }

        // Clean up response (remove newlines and whitespace)
        $response = trim($response);

        Log::info('USB response received', [
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 100) . (strlen($response) > 100 ? '...' : '')
        ]);

        // Try to decrypt and parse response
        try {
            // If response is encrypted, decrypt it first
            if ($this->isBase64($response)) {
                $decryptedResponse = $this->decryptData($response);
                $parsedResponse = json_decode($decryptedResponse, true);
            } else {
                // If response is plain JSON
                $parsedResponse = json_decode($response, true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid JSON response from EDC',
                    'raw_response' => $response
                ];
            }

            return $parsedResponse;
        } catch (Exception $e) {
            Log::error('Failed to parse USB response', [
                'error' => $e->getMessage(),
                'response' => $response
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to parse response: ' . $e->getMessage(),
                'raw_response' => $response
            ];
        }
    }

    /**
     * Check if string is base64 encoded
     */
    private function isBase64(string $data): bool
    {
        return base64_encode(base64_decode($data, true)) === $data;
    }

    /**
     * Decrypt data using AES/ECB/PKCS5Padding
     */
    private function decryptData(string $encryptedData): string
    {
        $key = $this->generateKey($this->secretKey);
        $decodedData = base64_decode($encryptedData);
        $decryptedData = openssl_decrypt($decodedData, 'aes-128-ecb', $key, OPENSSL_RAW_DATA, "");

        if ($decryptedData === false) {
            throw new Exception('Failed to decrypt response data');
        }

        return $decryptedData;
    }

    /**
     * Send request to ECRLink via WebSocket synchronously
     *
     * @param array $data Request data
     * @param string $transactionId Transaction ID for tracking
     * @return array Response from EDC
     * @throws Exception
     */
    private function sendWebSocketRequest(array $data): array
    {
        $endPoint = $this->connectionConfig['edc_address'] == 'localhost' ? '/app/qwerty' : '';
        $encryptedData = $this->encryptData(json_encode($data));
        $protocol = $this->connectionConfig['secure'] ? 'wss' : 'ws';
        $uri = "$protocol://{$this->connectionConfig['edc_address']}:{$this->connectionConfig['port']}$endPoint";

        Log::info('Connecting to ECRLink', [
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
        $timeoutTimer = $loop->addTimer($this->connectionTimeOut, function () use (&$error, &$completed, $data) {
            if (!$completed) {
                $error = 'Connection timeout after ' . $this->connectionTimeOut . ' seconds';
                $completed = true;

                Log::error('Connection timeout', [
                    'data' => $data,
                    'timeout' => $this->connectionTimeOut
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
            Log::info('Received response from ECRLink', [
                'data' => $data,
                'response' => $response
            ]);
            // // Check if payment was successful
            // $success = isset($response['status']) &&
            //     in_array(strtolower($response['status']), ['success', 'paid', 'refund']);

            // if ($success) {
            //     Log::info('Transaction completed successfully', [
            //         'data' => $data,
            //         'response' => $response
            //     ]);
            //     // Trigger payment completed event
            //     event(new PaymentCompletedEvent([
            //         'transaction_id' => $transactionId,
            //         'response' => $response,
            //     ]));
            // } else {
            //     // Trigger payment failed event
            //     event(new PaymentFailedEvent([
            //         'transaction_id' => $transactionId,
            //         'response' => $response,
            //         'error' => $response['msg'] ?? $response['message'] ?? 'Unknown error',
            //     ]));
            // }

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
        $this->connectionTimeOut = $timeout;
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

    /**
     * Get available Bluetooth ports
     */
    private function getBluetoothPort(): string
    {
        // For Linux/Mac
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            // Check for available Bluetooth serial ports
            $ports = ['/dev/rfcomm0', '/dev/rfcomm1', '/dev/ttyUSB0'];
            foreach ($ports as $port) {
                if (file_exists($port)) {
                    return $port;
                }
            }
            throw new Exception('No Bluetooth serial port found');
        }

        // For Windows
        if (PHP_OS_FAMILY === 'Windows') {
            // Check for available COM ports
            $output = shell_exec('wmic path Win32_SerialPort get DeviceID 2>nul');
            if ($output && preg_match('/COM\d+/', $output, $matches)) {
                return $matches[0];
            }
            throw new Exception('No Bluetooth COM port found');
        }

        throw new Exception('Unsupported OS for Bluetooth detection');
    }

    /**
     * Get available USB serial ports
     */
    private function getUSBPort(): string
    {
        // For Linux/Mac
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $ports = ['/dev/ttyUSB0', '/dev/ttyUSB1', '/dev/ttyACM0', '/dev/cu.usbserial-*'];
            foreach ($ports as $port) {
                if (str_contains($port, '*')) {
                    // Handle wildcard patterns
                    $matchingPorts = glob($port);
                    if (!empty($matchingPorts)) {
                        return $matchingPorts[0];
                    }
                } elseif (file_exists($port)) {
                    return $port;
                }
            }
            throw new Exception('No USB serial port found');
        }

        // For Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic path Win32_SerialPort get DeviceID 2>nul');
            if ($output && preg_match('/COM\d+/', $output, $matches)) {
                return $matches[0];
            }
            throw new Exception('No USB COM port found');
        }

        throw new Exception('Unsupported OS for USB detection');
    }

    public function getWifiConfig(): array
    {
        return [
            'edc_address' => config('edc.wifi.edc_address'),
            'pos_address' => config('edc.wifi.pos_address'),
            'secure' => config('edc.wifi.secure', false),
            'port' => config('edc.wifi.port', 6745),
        ];
    }

    public function getBluetoothConfig(): array
    {
        return [
            'serial_port' => $this->getBluetoothPort(), //'/dev/rfcomm0', // Linux/Mac //'COM3', // Windows
        ];
    }

    public function getUSBConfig(): array
    {
        return [
            'serial_port' => $this->getUSBPort(), //'/dev/ttyUSB0', // Linux/Mac //'COM1', // Windows
            'baud_rate' => config('edc.usb.baud_rate', 115200), // 9600, 115200
            'data_bits' => config('edc.usb.data_bits', 8), // 7=Seven, 8=Eight
            'stop_bits' => config('edc.usb.stop_bits', 1), // 1=One, 2=Two
            'parity' => config('edc.usb.parity', 0), // 0=None, 1=Odd, 2=Even
        ];
    }
}
