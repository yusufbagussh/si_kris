<?php

namespace App\Http\Controllers\APM\EDC;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleTransactionRequest;
use App\Jobs\APM\EDC\SendEcrLinkJob;
use App\Libraries\BRI\ECRLinkService;
use App\Models\EdcPayment;
use App\Models\PatientPayment;
use App\Models\PatientPaymentDetail;
use App\Traits\MessageResponseTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ECRLinkController extends Controller
{
    private ECRLinkService $ecrLinkService;
    private PatientPayment $patientPayment;
    private PatientPaymentDetail $patientPaymentDetail;
    private EdcPayment $edcPayment;

    use MessageResponseTrait;

    public function __construct()
    {
        // Load EDC configuration from environment variables
        $edcAddress = config('ecrlink.edc_address');
        $posAddress = config('ecrlink.pos_address');
        $isSecure = config('ecrlink.secure', false);

        $this->ecrLinkService = new ECRLinkService($edcAddress, $posAddress, $isSecure);
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
            'method' => 'required|string',
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
            $amount = $request->total_amount;
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'purchase');

            // Ensure method is valid
            if (!in_array($method, ['purchase', 'brizzi', 'qris'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase, brizzi, qris');
            }

            DB::beginTransaction();
            // Save transaction to database
            $patientPayment = $this->patientPayment->create([
                'medical_record_no' => $request->medical_record_no,
                'registration_no' => $request->registration_no,
                'total_amount' => $request->total_amount,
                'payment_method' => $method,
                //'payment_method_code' => '021',
            ]);

            foreach ($request->billing_list as $billing) {
                $billingNo = $billing['billing_no'];
                $amount = $billing['billing_amount'];

                $this->patientPaymentDetail->create([
                    'patient_payment_id' => $patientPayment->id,
                    'billing_no' => $billingNo,
                    'billing_amount' => $amount,
                ]);
            }

            $this->edcPayment->create([
                'patient_payment_id' => $patientPayment->id,
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method,
                'status' => 'pending',

            ]);

            //$this->ecrLinkService->sale($amount, $trxId, $method);
            dispatch(new SendEcrLinkJob($trxId, $amount, $method, 'sale'));

            DB::commit();

            // Log that the request was initiated
            Log::info('ECRLink sale transaction initiated', [
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method
            ]);

            // Langsung kembalikan respons ke client
            $data = [
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method,
                'action' => 'Sale'
            ];

            return $this->accept_msg_data_res('Sale transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Sale] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
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
            'method' => 'required|string',
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
            $amount = $request->input('amount');
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'purchase');

            // Ensure method is valid
            if (!in_array($method, ['purchase'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase');
            }

            DB::beginTransaction();
            // Save transaction to database
            $patientPayment = $this->patientPayment->create([
                'medical_record_no' => $request->medical_record_no,
                'registration_no' => $request->registration_no,
                'total_amount' => $request->total_amount,
                'payment_method' => $method,
                //'payment_method_code' => '021',
            ]);

            foreach ($request->billing_list as $billing) {
                $billingNo = $billing['billing_no'];
                $amount = $billing['billing_amount'];

                $this->patientPaymentDetail->create([
                    'patient_payment_id' => $patientPayment->id,
                    'billing_no' => $billingNo,
                    'billing_amount' => $amount,
                ]);
            }

            $this->edcPayment->create([
                'patient_payment_id' => $patientPayment->id,
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method,
                'status' => 'pending',

            ]);

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob($trxId, $amount, $method, 'contactless'));

            // Log that the request was initiated
            Log::info('ECRLink contactless transaction initiated', [
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method,
                'action' => 'Contactless'
            ]);

            $data = [
                'transaction_id' => $trxId,
                'amount' => $amount,
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Contactless transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
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
     * @param SaleTransactionRequest $request
     * @return array
     */
    public function void(SaleTransactionRequest $request)
    {
        try {
            // Get validated data from request
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'void');

            // Ensure method is valid
            if (!in_array($method, ['void'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase');
            }


            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob($trxId, '', $method, 'void'));

            // Log that the request was initiated
            Log::info('ECRLink void service initiated', [
                'trace_number' => $trxId,
            ]);

            $data = [
                'trace_number' => $trxId,
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Contactless transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Contactless] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function checkStatusQR(SaleTransactionRequest $request)
    {
        try {
            // Get validated data from request
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'qris');

            // Ensure method is valid
            if (!in_array($method, ['qris'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: qris');
            }

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob($trxId, '', $method, 'check-status-qris'));

            // Log that the request was initiated
            Log::info('ECRLink check status QR transaction initiated', [
                'reference_number' => $trxId,
                'method' => $method
            ]);

            $data = [
                'reference_number' => $trxId,
                'method' => $method
            ];

            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Check status QR transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][CheckStatusQR] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function refundQR(SaleTransactionRequest $request)
    {
        try {
            // Get validated data from request
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'qris');

            // Ensure method is valid
            if (!in_array($method, ['qris'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: qris');
            }

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob($trxId, '', $method, 'refund-qris'));

            // Log that the request was initiated
            Log::info('ECRLink refund QRIS transaction initiated', [
                'reff_id' => $trxId,
                'method' => $method
            ]);

            $data = [
                'reff_id' => $trxId,
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Refund QRIS transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][RefundQRIS] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function reprintLast(Request $request)
    {
        try {
            // Get validated data from request
            $method = $request->input('method');

            // Ensure method is valid
            if (!in_array($method, ['purchase', 'brizzi'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase, brizzi');
            }

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob('', '', $method, 'reprint-last'));

            // Log that the request was initiated
            Log::info('ECRLink reprintLast transaction initiated', [
                'method' => $method
            ]);

            $data = [
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Reprint last transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][ReprintLast] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function reprintAnyTransaction(Request $request)
    {
        try {
            // Get validated data from request
            $trxId = $this->generateTrxID($request->registration_no);
            $method = $request->input('method', 'purchase');

            // Ensure method is valid
            if (!in_array($method, ['purchase', 'brizzi'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase');
            }

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob($trxId, '', $method, 'reprint-any-transaction'));

            // Log that the request was initiated
            Log::info('ECRLink reprintAnyTransaction transaction initiated', [
                'trace_number' => $trxId,
                'method' => $method
            ]);

            $data = [
                'trace_number' => $trxId,
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Reprint any transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][ReprintAnyTransaction] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    public function settlement(Request $request)
    {
        try {
            // Get validated data from request
            $method = $request->input('method', 'purchase');

            // Ensure method is valid
            if (!in_array($method, ['purchase', 'brizzi'])) {
                return $this->fail_msg_res('Invalid payment method. Allowed methods: purchase');
            }

            // Inisialisasi proses transaksi asynchronously - tidak perlu menunggu hasil
            dispatch(new SendEcrLinkJob('', '', $method, 'settlement'));

            // Log that the request was initiated
            Log::info('ECRLink settlement transaction initiated', [
                'method' => $method
            ]);

            $data = [
                'method' => $method
            ];
            // Langsung kembalikan respons ke client
            return $this->accept_msg_data_res('Settlement transaction request has been sent to EDC', $data); // Using 202 Accepted status code to indicate asynchronous processing
        } catch (Exception $e) {
            Log::error('[' . $e->getCode() . '][ECRLink][Settlement] ' . $e->getMessage());
            return $this->error_res($e->getCode());
        }
    }

    private function generateTrxID($registrationNo)
    {
        $exploadRegistrationNo = explode("/", trim($registrationNo)); //13 digit
        $randomSuffix = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $registrationNo = $exploadRegistrationNo[1] . $exploadRegistrationNo[2] . $randomSuffix;
        return $registrationNo;
    }
}
