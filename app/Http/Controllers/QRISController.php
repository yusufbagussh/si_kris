<?php

namespace App\Http\Controllers;

use App\Models\QrisBriToken;
use App\Models\QrisTransaction;
use App\Services\QRISService;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QRISController extends Controller
{
    use MessageResponseTrait;

    protected QRISService $qrisService;

    public function __construct(QRISService $qrisService)
    {
        $this->qrisService = $qrisService;
    }

    // 1. Get Access Token
    // public function getToken()
    // {
    //     return response()->json($this->qrisService->getAccessToken());
    // }

    protected function getAccessTokenFromDB()
    {
        $token = QrisBriToken::where('expires_at', '>', now())
            ->latest()
            ->first();

        return $token ? $token->token : null;
    }

    // 2. Generate QR Code
    public function generateQrPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'medical_record_no' => 'required',
        ], [
            'medical_record_no.required' => 'Nomor Rekam Medis harus diisi',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $token = $this->getAccessTokenFromDB() ?? $this->qrisService->getAccessToken();

            if (!$token) {
                return $this->fail_msg_res('Token tidak ditemukan');
            }

            $medicalNo = substr(str_replace("-", "", $request->medical_record_no), -8);
            $qrisTransaction = QrisTransaction::select('partner_reference_no', 'reference_no', 'response_code', 'response_message', 'qr_content', 'expires_at')
                ->where('partner_reference_no', 'like', $medicalNo . '%')
                ->where('status', 'PENDING')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            //cek status transaction

            //Cek expired time qr
            if ($qrisTransaction) {
                return $this->ok_data_res([
                    "responseCode" => $qrisTransaction->response_code,
                    "responseMessage" => $qrisTransaction->response_message,
                    "partnerReferenceNo" => $qrisTransaction->partner_reference_no,
                    "qrContent" => $qrisTransaction->qr_content,
                    "referenceNo" => $qrisTransaction->reference_no,
                ]);
            }

            $response = $this->qrisService->generateQR($token, $request->medical_record_no);

            if ($response['responseCode'] != 2004700) {
                return $this->fail_msg_res($response['responseMessage']);
            }

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '] ' . $e->getMessage());
            return $this->error_res(500);
        }
    }

    // 3. Inquiry Payment
    public function inquiryPaymentPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'original_reference_no' => 'required',
        ], [
            'original_reference_no.required' => 'Nomor apotek harus diisi',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        $token = $this->getAccessTokenFromDB() ?? $this->qrisService->getAccessToken();
        if (!$token) {
            return $this->fail_msg_res('Token tidak ditemukan');
        }

        try {
            $response = $this->qrisService->inquiryPayment($token,  $request->original_reference_no);
            if ($response['responseCode'] != 2005100) {
                return $this->fail_msg_res($response['responseMessage']);
            }

            return $this->ok_data_res($response);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '] ' . $e->getMessage());
            return $this->error_res(500);
        }
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
