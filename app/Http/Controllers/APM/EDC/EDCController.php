<?php

namespace App\Http\Controllers\APM\EDC;

use App\Http\Controllers\Controller;
use App\Jobs\APM\EDC\EDCJob;
use App\Libraries\BRI\EDCService;
use App\Models\EdcPayment;
use App\Models\PatientPayment;
use App\Models\PatientPaymentDetail;
use App\Traits\MessageResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EDCController extends Controller
{
    private EDCService $ecrLinkService;
    private PatientPayment $patientPayment;
    private PatientPaymentDetail $patientPaymentDetail;
    private EdcPayment $edcPayment;
    private $apmWebhookSecret;
    private $apmWebhookBaseUrl;

    use MessageResponseTrait;

    public function __construct()
    {
        // Load EDC configuration from environment variables
        $edcAddress = config('edc.edc_address');
        $posAddress = config('edc.pos_address');
        $isSecure = config('edc.secure', false);

        $this->apmWebhookSecret = config('edc.webhook_secret');
        $this->apmWebhookBaseUrl = config('edc.webhook_url');

        $this->ecrLinkService = new EDCService($edcAddress, $posAddress, $isSecure);
        $this->patientPayment = new PatientPayment();
        $this->patientPaymentDetail = new PatientPaymentDetail();
        $this->edcPayment = new EdcPayment();
    }

    public function index()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'ECRLink service is running'
        ]);
    }

    private function generateTrxID($registrationNo)
    {
        $exploadRegistrationNo = explode("/", trim($registrationNo)); //13 digit
        $randomSuffix = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $registrationNo = $exploadRegistrationNo[1] . $exploadRegistrationNo[2] . $randomSuffix;
        return $registrationNo;
    }

    private function checkPaymentMethod($method)
    {
        $paymentMethodCode = '';
        switch ($method) {
            case 'purchase':
            case 'brizzi':
                $paymentMethodCode = '003'; //Debit Card //'013'; //Virtual Payment
                break;
            case 'qris':
                $paymentMethodCode = '021'; //QRIS
                break;
        }
        return $paymentMethodCode;
    }

    /**
     * Process a sale transaction
     *
     */
    public function sale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medical_record_no' => 'required|string',
            'registration_no' => 'required',
            'billing_list' => 'required|array',
            'total_amount' => 'required|numeric',
            'billing_list.*.billing_no' => 'required|string',
            'billing_list.*.billing_amount' => 'required|numeric',
            'method' => 'required|string|in:purchase,brizzi,qris',
            'action' => 'required|string',
        ], [
            'medical_record_no.required' => 'Nomor rekam medis harus diisi',
            'registration_no.required' => 'Nomor registrasi harus diisi',
            'total_amount.required' => 'Total biaya harus diisi',
            'total_amount.numeric' => 'Total biaya harus berupa angka',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'billing_list.required' => 'Informasi billing harus diisi',
            'billing_list.*.billing_no.required' => 'Nomor billing harus diisi',
            'billing_list.*.billing_amount.required' => 'Biaya tagihan harus diisi',
            'billing_list.*.billing_amount.numeric' => 'Biaya tagihan harus berupa angka',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'registration_no' => $request->registration_no,
                'amount' => $request->amount,
                'method' => $request->method,
                'action' => trim($request->action),
                'request' => $request->all(),
            ];

            // Log that the request was initiated
            Log::info('ECRLink sale transaction initiated', $data);

            $edcPayment = $this->edcPayment->findPatientPayment($request->registration_no, $request->billing_list);

            //Asynchronously dispatch the job to send ECRLink data
            if ($edcPayment) {
                if ($edcPayment->status == 'paid') {
                    return $this->ok_msg_res('Bill has been paid');
                } else {
                    $data['patient_payment'] = $edcPayment->patientPayment;
                    dispatch(new EDCJob($data));
                }
            } else {
                $data['patient_payment'] = null;
                dispatch(new EDCJob($data));
            }

            //Synchronously process the sale transaction
            // DB::beginTransaction();
            // if ($edcPayment) {
            //     if ($edcPayment->status == 'paid') {
            //         return $this->ok_msg_res('Bill has been paid');
            //     } else {
            //         $response = $this->ecrLinkService->sale($data);
            //         $patientPayment = $edcPayment->patientPayment;
            //         $this->saveEdcPayment($edcPayment->patientPayment->id, $response);
            //     }
            // } else {
            //     $response = $this->ecrLinkService->sale($data);
            //     $patientPayment = $this->savePatientPayment($request);
            //     $this->saveEdcPayment($patientPayment->id, $response);
            // }
            // DB::commit();
            // $this->sendToWebhook($patientPayment, $response);

            return $this->accept_msg_data_res('Sale transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('[' . $e->getCode() . '][ECRLink][Sale] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    private function savePatientPayment($request)
    {
        $patientPayment = PatientPayment::create([
            'medical_record_no' => $request['medical_record_no'],
            'registration_no' => $request['registration_no'],
            'total_amount' => $request['total_amount'],
            'payment_method' => $request->method,
            'payment_method_code' => $this->checkPaymentMethod($request->method)
        ]);

        foreach ($request['billing_list'] as $billing) {
            $billingNo = $billing['billing_no'];
            $amount = $billing['billing_amount'];

            PatientPaymentDetail::create([
                'patient_payment_id' => $patientPayment->id,
                'billing_no' => $billingNo,
                'billing_amount' => $amount,
            ]);
        }
        return $patientPayment;
    }

    private function sendToWebhook($patientPayment, $response)
    {
        $method = $response['card_category'] != 'N/A' ?? $response['card_name'] != 'N/A' ?? 'Sobapay';
        $data = [
            "registration_no" => $patientPayment->registration_no,
            "remarks" => "Pembayaran melalui {$method} dengan jumlah {$response['amount']}",
            "reference_no" => $response['reference_number'],
            "status" => $response['status'],
            "message" => $response['msg'],
            "amount" => $response['amount'],
            "issuer_name" => $method
        ];

        $billingList = [];
        foreach ($patientPayment->patientPaymentDetail as $detail) {
            $billingAmount = intval($detail->billing_amount);
            $billingList[] = "{$detail->billing_no}-{$billingAmount}";
        }

        $data['billList'] = implode(',', $billingList);

        $headersWebhook = [
            'Content-Type' => 'application/json',
            'X-Signature' => hash_hmac('sha256', json_encode($data), $this->apmWebhookSecret),
        ];

        Log::info('Callback APM Request', [
            'headers' => $headersWebhook,
            'data' => $data,
        ]);

        $response = Http::withHeaders($headersWebhook)->post($this->apmWebhookBaseUrl . '/payment/bank/kris-card-callback', $data);

        Log::info('Callback APM Response', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    private function saveEdcPayment($patientPaymentID, $response)
    {
        $this->edcPayment->create([
            'patient_payment_id' => $patientPaymentID,
            'acq_mid' => $response['acq_mid'] ?? null,
            'acq_tid' => $response['acq_tid'] ?? null,
            'action' => $response['action'],
            'amount' => $response['amount'],
            'approval_code' => $response['approval'] ?? null, //approval
            'batch_number' => $response['batch_number'] ?? null,
            'card_category' => $response['card_category'] ?? null,
            'card_name' => $response['card_name'] ?? null,
            'card_type' => $response['card_type'] ?? null,
            'edc_address' => $response['edc_address'] ?? null,
            'is_credit' => $response['is_credit'] ?? null,
            'is_off_us' => $response['is_off_us'] ?? null,
            'method' => strtolower($response['method']),
            'message' => $response['msg'] ?? null,
            'pan' => $response['pan'] ?? null,
            'pos_address' => $response['pos_address'] ?? null,
            'rc' => $response['rc'] ?? null,
            'reference_number' => $response['reference_number'] ?? null,
            'reff_id' => $response['reff_id'] ?? null, //QR
            'status' => $response['status'] ?? null,
            'trace_number' => $response['trace_number'] ?? null,
            'transaction_date' => $response['transaction_date'] ?? null,
            'transaction_id' => $response['trx_id'],
        ]);
    }

    /**
     * Process a contactless payment
     *
     * Contactless hanya dapat digunakan untuk metode Pembayaran Purchase.
     * Contactless dapat digunakan untuk pembayaran metode Tap menggunakan kartu Visa dan Mastercard.
     *
     */
    public function contactless(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medical_record_no' => 'required|string',
            'registration_no' => 'required',
            'billing_list' => 'required|array',
            'total_amount' => 'required|numeric',
            'method' => 'required|string|in:purchase',
            'billing_list.*.billing_no' => 'required|string',
            'billing_list.*.billing_amount' => 'required|numeric',
        ], [
            'medical_record_no.required' => 'Nomor rekam medis harus diisi',
            'registration_no.required' => 'Nomor registrasi harus diisi',
            'total_amount.required' => 'Total biaya harus diisi',
            'total_amount.numeric' => 'Total biaya harus berupa angka',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'billing_list.required' => 'Informasi billing harus diisi',
            'billing_list.*.billing_no.required' => 'Nomor billing harus diisi',
            'billing_list.*.billing_amount.required' => 'Biaya tagihan harus diisi',
            'billing_list.*.billing_amount.numeric' => 'Biaya tagihan harus berupa angka',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            // Get validated data from request
            $data = [
                'registration_no' => $request->registration_no,
                'amount' => $request->amount,
                'method' => $request->method,
                'action' => $request->action,
                'request' => $request->all(),
            ];

            // Log that the request was initiated
            Log::info('ECRLink sale transaction initiated', $data);

            $edcPayment = $this->edcPayment->findPatientPayment($request->registration_no, $request->billing_list);

            // Asynchronously dispatch the job to send ECRLink data
            if ($edcPayment) {
                if ($edcPayment->status == 'paid') {
                    return $this->ok_msg_res('Bill has been paid');
                } else {
                    $data['patient_payment'] = $edcPayment->patientPayment;
                    dispatch(new EDCJob($data));
                }
            } else {
                $data['patient_payment'] = null;
                dispatch(new EDCJob($data));
            }

            return $this->accept_msg_data_res('Contactless transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Contactless] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    /**
     * Process a contactless payment
     *
     * Contactless hanya dapat digunakan untuk metode Pembayaran Purchase.
     * Contactless dapat digunakan untuk pembayaran metode Tap menggunakan kartu Visa dan Mastercard.
     *
     * @param Request $request
     * @return array
     */
    public function void(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'registration_no' => 'required',
            'billing_list' => 'required|array',
            'billing_list.*.billing_no' => 'required|string',
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string:Void',
            'trace_number' => 'required|numeric',
        ], [
            'registration_no.required' => 'Nomor registrasi harus diisi',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'billing_list.required' => 'Informasi billing harus diisi',
            'billing_list.*.billing_no.required' => 'Nomor billing harus diisi',
            'trace_number.required' => 'Trace number harus diisi',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'registration_no' => $request->registration_no,
                'trace_number' => (int)$request->trace_number,
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink void service initiated', [
                'trace_number' => $data,
            ]);

            $edcPayment = $this->edcPayment->findPatientPayment($request->registration_no, $request->billing_list);

            if ($edcPayment) {
                if (!in_array($edcPayment->status, ['paid', 'refund'])) {
                    dispatch(new EDCJob($data));
                } else {
                    return $this->fail_msg_res('Cannot void a payment that has already been paid or refunded.');
                }
            } else {
                return $this->fail_msg_res('No payment record found for the provided registration number and billing list.');
            }

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Contactless transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Contactless] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function checkStatusQR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference_number' => 'required|string',
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string|in:Settlement',
        ], [
            'reference_number.required' => 'Nomor referensi harus diisi',
            'reference_number.string' => 'Nomor referensi harus berupa string',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'action.required' => 'Aksi harus diisi',
            'action.string' => 'Aksi harus berupa string',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'reference_number' => $request->reference_number,
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink Reprint Any Transaction initiated', $data);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new EDCJob($data));

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][CheckStatusQR] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function refundQR(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reff_id' => 'required|string',
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string|in:Settlement',
        ], [
            'reff_id.required' => 'Nomor referensi harus diisi',
            'reff_id.string' => 'Nomor referensi harus berupa string',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'action.required' => 'Aksi harus diisi',
            'action.string' => 'Aksi harus berupa string',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'reff_id' => $request->reff_id,
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink Refund QR initiated', $data);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new EDCJob($data));

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][RefundQRIS] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function reprintLast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string|in:Settlement',
        ], [
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'action.required' => 'Aksi harus diisi',
            'action.string' => 'Aksi harus berupa string',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink Reprint Last Transaction initiated', $data);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new EDCJob($data));

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][ReprintLast] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function reprintAnyTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trace_number' => 'required|string',
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string|in:Settlement',
        ], [
            'trace_number.required' => 'Nomor referensi harus diisi',
            'reference_number.string' => 'Nomor referensi harus berupa string',
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'action.required' => 'Aksi harus diisi',
            'action.string' => 'Aksi harus berupa string',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'reference_number' => $request->reference_number,
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink Reprint Any Transaction initiated', $data);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new EDCJob($data));

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][ReprintAnyTransaction] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function settlement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|string|in:purchase:brizzi',
            'action' => 'required|string|in:Settlement',
        ], [
            'method.required' => 'Metode pembayaran harus diisi',
            'method.string' => 'Metode pembayaran harus berupa string',
            'action.required' => 'Aksi harus diisi',
            'action.string' => 'Aksi harus berupa string',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $data = [
                'method' => $request->method,
                'action' => trim($request->action),
            ];

            // Log that the request was initiated
            Log::info('ECRLink settlement transaction initiated', $data);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new EDCJob($data));

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data);
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Settlement] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }
}
