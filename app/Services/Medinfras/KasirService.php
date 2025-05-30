<?php

namespace App\Libraries\Medinfras;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Log;

class KasirService
{
    private $consId, $secretKey, $headers, $clients, $signature, $timestamp;

    public function __construct()
    {
        //if (env('HIS_MODE') == 'live') {
        //    $this->consId = env('API_MEDINFRAS_LIVE_CONS_ID');
        //    $this->secretKey = env('API_MEDINFRAS_LIVE_SECRET_KEY');
        //    $url = env('API_URL_MEDINFRAS_LIVE');
        //} else {
        $this->consId = env('API_MEDINFRAS_WS_CONS_ID');
        $this->secretKey = env('API_MEDINFRAS_WS_SECRET_KEY');
        $url = env('API_URL_MEDINFRAS_WS');
        //}

        $this->clients = new Client([
            'base_uri' => $url,
            'verify' => false
        ]);

        $this->setTimestamp()->setSignature()->setHeaders();
    }


    protected function setHeaders()
    {
        $this->headers = [
            'X-cons-id' => $this->consId,
            'X-Timestamp' => $this->timestamp,
            'X-Signature' => $this->signature
        ];

        return $this;
    }

    protected function setTimestamp()
    {
        $time = strtotime(Carbon::now()->setTimezone('UTC'));

        $this->timestamp = strval($time - strtotime('1970-01-01 00:00:00'));

        return $this;
    }

    protected function setSignature()
    {
        $data = $this->timestamp . $this->consId;
        //$data = $this->consId."&".$this->timestamp;
        $signature = hash_hmac('sha256', $data, $this->secretKey, true);
        $encodedSignature = base64_encode($signature);
        $this->signature = $encodedSignature;

        return $this;
    }

    public function get($src)
    {
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';

        try {
            $response = $this->clients->request(
                'GET',
                $src,
                [
                    'headers' => $this->headers
                ]
            )->getBody()->getContents();
        } catch (ConnectException $e) {
            $response = Message::toString($e->getResponse());
        } catch (ClientException $e) {
            $response = Message::toString($e->getResponse());
        }

        return $response;
    }

    public function post($src, $data = [])
    {
        $this->headers['Content-Type'] = 'application/json';
        Log::info('KasirService post', ['src' => $src, 'header' => $this->headers, 'data' => $data]);
        try {
            $response = $this->clients->request(
                'POST',
                $src,
                [
                    'headers' => $this->headers,
                    'json' => $data
                ]
            )->getBody()->getContents();
        } catch (ConnectException $e) {
            $response = Message::toString($e->getResponse());
        } catch (ClientException $e) {
            $response = Message::toString($e->getResponse());
        }

        return $response;
    }
}
