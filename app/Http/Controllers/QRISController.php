<?php

namespace App\Http\Controllers;

use App\Models\PatientPayment;
use App\Models\PatientPaymentDetail;
use App\Models\QrisBriToken;
use App\Models\QrisPayment;
use App\Services\QRISService;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QRISController extends Controller
{
    use MessageResponseTrait;

    private QRISService $qrisService;
    private QrisBriToken $qrisBriToken;
    private QrisPayment $qrisTransaction;
    private PatientPayment $patientPayment;
    private PatientPaymentDetail $patientPaymentDetail;
    private QrisPayment $qrisPayment;

    public function __construct(QRISService $qrisService)
    {
        $this->qrisService = $qrisService;
        $this->qrisBriToken = new QrisBriToken();
        $this->qrisPayment = new QrisPayment();
        $this->patientPayment = new PatientPayment();
        $this->patientPaymentDetail = new PatientPaymentDetail();
    }

    // 1. Get Access Token
    // public function getToken()
    // {
    //     return response()->json($this->qrisService->getAccessToken());
    // }

    public function getListInfoPatientPayment(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'medical_record_no' => 'required|string',
        //     'registration_no' => 'required',
        // ], [
        //     'medical_record_no.required' => 'Nomor rekam medis harus diisi',
        //     'registration_no.required' => 'Nomor registrasi harus diisi',
        // ]);

        // if ($validator->fails()) {
        //     return $this->fail_msg_res($validator->errors());
        //}

        try {
            //            $result = DB::table('qris_transactions')
            //                ->select('medical_record_no', 'registration_no', 'billing_no', 'registration_no', 'value', 'currency', 'status')
            //                ->whereIn(DB::raw('(registration_no, billing_no, created_at)'), function ($query) {
            //                    $query->select('registration_no', 'billing_no', DB::raw('MAX(created_at)'))
            //                        ->from('qris_transactions')
            //                        ->groupBy('registration_no', 'billing_no');
            //                })
            //                ->orderBy('created_at', 'desc')
            //                ->get();

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

    protected function getAccessTokenFromDB()
    {
        $token = $this->qrisBriToken->getAccessToken();

        return $token ? $token->token : null;
    }

    // 2. Generate QR Code
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
            'billing_list.required' => 'Informasi billing harus diisi',
            'billing_list.*.billing_no.required' => 'Nomor billing harus diisi',
            'billing_list.*.billing_amount.required' => 'Biaya tagihan harus diisi',
            'billing_list.*.billing_amount.numeric' => 'Biaya tagihan harus berupa angka',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $token = $this->qrisBriToken->getAccessToken() ?? $this->qrisService->getAccessToken();

            if (!$token) {
                return $this->fail_msg_res('Token tidak ditemukan');
            }

            $qrisPayment = $this->qrisPayment
                ->with(['patientPayment', 'patientPayment.patientPaymentDetail'])
                //->where('registration_no', $request->registration_no)
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

            //$qrisTransaction = $this->qrisPayment->findLastTransactionByRegistationNo($request->registration_no);

            //if ($qrisTransaction) {
            //    //Cek status transaksi
            //    if ($qrisTransaction->status == 'SUCCESS') {
            //        return $this->ok_msg_res('Transaksi sudah dibayar');
            //    }

            //    //Cek expired time qr
            //    if ($qrisTransaction->expires_at > now()) {
            //        return $this->ok_msg_data_res('QR Code belum expired', [
            //            "responseCode" => $qrisTransaction->response_code,
            //            "responseMessage" => $qrisTransaction->response_message,
            //            "partnerReferenceNo" => $qrisTransaction->partner_reference_no,
            //            "qrContent" => $qrisTransaction->qr_content,
            //            "referenceNo" => $qrisTransaction->original_reference_no,
            //        ]);
            //    }
            //}

            $response = $this->qrisService->generateQR(
                $token,
                $request->registration_no,
                $request->total_amount,
            );

            if ($response['responseCode'] != 2004700) {
                return $this->fail_msg_res($response['responseMessage']);
            }

            // Save transaction to database
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

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][generateQrPatient] ' . $e->getMessage());
            return $this->error_res(500);
        }
    }

    // 3. Inquiry Payment
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

        $token = $this->qrisBriToken->getAccessToken() ?? $this->qrisService->getAccessToken();
        if (!$token) {
            return $this->fail_msg_res('Token tidak ditemukan');
        }

        try {
            // Find transaction by reference number
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

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][inquiryPaymentPatient] ' . $e->getMessage());
            return $this->error_res(500);
        }
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
    ): void {
        $this->qrisPayment->create([
            'patient_payment_id' => $patientPaymentID,
            'registration_no' => $registrationNo,
            'original_reference_no' => $responseData['referenceNo'],
            'partner_reference_no' => $responseData['partnerReferenceNo'],
            'value' => $amount,
            //currency
            'merchant_id' => $merchantId,
            'terminal_id' => $terminalId,
            'qr_content' => $responseData['qrContent'],
            'status' => 'PENDING',
            'response_code' => $responseData['responseCode'],
            'response_message' => $responseData['responseMessage'],
            'expires_at' => now()->addSeconds(119),
            //new data
            'medical_record_no' => $medicalRecordNo,
            //'billing_no' => $billingNo,
        ]);
    }

    // public function checkPatient(Request $request)
    // {
    //     $request->validate([
    //         'medical_partner_no' => 'required|string',
    //         'patient_date' => 'required|date',
    //     ]);

    //     // Simulasi data pasien dan transaksi dari database
    //     $patientData = [
    //         'name' => 'Budi Santoso',
    //         'age' => 45,
    //         'medical_partner_no' => $request->medical_partner_no,
    //         'visit_date' => $request->patient_date,
    //         'transactions' => [
    //             [
    //                 'referenceNo' => '409676201434',
    //                 'amount' => '500000.00',
    //                 'status' => 'Pending',
    //             ],
    //             [
    //                 'referenceNo' => '409676201435',
    //                 'amount' => '250000.00',
    //                 'status' => 'Success',
    //             ],
    //         ],
    //     ];

    //     // Simulasi Generate QR Code untuk pembayaran baru
    //     // $qrResponse = $this->qrisService->generateQR(
    //     //     '1234567890133',
    //     //     500000.00,
    //     //     '00007100010926',
    //     //     '213141251124'
    //     // );

    //     // $dummyQR = "00020101021126660015ID.CO.BANK.BRI.WWW01189360000201102921379480206PAS12345180304ID5907RUMAH S6015KOTA JAKARTA P62070703A01630414BRI123456789011223456789530336054012.345802ID5910TEST MERCHANT6013JAKARTA UTARA62070703A01630414BRI98765432101122345678963044D2FCE446";

    //     $dummyQR = "00020101021226650013ID.CO.BRI.WWW011893600002011959081002150000010190000140303UME520412345303360540410005802ID5915RS DR EON Dummy6013KOTABUMI KOT.610534511623401188903337975013938740708195908106304C40F";

    //     // $dummyQR = "00020101021226650013ID.CO.BRI.WWW011893600002011959081002150000010190000140303UME520412345303360540115802ID5915RS DR EON Dummy6013KOTABUMI KOT.6105345116234011889033480190972512807081959081063043D93";


    //     return response()->json([
    //         'patient' => $patientData,
    //         'qrCode' => $dummyQR,
    //         // 'qrCode' => $qrResponse['qrContent'] ?? null,
    //     ]);
    // }

    // public function generateQR(Request $request)
    // {
    //     $request->validate([
    //         'partnerReferenceNo' => 'required|string',
    //         'amount' => 'required|numeric',
    //     ]);

    //     return response()->json(
    //         $this->qrisService->generateQR(
    //             $request->partnerReferenceNo,
    //             $request->amount,
    //             $request->merchantId,
    //             $request->terminalId
    //         )
    //     );
    // }

    // public function inquiryPayment(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'original_reference_no' => 'required',
    //     ], [
    //         'original_reference_no.required' => 'Nomor apotek harus diisi',
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->fail_msg_res($validator->errors());
    //     }

    //     try {
    //         return response()->json(
    //             $this->qrisService->inquiryPayment(
    //                 $request->original_reference_no
    //             )
    //         );
    //     } catch (\Exception $e) {
    //         return $this->error_res(500);
    //     }
    // }

    public function getSignatureToken()
    {
        return response()->json($this->qrisService->getSignatureAccessToken());
    }

    public function generateSignatureNotify(Request $request)
    {
        return response()->json(
            $this->qrisService->generateSignatureNotify(
                $request
            )
        );
    }
}
