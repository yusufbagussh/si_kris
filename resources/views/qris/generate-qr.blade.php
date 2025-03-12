<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Data Pasien & Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Cek Data Pasien & Transaksi</h2>

                        <!-- Form Input -->
                        <form id="checkPatientForm" action="{{ route('qris.generate-qr') }}" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nomor Rekam Medis:</label>
                                <input type="text" id="medical_record_no" name="medical_record_no"
                                    class="form-control" required maxlength="14" placeholder="">
                                <div id="errorMessage" class="text-danger mt-1"></div>
                            </div>
                            {{-- <div class="mb-3">
                                <label class="form-label">Tanggal Pasien:</label>
                                <input type="date" name="patient_date" class="form-control">
                            </div> --}}
                            <button type="submit" class="btn btn-primary w-100">Cek Data</button>
                        </form>


                        <!-- Tempat Hasil -->
                        <div id="qrCodeContainer" class="text-center mt-4 d-none">
                            <h4>QR Code:</h4>
                            <div id="qrcode"></div>
                        </div>
                        {{--                    <div id="patientResult" class="mt-4 d-none"> --}}
                        {{--                        <h4>Data Pasien</h4> --}}
                        {{--                        <p><strong>Nama:</strong> <span id="patientName"></span></p> --}}
                        {{--                        <p><strong>Umur:</strong> <span id="patientAge"></span></p> --}}
                        {{--                        <p><strong>Tanggal Kunjungan:</strong> <span id="visitDate"></span></p> --}}

                        {{--                        <h4 class="mt-3">Riwayat Transaksi</h4> --}}
                        {{--                        <table class="table table-bordered"> --}}
                        {{--                            <thead> --}}
                        {{--                            <tr> --}}
                        {{--                                <th>Referensi</th> --}}
                        {{--                                <th>Jumlah</th> --}}
                        {{--                                <th>Status</th> --}}
                        {{--                            </tr> --}}
                        {{--                            </thead> --}}
                        {{--                            <tbody id="transactionList"></tbody> --}}
                        {{--                        </table> --}}

                        {{--                        <h4 class="mt-3">QR Code Pembayaran</h4> --}}
                        {{--                        <div id="qrCodeContainer" class="text-center d-none"> --}}
                        {{--                            <div id="qrcode"></div> --}}
                        {{--                        </div> --}}
                        {{--                    </div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Format otomatis: Tambah strip (-) setiap 2 angka
        document.getElementById('medical_record_no').addEventListener('input', function(event) {
            let value = this.value.replace(/\D/g, ''); // Hanya angka
            if (value.length > 8) value = value.substring(0, 8);

            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 2 === 0) {
                    formatted += '-';
                }
                formatted += value[i];
            }
            this.value = formatted;
        });


        document.getElementById('checkPatientForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);

            const response = await fetch("{{ route('qris.generate-qr') }}", {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const res = await response.json();
            if (res.data.qrContent) {
                document.getElementById('qrCodeContainer').classList.remove('d-none');
                document.getElementById('qrcode').innerHTML = '';
                // new QRCode(document.getElementById("qrcode"), res.data.qrContent);
                new QRCode(document.getElementById("qrcode"), {
                    text: res.data.qrContent,
                    width: 256,
                    height: 256,
                    correctLevel: QRCode.CorrectLevel
                        .L, // Gunakan Level Koreksi Rendah (L) agar muat
                    version: 10 // Gunakan versi QR lebih tinggi untuk string panjang
                });
            }
            // if (data.patient) {
            //     document.getElementById('patientResult').classList.remove('d-none');
            //     document.getElementById('patientName').textContent = data.patient.name;
            //     document.getElementById('patientAge').textContent = data.patient.age;
            //     document.getElementById('visitDate').textContent = data.patient.visit_date;
            //
            //     let transactionsHtml = '';
            //     data.patient.transactions.forEach(transaction => {
            //         transactionsHtml += `
        //                 <tr>
        //                     <td>${transaction.referenceNo}</td>
        //                     <td>IDR ${transaction.amount}</td>
        //                     <td>${transaction.status}</td>
        //                 </tr>
        //             `;
            //     });
            //     document.getElementById('transactionList').innerHTML = transactionsHtml;
            //
            //     if (data.qrCode) {
            //         document.getElementById('qrCodeContainer').classList.remove('d-none');
            //         document.getElementById('qrcode').innerHTML = '';
            //         // new QRCode(document.getElementById("qrcode"), data.qrCode);
            //         new QRCode(document.getElementById("qrcode"), {
            //             text: data.qrCode,
            //             width: 256,
            //             height: 256,
            //             correctLevel: QRCode.CorrectLevel
            //                 .L, // Gunakan Level Koreksi Rendah (L) agar muat
            //             version: 10 // Gunakan versi QR lebih tinggi untuk string panjang
            //         });
            //     }
            // }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
