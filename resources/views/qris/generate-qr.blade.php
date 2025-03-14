<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Data Pasien & Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Custom styles for toasts */
        .swal2-toast {
            max-width: 350px !important;
            font-size: 0.9rem !important;
        }
    </style>
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
                            <button type="submit" class="btn btn-primary w-100">Cek Data</button>
                        </form>

                        <!-- Tempat Hasil -->
                        <div id="qrCodeContainer" class="text-center mt-4 d-none">
                            <h4>QR Code:</h4>
                            <div id="qrcode"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to show toast notification
        function showToast(title, message, icon = 'success') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: icon,
                title: title,
                text: message
            });
        }

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
            const submitBtn = document.querySelector('button[type="submit"]');

            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Memproses...';

                // Show loading SweetAlert
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch("{{ route('qris.generate-qr') }}", {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cek Data';

                // Close the loading alert
                Swal.close();

                // Handle response based on status
                if (result.code == 200) {
                    // Success case
                    if (result.data && result.data.qrContent) {
                        document.getElementById('qrCodeContainer').classList.remove('d-none');
                        document.getElementById('qrcode').innerHTML = '';

                        new QRCode(document.getElementById("qrcode"), {
                            text: result.data.qrContent,
                            width: 256,
                            height: 256,
                            correctLevel: QRCode.CorrectLevel.L,
                            version: 10
                        });

                        // Show success toast
                        showToast('Berhasil', 'QR Code berhasil dibuat', 'success');
                    } else if (result.message) {
                        // Success but with a message (like "Transaksi sudah dibayar")
                        showToast('Informasi', result.message, 'info');
                    }
                } else {
                    // Error case - still use modal for errors
                    const errorMessage = result.message || 'Terjadi kesalahan saat memproses permintaan.';

                    Swal.fire({
                        title: 'Gagal',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'Coba Lagi'
                    });

                    // Hide QR code if previously shown
                    document.getElementById('qrCodeContainer').classList.add('d-none');
                }
            } catch (error) {
                console.error('Error:', error);

                // Close the loading alert first if it's still open
                Swal.close();

                Swal.fire({
                    title: 'Error',
                    text: 'Terjadi kesalahan pada sistem. Silakan coba lagi nanti.',
                    icon: 'error',
                    confirmButtonText: 'Tutup'
                });

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cek Data';
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
