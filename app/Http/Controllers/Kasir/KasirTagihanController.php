<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Libraries\Medinfras\KasirService;
use App\Models\MedinTagihanModel;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KasirTagihanController extends Controller
{
    use MessageResponseTrait;

    public function __construct()
    {
    }

    public function index()
    {
    }

    // Penarikan daftar pasien berdasarkan tanggal dan tipe transaksi 1202 /rajal
    public function listPatient(Request $request)
    {
        try {
            $visitDate = $request->input('tanggal');
            $serviceUnitId = $request->input('serviceUnitId');
            $paramedicID = $request->input('paramedicID');

            $query = MedinTagihanModel::getListPatient($visitDate, $serviceUnitId, $paramedicID);

            if (isset($query) && $query != null) {
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][listPatient] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    // Penarikan data lengkap Rincian Biaya tagihan pasien
    public function detailPayment(Request $request)
    {
        try {
            $registrationId = $request->input('registrationId');

            $query = MedinTagihanModel::getPatientByRegistrationID($registrationId);

            if (isset($query) && $query != null) {
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][detailPayment] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    // Penarikan data tagihan pasien yang dipilih sesuai registrasi ID
    public function listTagihanPasien(Request $request)
    {
        try {
            $registrationId = $request->input('registrationId');

            $query = MedinTagihanModel::getTagihanByRegistrationID($registrationId);

            if (isset($query) && $query != null) {
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][listTagihan] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    // Pembuatan tagihan dari rincian biaya di medinfras dengan status rincian sudah di Propose atau Belum dan transaksi di kunci maupun tidak
    public function getListTransaksiByRegistrationNo(Request $request)
    {
        try {
            $data = $request->all();

            $medin = new KasirService; //MedinfrasWsClient

            $registrationNo = str_replace("/", "_", $data['registrationNo']);

            $curl = json_decode($medin->get("transaction/base/list/$registrationNo"), true);
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

    // Pembuatan tagihan dari rincian biaya di medinfras dengan status rincian sudah di Propose atau Belum dan transaksi di kunci maupun tidak
    public function generatePaymentBill(Request $request)
    {
        try {
            $data = $request->all();

            $bills = explode(',', $data['detailList']);
            $detailList = [];

            for ($i = 0; $i < count($bills); $i++) {
                $detailList[$i] = ['TransactionNo' => $bills[$i]];
            }

            $dataPost = [
                'RegistrationNo' => $data['registrationNo'],
                'DetailList' => $detailList
            ];

            $medin = new KasirService;

            $curl = json_decode($medin->post('billing/base/generate', $dataPost), true);
            Log::info('[' . $curl['Status'] . '][Kasir][Rajal][Tagihan][generatePaymentBill] ' . json_encode($curl));
            if ($curl['Status'] == 'SUCCESS') {
                $res = $this->ok_msg_data_res($curl['Remarks'], json_decode($curl['Data'], true));
            } else {
                $res = $this->fail_msg_data_res($curl['Remarks'], $curl['Data']);
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][generatePaymentBill] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    // Melakukan kunci pada transaksi tagihan
    public function lockTransaction(Request $request)
    {
        try {
            $data = $request->all();

            $medin = new KasirService; //MedinfrasWsClient

            $curl = json_decode($medin->get('registration/base/lockdown/validation/' . str_replace("/", "_", $data['registrationNo'])), true);

            if ($curl['Status'] == 'SUCCESS') {

                //$query = MedinTagihanModel::getPatientRegistrationLock($data['registrationId']);
                //if (isset($query) && $query != null) {
                //$res = $this->ok_msg_data_res($curl['Remarks'], $query);
                //} else {
                //$res = $this->ok_msg_data_res($curl['Remarks'], []);
                //}
                //$res = $this->ok_msg_data_res($curl['Remarks'], $query);

                $res = $this->ok_msg_data_res($curl['Remarks'], $curl['Data']);
            } else {
                $res = $this->fail_msg_data_res($curl['Remarks'], $curl['Data']);
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Tagihan][lockTransaction] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }
}
/*
1. Lock Transaction : api/registration/base/lockdown/validation/{registrationNo} -> OPR_20240101_00001
2. List Transaksi: api/transaction/base/list/{RegistrationNo} -> OPR_20240101_00001
3. List Billing: api/billing/list/{registrationNo} -> OPR_20240101_00001
4. Generate Bill: api/billing/base/generate
4.1 Payload:
{
	"RegistrationNo":"OPR/20200101/00001",
	"DetailList": [
		{
			"TransactionNo": "OPC/20200101/00001"
		},
		{
			"TransactionNo": "OPC/20200101/00002"
		}
	]
}
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
