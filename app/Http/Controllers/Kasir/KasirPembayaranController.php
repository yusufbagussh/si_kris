<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Libraries\Medinfras\KasirService;
use App\Models\MedinTagihanModel;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KasirPembayaranController extends Controller
{
    use MessageResponseTrait;

    public function __construct() {}

    public function index() {}

    // Penarikan data tagihan pasien yang telah terbentuk untuk di bayar
    public function getPatientBill(Request $request)
    {
        try {
            $registrationId = $request->input('registrationId');

            $query = MedinTagihanModel::getPatientBillingNew($registrationId);

            $query = json_decode(json_encode($query), true);

            if (isset($query) && $query != null) {
                for ($i = 0; $i < count($query); $i++) {
                    $transaksi = MedinTagihanModel::getPatientBillTransaction($query[$i]['PatientBillingID']);

                    if (isset($transaksi) && $transaksi != null) {
                        $transaksi = json_decode(json_encode($transaksi), true);
                        $counter = count($transaksi) - 1;
                        for ($j = 0; $j < count($transaksi); $j++) {
                            $query[$i]['TransactionNo'] .= ' (' . $transaksi[$j]['TransactionNo'] .
                                ' - ' . $transaksi[$j]['ServiceUnitName'] . ')' .
                                ' = ' . $transaksi[$j]['TotalAmount'];

                            if ($j != $counter) {
                                $query[$i]['TransactionNo'] .= '<br>';
                            }
                        }
                    }
                }
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Pembayaran][getPatientBilling] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function getPatientBillByRegistrationNo(Request $request)
    {
        try {
            $data = $request->all();

            $medin = new KasirService; //MedinfrasWsClient

            $registrationNo = str_replace("/", "_", $data['registrationNo']);

            $curl = json_decode($medin->get("billing/list/$registrationNo"), true);
            if ($curl['Status'] == 'SUCCESS') {
                $res = $this->ok_msg_data_res($curl['Remarks'], json_decode($curl['Data'], true));
            } else {
                $res = $this->fail_msg_data_res($curl['Remarks'], $curl['Data']);
            }
            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][lockTransaction] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    // Melakukan pembayaran bill pasien
    public function doPaymentBill(Request $request)
    {
        try {
            $data = $request->all();

            //$bills = explode(';', $data['noBills']);
            //$billsAmount = explode(',', $data['noAmounts']);
            $billList = explode(',', $data['billList']);
            $detailList = [];

            for ($i = 0; $i < count($billList); $i++) {
                $splitBill = explode('-', $billList[$i]);

                $detailList[$i] = [
                    'PatientBillingNo' => $splitBill[0],
                    'TotalPatientAmount' => (int)$splitBill[1]
                ];
            }

            $dataPost = [
                'RegistrationNo' => $data['registrationNo'],
                'BillingList' => $detailList,
                'PaymentInfo' => [
                    'Shift' => $data['shift'],
                    'CashierGroup' => $data['cashierGroup'],
                    'PaymentMethod' => $data['paymentMethod'],
                    'Referenceno' => $data['referenceNo'] ?? null,
                    'PaymentAmount' => (int)$data['paymentAmount'],
                    'BankCode' => $data['bankCode'] ?? null,
                    'Remarks' => $data['remarks'] ? $data['remarks'] : '',
                    'CardType' => $data['cardType'], // "001", //X102
                    'CardProvider' => $data['cardProvider'], // "009", //X142
                    'EDCMachineCode' => $data['machineCode'] // "EDC005",
                    /**Card Type */
                    // X102^001', 'Debit Card'
                    // X102^002', 'Visa'
                    // X102^003', 'Master'
                    // X102^008', 'QR/QRIS'
                    // X102^999', 'Other'
                    /**Card Provider */

                    // X142^003, BRI
                    // X142^009, CIMB Niaga

                    /**EDC Machine */
                    // EDC005, NIAGA-RI
                    // EDC006, NIAGA RI 2
                    // EDC007, NIAGA RI 3
                    // EDC008, NIAGA-PSP
                    // EDC009, NIAGA-KOS
                    // EDC010, NIAGA-ADM
                    // EDC013, BRI-RI
                    // EDC014, BRI RI 2
                    // EDC015, BRI-PSP

                ]
            ];

            $medin = new KasirService;

            $curl = json_decode($medin->post('billing/base/create/payment', $dataPost), true);
            //$curl['Status'] = 'FAILED';$curl['Remarks'] = 'FAILED';$curl['Data']=[];
            if ($curl['Status'] == 'SUCCESS') {
                $res = $this->ok_msg_data_res($curl['Remarks'], json_decode($curl['Data'], true));
            } else {
                $res = $this->fail_msg_data_res($curl['Remarks'], $curl['Data']);
            }

            //$res = [$dataPost, $curl];
            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Pembayaran][doPaymentBill] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }
}
/*
5. Create Payment: api/billing/base/create/payment
{
	"RegistrationNo":"OPR/20200101/00001",
	"BillingList": [
		{
			"PatientBillingNo": "IPB/20200101/00001",
			"TotalPatientAmount": 225000 -> decimal
		}
	],
	"PaymentInfo": {
		"Shift": "001",
		"CashierGroup": "001",
		"PaymentMethod": "004",
		"PaymentAmount": 225000,
		"Referenceno": "No.Referensi",
		"BankCode": "111-212",
		"Remarks": "Pembayaran melalui CIMB NIAGA Mandiri oleh pasien"
	}
}



*/
