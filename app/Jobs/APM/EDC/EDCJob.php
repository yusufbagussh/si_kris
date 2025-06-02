<?php

namespace App\Jobs\APM\EDC;

use App\Models\EdcPayment;
use App\Models\PatientPayment;
use App\Models\PatientPaymentDetail;
use App\Services\BRI\EDCService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EDCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private EDCService $ecrLinkService;
    private array $data;
    private $apmWebhookSecret;
    private $apmWebhookBaseUrl;

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        // Load EDC configuration from environment variables
        $this->data = $data;
        $this->ecrLinkService = new EDCService();
        $this->apmWebhookSecret = config('edc.webhook_secret');
        $this->apmWebhookBaseUrl = config('edc.webhook_url');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::beginTransaction();
        $response = null;
        try {
            switch ($this->data['action']) {
                case 'Sale':
                    $response = $this->ecrLinkService->sale($this->data);
                    if ($this->data['patient_payment'] != null) {
                        $patientPayment = $this->data['patient_payment'];
                        $this->saveEdcPayment($this->data['patient_payment']->id, $response);
                    } else {
                        $patientPayment = $this->savePatientPayment($this->data['request']);
                        $this->saveEdcPayment($patientPayment->id, $response);
                    }
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink sale transaction job executed]');
                    break;
                case 'contactless':
                    $response = $this->ecrLinkService->contactless($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink sale transaction job executed]');
                    break;
                case 'void':
                    $response = $this->ecrLinkService->void($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink void transaction job executed]');
                    break;
                case 'check-status-qris':
                    $response = $this->ecrLinkService->checkStatusQR($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink checkStatusQR transaction job executed]');
                    break;
                case 'refund-qris':
                    $response = $this->ecrLinkService->refundQR($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink refund transaction job executed]');
                    break;
                case 'reprint-last':
                    $response = $this->ecrLinkService->reprintLast($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink reprintLast transaction job executed]');
                    break;
                case 'reprint-any-transaction':
                    $response = $this->ecrLinkService->reprintAnyTransaction($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink reprintAnyTransaction transaction job executed]');
                    break;
                case 'settlement':
                    $response = $this->ecrLinkService->settlement($this->data);
                    Log::Info('[200][Job][EDC][EDCJob][ECRLink settlement transaction job executed]');
                    break;
                default:
                    Log::error('[400][Job][EDC][EDCJob] Invalid action: ' . $this->data['action']);
                    break;
            }
            $this->sendToWebhook($patientPayment, $response);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[' . $e->getCode() . '][Job][EDC][EDCJob] ' . $e->getMessage());
        }
    }

    private function savePatientPayment($request)
    {
        Log::info('Save Patient Payment');
        $patientPayment = PatientPayment::create([
            'medical_record_no' => $request['medical_record_no'],
            'registration_no' => $request['registration_no'],
            'total_amount' => $request['total_amount'],
            'payment_method' => $this->data['method'],
            'payment_method_code' => $this->checkPaymentMethod($this->data['method'])
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

    private function generateGeneralResponse($response)
    {
        return [
            'method' => $response['method'],
            'action' => $response['action'],
            'status' => $response['status'],
            'message' => $response['msg'],
            'trx_id' => $response['trx_id'] ?? null,
            'reference_number' => $response['reference_number'] ?? null,
            'reff_id' => $response['reff_id'] ?? null,
            'trace_number' => $response['trace_number'] ?? null,
            'payment_method_code' => $this->checkPaymentMethod($response['method']),
        ];
    }

    private function generatePaymentResponse($response, $registrationNo, $billingList)
    {
        return [
            'registration_no' => $registrationNo,
            'billing_list' => $billingList,
            'remarks' => "Pembayaran melalui {$response['method']} dengan jumlah Rp{$response['amount']},00",
            'reference_no' => $response['reference_number'],
            'status' => $response['status'],
            'message' => $response['msg'],
            'amount' => $response['amount'],
            'issuer_name' => $response['method'],
            'payment_method_code' => $this->checkPaymentMethod($response['method']),
        ];
    }

    private function sendToWebhook($patientPayment, $response)
    {
        $data = [];
        if (in_array($response['action'], ['Sale', 'Contactless'])) {

            $billingList = [];
            foreach ($patientPayment->patientPaymentDetail as $detail) {
                $billingAmount = intval($detail->billing_amount);
                $billingList[] = "{$detail->billing_no}-{$billingAmount}";
            }

            $data['billList'] = implode(',', $billingList);

            $data = $this->generatePaymentResponse(
                $response,
                $patientPayment->registration_no,
                $billingList
            );
        } else {
            $data =  $this->generateGeneralResponse($response);
        }


        $headersWebhook = [
            'Content-Type' => 'application/json',
            'X-Signature' => hash_hmac('sha256', json_encode($data), $this->apmWebhookSecret),
        ];

        Log::info('Callback APM Request', [
            'headers' => $headersWebhook,
            'data' => $data,
        ]);

        Log::info('info', [
            'url' => $this->apmWebhookBaseUrl . '/payment/bank/kris-card-callback',
            'secret' => $this->apmWebhookSecret,
        ]);

        $response = Http::withHeaders($headersWebhook)->post($this->apmWebhookBaseUrl . '/payment/bank/kris-card-callback', $data);

        Log::info('Callback APM Response', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    private function saveEdcPayment($patientPaymentID, $response)
    {
        Log::info('Save EDC Payment');
        EdcPayment::create([
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
}
