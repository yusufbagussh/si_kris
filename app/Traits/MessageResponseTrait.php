<?php

namespace App\Traits;

trait MessageResponseTrait
{
    // Success Message
    public function ok_res()
    {
        return array('code' => 200, 'message' => 'OK', 'data' => []);
    }

    public function ok_msg_res($message)
    {
        return array('code' => 200, 'message' => $message, 'data' => []);
    }

    public function ok_data_res($data)
    {
        return array('code' => 200, 'message' => 'OK', 'data' => $data);
    }

    public function accept_msg_data_res($message, $data)
    {
        return array('code' => 202, 'status' => 'accepted', 'message' => $message, 'data' => $data);
    }

    public function ok_msg_data_res($message, $data)
    {
        return array('code' => 200, 'message' => $message, 'data' => $data);
    }

    // Fail Message
    public function fail_res()
    {
        return array('code' => 400, 'message' => 'Proses gagal silahkan coba kembali', 'data' => []);
    }

    public function fail_msg_res($message)
    {
        return array('code' => 400, 'message' => $message, 'data' => []);
    }

    public function fail_data_res($data)
    {
        return array('code' => 400, 'message' => 'Proses gagal silahkan coba kembali', 'data' => $data);
    }

    public function fail_msg_data_res($message, $data)
    {
        return array('code' => 400, 'message' => $message, 'data' => $data);
    }

    // Not Found Message
    public function not_found_res()
    {
        return array('code' => 404, 'message' => 'Data tidak ditemukan', 'data' => []);
    }

    public function not_found_msg_res($message)
    {
        return array('code' => 404, 'message' => $message, 'data' => []);
    }

    public function not_found_data_res($data)
    {
        return array('code' => 404, 'message' => 'Data tidak ditemukan', 'data' => $data);
    }

    public function not_found_msg_data_res($message, $data)
    {
        return array('code' => 404, 'message' => $message, 'data' => $data);
    }

    function error_res($code)
    {
        return array('code' => (int)$code, 'message' => 'Kendala pada Server, silakan coba beberapa saat lagi atau hubungi TI', 'data' => []);
    }
}
