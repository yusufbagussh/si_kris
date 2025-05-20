<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\MedinTagihanModel;
use App\Traits\MessageResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class KasirCetakanController extends Controller
{
    use MessageResponseTrait;

    public function getTransactionPayment(Request $request)
    {
        try {
            $registrationId = $request->input('registrationId');

            $query = MedinTagihanModel::getTransactionPayment($registrationId);

            if (isset($query) && $query != null) {
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Cetakan][getTransactionPayment] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function getListReceipt(Request $request)
    {
        try {
            $registrationId = $request->input('registrationId');

            $query = MedinTagihanModel::getListReceipt($registrationId);

            if (isset($query) && $query != null) {
                $res = $this->ok_data_res($query);
            } else {
                $res = $this->not_found_res();
            }

            return response()->json($res);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Cetakan][getListReceipt] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function printReceipt()
    {
        try {
            // $registrationId = $request->input('registrationId');

            // $query = KasirRajalModel::getListReceipt($registrationId);

            // if (isset($query) && $query != null) {
            // 	$res = $this->ok_data_res($query);
            // } else {
            // 	$res = $this->not_found_res();
            // }
            // Path ke gambar
            $path = asset('res/images/logo-transparent.png');

            $data['logo'] = base64_encode(file_get_contents($path));

            $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => [148, 210], 'margin_top' => 7]);
            $mpdf->WriteHTML(view('prints.kasir.print-cetakan-kwitansi', $data));

            return response()->stream(
                function () use ($mpdf) {
                    $mpdf->Output();
                },
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="document.pdf"',
                ]
            );
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Cetakan][printReceipt] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function printCetakanKwitansi()
    {
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => [148, 210], 'margin_top' => 7]);

        $path = asset('res/images/logo-transparent.png');
        $data['logo'] = base64_encode(file_get_contents($path));
        $mpdf->WriteHTML(view('prints.kasir.print-cetakan-kwitansi', $data));

        $mpdf->Output();
    }


    /*
    public function printDaftarPasien()
	{
		$kodeDokter	= $this->request->getPost("kodeDokter");
		$tanggalPeriksa	= $this->request->getPost("tanggalPeriksa");

		// $kodeBooking = substr($kodeDokter, 2, 3) . substr(str_replace("-", "", $tanggalPeriksa), 2);
		$this->KlinikSwabModel = new KlinikSwabModel();

		$listPasien = $this->KlinikSwabModel->getListPasien(substr($kodeDokter, 2), $tanggalPeriksa);

		if (count($listPasien) > 0) {

			$listPasien[0]["logo_soba"] = base64_encode(file_get_contents(WRITEPATH . "/images/soba.jpg"));
			$listPasien[0]["tanggal_periksa"] = $tanggalPeriksa;

			if (stripos($kodeDokter, 'SALIVA')) {
				$listPasien[0]["judul_pemeriksaan"] = "PCR SALIVA";
			} elseif (stripos($kodeDokter, 'ANTIGEN')) {
				$listPasien[0]["judul_pemeriksaan"] = "ANTIGEN";
			} else {
				$listPasien[0]["judul_pemeriksaan"] = "PCR";
			}

			$data["listPasien"] = $listPasien;

			$mpdf = new Mpdf([
				"format"		=> "A4",
				"margin_top"	=> 5,
				"margin_left"	=> 10,
				"margin_right"	=> 10,
				"margin_bottom"	=> 5,
				"orientation"	=> "P"
			]);

			ob_start();
			$html = view("prints/print_daftar_pasien", $data);
			$mpdf->AddPage();
			ob_end_clean();
			$mpdf->WriteHTML($html);
			$path = WRITEPATH . DOWNLOAD_TEMPLATE . $kodeDokter . "-" . $tanggalPeriksa . ".pdf";
			$mpdf->Output($path, "F");

			$file_pdf = base64_encode(file_get_contents($path, true));
			//$inifile  = addslashes($file_pdf);

			$dataPdf = array(
				"code"		=> 200,
				"hasil_pdf"	=> $file_pdf
			);

			if (file_exists(''.$path.'')) {
				unlink(''.$path.'');
			}

			echo json_encode($dataPdf);
		} else {
			echo json_encode(array(
				"code"		=> 400,
				"message"	=> "Tidak ada pendaftar."
			));
		}
	}
    */
}
