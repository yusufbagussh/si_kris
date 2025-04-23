<?php

namespace App\Http\Controllers;

use App\Models\MedinTagihanModel;
use App\Traits\MessageResponseTrait;
use App\Traits\NavigationMenuTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class KasirRajalMainController extends Controller
{
    use NavigationMenuTrait, MessageResponseTrait;

    private $color = 'info';

    public function __construct()
    {
    }

    public function index()
    {
        $page = [
            'image' => '',
            'title' => 'Kasir',
            'color' => $this->color,
        ];

        $navbar = array(
            [
                'title' => 'Rawat Jalan',
                'url' => 'kasir/rawat-jalan',
                'icon' => '<i class="bi bi-person-walking me-2"></i>'
            ],
            [
                'title' => 'Rawat Inap',
                'url' => 'kasir/farmasi-ranap',
                'icon' => '<i class="bi bi-hospital me-2"></i>'
            ],
        );
        $data = $this->generateNavigation();

        return view('moduls.kasir.rawat-jalan', compact('data', 'navbar', 'page'));
    }

    public function generateNavigation()
    {
        // Generate Navigation Menu Main & Secondary
        $nav_list = array(
            [
                'nav-main' => 'Tagihan & Pembayaran',
                'icon' => '<i class="bi bi-folder pe-2"></i>',
                'nav-secondary' => [
                    [
                        'name' => 'Tagihan',
                        'class' => ''
                    ],
                    [
                        'name' => 'Pembayaran',
                        'class' => ''
                    ],
                    [
                        'name' => 'Cetakan',
                        'class' => ''
                    ]
                ]
            ],
        );

        $main_content = 'pages.kasir.payment';

        $url = '/kasir/rawat-jalan';

        $content_data = $this->kasirRajalPembayaranInfo();

        $data['nav'] = $this->createNavMenu($nav_list, $main_content, $content_data, $this->color, $url);

        // Generate Menu Sidebar
        $sidebar_list = array(
            'Riwayat Kunjungan',
            'Form Berkas Pasien',
            'Riwayat Operasi',
            'Konsultasi',
            'Hasil Laboratorium',
            'Catatan Kunjungan Rawat Jalan'
        );

        $data['menu'] = $this->createSidebarMenu($sidebar_list);

        // Generate Print Sidebar
        $print_list = array(
            'Riwayat Kunjungan',
            'Form Berkas Pasien',
            'Riwayat Operasi',
            'Konsultasi',
            'Hasil Laboratorium',
            'Catatan Kunjungan Rawat Jalan'
        );

        $data['print'] = $this->createSidebarPrint($print_list);

        $data['klinik'] = $this->klinikRawatJalan();

        $data['dokter'] = $this->dokterRawatJalan();

        return $data;
    }

    protected function klinikRawatJalan()
    {
        try {
            $query = json_decode(json_encode(MedinTagihanModel::getOutpatientClinic()), true);
            return $this->ok_data_res($query);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Main][klinikRawatJalan] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    protected function dokterRawatJalan()
    {
        try {
            $query = json_decode(json_encode(MedinTagihanModel::getOutpatientParamedic()), true);
            return $this->ok_data_res($query);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Main][dokterRawatJalan] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    protected function kasirRajalPembayaranInfo()
    {
        try {
            $now = Carbon::now('Asia/Jakarta');
            $hour = (int)$now->format('H');
            info($hour);

            $res = [];
            $a = [];
            $b = [];
            $c = [];
            $d = [];
            $ax = 0;
            $bx = 0;
            $cx = 0;

            $query = json_decode(json_encode(MedinTagihanModel::getStandardCodePaymentInfo()), true);
            if (isset($query) && $query != null) {
                for ($i = 0; $i < count($query); $i++) {
                    if (stripos($query[$i]['StandardCodeID'], '168^')) {
                        if ($hour >= 7 && $hour < 14 && $query[$i]['StandardCodeName'] == 'Pagi') {
                            $a[$ax]['StandardCodeID'] = str_replace('X168^', '', $query[$i]['StandardCodeID']);
                            $a[$ax]['StandardCodeName'] = $query[$i]['StandardCodeName'];
                            $ax += 1;
                        } else if ($hour >= 14 && $hour < 21 && $query[$i]['StandardCodeName'] == 'Siang') {
                            $a[$ax]['StandardCodeID'] = str_replace('X168^', '', $query[$i]['StandardCodeID']);
                            $a[$ax]['StandardCodeName'] = $query[$i]['StandardCodeName'];
                            $ax += 1;
                        } else if ($hour >= 21 && $hour < 7 && $query[$i]['StandardCodeName'] == 'Malam') {
                            $a[$ax]['StandardCodeID'] = str_replace('X168^', '', $query[$i]['StandardCodeID']);
                            $a[$ax]['StandardCodeName'] = $query[$i]['StandardCodeName'];
                            $ax += 1;
                        }
                    } else if (stripos($query[$i]['StandardCodeID'], '035^')) {
                        $b[$bx]['StandardCodeID'] = str_replace('X035^', '', $query[$i]['StandardCodeID']);
                        $b[$bx]['StandardCodeName'] = $query[$i]['StandardCodeName'];
                        $bx += 1;
                    } else if (stripos($query[$i]['StandardCodeID'], '169^')) {
                        $c[$cx]['StandardCodeID'] = str_replace('X169^', '', $query[$i]['StandardCodeID']);
                        $c[$cx]['StandardCodeName'] = $query[$i]['StandardCodeName'];
                        $cx += 1;
                    }
                }
            }

            $queryBank = json_decode(json_encode(MedinTagihanModel::getBankInfo()), true);

            if (isset($queryBank) && $queryBank != null) {
                for ($i = 0; $i < count($queryBank); $i++) {
                    $d[$i]['BankCode'] = $queryBank[$i]['BankCode'];
                    $d[$i]['BankName'] = $queryBank[$i]['BankName'];
                }
            }

            $data = array(
                'shift' => $a,
                'paymentMethod' => $b,
                'cashierGroup' => $c,
                'bank' => $d
            );

            return $this->ok_data_res($data);
        } catch (\Throwable $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Main][kasirRajalPembayaranInfo] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function listMesinEDCBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_code' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $query = json_decode(json_encode(MedinTagihanModel::getEDCMachineByBankID($request->bank_code)), true);
            return $this->ok_data_res($query);
        } catch (\Exception $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Main][listMesinEDCBank] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }

    public function listTipeKartuBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'machine_code' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail_msg_res($validator->errors());
        }

        try {
            $query = json_decode(json_encode(MedinTagihanModel::getCardTypeByEDCMachineID($request->machine_code)), true);
            $newResponse = [];
            if (isset($query) && $query != null) {
                foreach ($query as $q) {
                    $cardTypeCode = str_replace('X102^', '', $q['GCCardType']);
                    $cardProviderCode = str_replace('X142^', '', $q['GCCardProvider']);
                    $newResponse[] = [
                        'CardTypeID' => $cardTypeCode . '-' . $cardProviderCode,
                        'CardTypeName' => $q['CardType'] . '-' . $q['CardProvider']
                    ];
                }
            }

            return $this->ok_data_res($newResponse);
        } catch (\Throwable $e) {
            Log::error('[' . $e->getCode() . '][Kasir][Rajal][Main][listTipeKartuBank] ' . $e->getMessage());
            return response()->json($this->error_res($e->getCode()));
        }
    }
}
