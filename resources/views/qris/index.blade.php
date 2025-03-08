<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRIS Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="text-center mb-4">Generate QRIS Payment</h2>

                    <!-- Form Generate QR -->
                    <form id="generateQRForm" action="{{ route('qris.generate') }}" method="POST">
                        {{-- @csrf --}}
                        <div class="mb-3">
                            <label class="form-label">Partner Reference No:</label>
                            <input type="text" name="partnerReferenceNo" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (IDR):</label>
                            <input type="number" name="amount" class="form-control" required>
                        </div>
                        {{-- <div class="mb-3">
                            <label class="form-label">Merchant ID:</label>
                            <input type="text" name="merchantId" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Terminal ID:</label>
                            <input type="text" name="terminalId" class="form-control" required>
                        </div> --}}
                        <button type="submit" class="btn btn-primary w-100">Generate QR</button>
                    </form>

                    <!-- Tempat QR Code -->
                    <div id="qrCodeContainer" class="text-center mt-4 d-none">
                        <h4>QR Code:</h4>
                        <div id="qrcode"></div>
                    </div>

                    <hr class="my-4">

                    <!-- Form Inquiry Payment -->
                    <h2 class="text-center mb-3">Inquiry Payment</h2>
                    <form id="inquiryForm" action="{{ route('qris.inquiry') }}" method="POST">
                        {{-- @csrf --}}
                        <div class="mb-3">
                            <label class="form-label">Original Reference No:</label>
                            <input type="text" name="original_reference_no" class="form-control" required>
                        </div>
                        {{-- <div class="mb-3">
                            <label class="form-label">Service Code:</label>
                            <input type="text" name="serviceCode" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Terminal ID:</label>
                            <input type="text" name="terminalId" class="form-control" required>
                        </div> --}}
                        <button type="submit" class="btn btn-success w-100">Check Payment Status</button>
                    </form>

                    <!-- Tempat Hasil Inquiry -->
                    <div id="inquiryResult" class="text-center mt-4 d-none">
                        <h4>Inquiry Result:</h4>
                        <p id="statusMessage" class="fw-bold"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('generateQRForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const formData = new FormData(this);

        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.qrContent) {
            document.getElementById('qrCodeContainer').classList.remove('d-none');
            document.getElementById('qrcode').innerHTML = '';
            // new QRCode(document.getElementById("qrcode"), data.qrContent);
            new QRCode(document.getElementById("qrcode"), {
                text: data.qrContent,
                width: 256,
                height: 256,
                correctLevel: QRCode.CorrectLevel
                    .L, // Gunakan Level Koreksi Rendah (L) agar muat
                version: 10 // Gunakan versi QR lebih tinggi untuk string panjang
            });
        }
    });

    document.getElementById('inquiryForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const formData = new FormData(this);

        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        document.getElementById('inquiryResult').classList.remove('d-none');
        document.getElementById('statusMessage').textContent =
            `Status: ${data.transactionStatusDesc || 'Unknown'}`;
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
