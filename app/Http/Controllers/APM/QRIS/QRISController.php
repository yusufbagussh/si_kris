<?php

namespace App\Http\Controllers\APM\QRIS;

use App\Http\Controllers\Controller;
use App\Models\PatientPayment;
use App\Models\PatientPaymentDetail;
use App\Models\QrisBriToken;
use App\Models\QrisInquiry;
use App\Models\QrisPayment;
use App\Services\BRI\QRISService;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QRISController extends Controller
{
    use MessageResponseTrait;

    private QRISService $qrisService;
    private QrisBriToken $qrisBriToken;
    private QrisPayment $qrisTransaction;
    private QrisInquiry $qrisInquiry;
    private PatientPayment $patientPayment;
    private PatientPaymentDetail $patientPaymentDetail;
    private QrisPayment $qrisPayment;

    public function __construct()
    {
        $this->qrisService = new QRISService;
        $this->qrisBriToken = new QrisBriToken();
        $this->qrisPayment = new QrisPayment();
        $this->qrisInquiry = new QrisInquiry();
        $this->patientPayment = new PatientPayment();
        $this->patientPaymentDetail = new PatientPaymentDetail();
    }

    /**
     * Generate QR Code for Patient Payment
     *
     * @param Request $request
     */
    public function generateQrPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medical_record_no' => 'required|string',
            'registration_no' => 'required',
            'billing_list' => 'required|array',
            'total_amount' => 'required|numeric',
            'billing_list.*.billing_no' => 'required|string',
            'billing_list.*.billing_amount' => 'required|numeric',
        ], [
            'medical_record_no.required' => 'Nomor rekam medis harus diisi',
            'registration_no.required' => 'Nomor registrasi harus diisi',
            'total_amount.required' => 'Total biaya harus diisi',
            'total_amount.numeric' => 'Total biaya harus berupa angka',
            'billing_list.required' => 'Informasi billing harus diisi',
            'billing_list.*.billing_no.required' => 'Nomor billing harus diisi',
            'billing_list.*.billing_amount.required' => 'Biaya tagihan harus diisi',
            'billing_list.*.billing_amount.numeric' => 'Biaya tagihan harus berupa angka',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $token = $this->qrisBriToken->getAccessToken() ?? $this->getAccessToken();

            if (!$token) {
                return $this->fail_msg_res('Token tidak ditemukan');
            }

            $qrisPayment = $this->qrisPayment
                ->with(['patientPayment', 'patientPayment.patientPaymentDetail'])
                ->whereHas('patientPayment', function ($query) use ($request) {
                    $query->where('registration_no', $request->registration_no);
                })
                ->whereHas('patientPayment.patientPaymentDetail', function ($query) use ($request) {
                    $listBillingNo = collect($request->billing_list)->pluck('billing_no')->toArray();
                    $query->whereIn('billing_no', $listBillingNo);
                })
                ->latest()
                ->first();

            if ($qrisPayment) {
                if ($qrisPayment->status == 'SUCCESS') {
                    return $this->ok_msg_res('Tagihan sudah dibayar');
                }

                // if ($qrisPayment->expires_at > now()->format('Y-m-d H:i:s')) {
                //     return $this->ok_msg_data_res('QR Code masih aktif', [
                //         "responseCode" => $qrisPayment->response_code,
                //         "responseMessage" => $qrisPayment->response_message,
                //         "partnerReferenceNo" => $qrisPayment->partner_reference_no,
                //         "qrContent" => $qrisPayment->qr_content,
                //         "referenceNo" => $qrisPayment->original_reference_no,
                //     ]);
                // }

                $response = $this->qrisService->generateQR(
                    $token,
                    $request->registration_no,
                    $request->total_amount,
                );

                if ($response['responseCode'] != 2004700) {
                    return $this->fail_msg_res($response['responseMessage']);
                }

                $this->saveGeneratedQR(
                    $qrisPayment->patientPayment->id,
                    $response,
                    env('QRIS_TERMINAL_ID'),
                    env('QRIS_MERCHANT_ID'),
                    $request->medical_record_no,
                    $request->registration_no,
                    $request->total_amount,
                );

                return $this->ok_data_res($response);
            }

            $response = $this->qrisService->generateQR(
                $token,
                $request->registration_no,
                $request->total_amount,
            );

            if ($response['responseCode'] != 2004700) {
                return $this->fail_msg_res($response['responseMessage']);
            }

            DB::beginTransaction();
            $patientPayment = $this->patientPayment->create([
                'medical_record_no' => $request->medical_record_no,
                'registration_no' => $request->registration_no,
                'total_amount' => $request->total_amount,
                'payment_method' => 'QRIS BRI',
                'payment_method_code' => '021',
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

            $this->saveGeneratedQR(
                $patientPayment->id,
                $response,
                env('QRIS_TERMINAL_ID'),
                env('QRIS_MERCHANT_ID'),
                $request->medical_record_no,
                $request->registration_no,
                $request->total_amount,
            );

            DB::commit();

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[' . $e->getCode() . '][generateQrPatient] ' . $e->getMessage());
            return $this->error_res(500);
        }
    }

    /**
     * Inquiry payment status
     *
     * @param Request $request
     */
    public function inquiryPaymentPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'original_reference_no' => 'required',
        ], [
            'original_reference_no.required' => 'Nomer referensi original harus diisi',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        $token = $this->qrisBriToken->getAccessToken() ?? $this->getAccessToken();
        if (!$token) {
            return $this->fail_msg_res('Token tidak ditemukan');
        }

        try {
            $qrisPayment = $this->qrisPayment
                ->where('original_reference_no', $request->original_reference_no)
                ->first();

            if (!$qrisPayment) {
                return $this->fail_msg_res('Data transaksi tidak ditemukan');
            }

            $response = $this->qrisService->inquiryPayment($token, $request->original_reference_no);

            if ($response['responseCode'] != 2005100) {
                return $this->fail_msg_res($response['responseMessage']);
            }

            $this->savePaymentInquiry($response, $request->original_reference_no, env('QRIS_TERMINAL_ID'));

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][inquiryPaymentPatient] ' . $e->getMessage());
            return $this->error_res(500);
        }
    }

    protected function getAccessToken()
    {
        $token = $this->qrisService->getAccessToken();
        $this->saveAccessToken($token);
        return $token ? $token['accessToken'] : null;
    }

    public function getListInfoPatientPayment(Request $request)
    {
        try {
            $listPatientPayments = $this->patientPayment
                ->with(['lastQrisPayment', 'patientPaymentDetail'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->ok_data_res($listPatientPayments);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][getListInfoPatientPayment] ' . $e->getMessage());
            return $this->error_res(500);
        }
    }

    /**
     * Save access token to database
     *
     * @param array $tokenData
     * @return void
     */
    protected function saveAccessToken(array $tokenData)
    {
        $this->qrisBriToken->create([
            'token' => $tokenData['accessToken'],
            'token_type' => $tokenData['tokenType'],
            'expires_in' => $tokenData['expiresIn'],
            'expires_at' => now()->addSeconds((int)$tokenData['expiresIn']),
        ]);
    }

    /**
     * Save generated QR data to database
     *
     * @param $patientPaymentID
     * @param array $responseData
     * @param string $partnerReferenceNo
     * @param string $merchantId
     * @param string $terminalId
     * @param string $medicalRecordNo
     * @param string $registrationNo
     * @param string $billingNo
     * @param float $amount
     * @return void
     */
    private function saveGeneratedQR(
        $patientPaymentID,
        array $responseData,
        string $merchantId,
        string $terminalId,
        string $medicalRecordNo,
        string $registrationNo,
        float $amount,
    ): void
    {
        $this->qrisPayment->create([
            'patient_payment_id' => $patientPaymentID,
            'registration_no' => $registrationNo,
            'original_reference_no' => $responseData['referenceNo'],
            'partner_reference_no' => $responseData['partnerReferenceNo'],
            'value' => $amount,
            'merchant_id' => $merchantId,
            'terminal_id' => $terminalId,
            'qr_content' => $responseData['qrContent'],
            'status' => 'PENDING',
            'response_code' => $responseData['responseCode'],
            'response_message' => $responseData['responseMessage'],
            'expires_at' => now()->addSeconds(119),
            'medical_record_no' => $medicalRecordNo,
        ]);
    }

    /**
     * Save payment inquiry data to database
     *
     * @param array $responseData
     * @param string $originalReferenceNo
     * @param string $terminalId
     * @return void
     */
    protected function savePaymentInquiry(array $response, string $originalReferenceNo, string $terminalId)
    {
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

        //Update data QRIS Transaction
        $status = $statusMap[$response['latestTransactionStatus']] ?? 'UNKNOWN';
        $transaction->status = $status;
        $transaction->last_inquiry_at = now();
        $transaction->save();

        $this->qrisInquiry->create([
            'qris_transaction_id' => $transaction->id,
            'original_reference_no' => $originalReferenceNo,
            'terminal_id' => $terminalId,
            'response_code' => $response['responseCode'],
            'response_message' => $response['responseMessage'],
            'service_code' => $response['serviceCode'],
            'latest_transaction_status' => $response['latestTransactionStatus'],
            'transaction_status_desc' => $response['transactionStatusDesc'] ?? null,
            'customer_name' => $response['additionalInfo']['customerName'] ?? null,
            'customer_number' => $response['additionalInfo']['customerNumber'] ?? null,
            'invoice_number' => $response['additionalInfo']['invoiceNumber'] ?? null,
            'issuer_name' => $response['additionalInfo']['issuerName'] ?? null,
            'issuer_rrn' => $response['additionalInfo']['issuerRrn'] ?? null,
            'mpan' => $response['additionalInfo']['mpan'] ?? null,
        ]);
    }
}
