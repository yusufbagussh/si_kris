<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Data Pasien & Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
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

        .billing-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
        }

        .remove-billing {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            color: #dc3545;
        }

        #total-amount {
            font-weight: bold;
            font-size: 1.2rem;
        }

        .field-error {
            border-color: #dc3545;
        }

        .error-feedback {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Cek Data Pasien & Transaksi</h2>

                        <!-- Form Input -->
                        <form id="checkPatientForm" action="{{ route('qris.generate-qr') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Nomor Rekam Medis:</label>
                                <input type="text" id="medical_record_no" name="medical_record_no"
                                    class="form-control" maxlength="14" placeholder="">
                                <div id="medical_record_no_error" class="error-feedback"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor Registrasi:</label>
                                <input type="text" id="registration_no" name="registration_no" class="form-control"
                                    placeholder="Contoh: OPR/20250416/00001" required>
                                <div id="registration_no_error" class="error-feedback"></div>
                            </div>

                            <h4 class="mt-4 mb-3">Daftar Tagihan</h4>
                            <div id="billing-container">
                                <div class="billing-item">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Nomor Tagihan:</label>
                                            <input type="text" class="form-control billing-no"
                                                placeholder="Contoh: OPB/20250416/00001" required>
                                            <div class="error-feedback billing-no-error"></div>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label">Jumlah Tagihan:</label>
                                            <input type="number" class="form-control billing-amount"
                                                placeholder="Contoh: 90000" required>
                                            <div class="error-feedback billing-amount-error"></div>
                                        </div>
                                    </div>
                                    <span class="remove-billing">&times;</span>
                                </div>
                            </div>
                            <div id="billing_list_error" class="error-feedback mb-2"></div>

                            <div class="mb-3">
                                <button type="button" id="add-billing" class="btn btn-secondary">+ Tambah
                                    Tagihan</button>
                            </div>

                            <div class="mb-3 mt-4">
                                <label class="form-label">Total Tagihan:</label>
                                <div id="total-amount" class="form-control bg-light">Rp 0</div>
                                <input type="hidden" id="total_amount_input" name="total_amount" value="0">
                                <div id="total_amount_error" class="error-feedback"></div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran:</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="021" selected>QRIS</option>
                                </select>
                                <div id="payment_method_error" class="error-feedback"></div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Buat QR Code</button>
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

        // Clear all error messages
        function clearValidationErrors() {
            // Remove all error classes
            document.querySelectorAll('.field-error').forEach(element => {
                element.classList.remove('field-error');
            });

            // Clear all error messages
            document.querySelectorAll('.error-feedback').forEach(element => {
                element.textContent = '';
            });
        }

        // Display validation errors
        function displayValidationErrors(errors) {
            clearValidationErrors();

            // Process nested error messages
            if (typeof errors === 'object' && errors !== null) {
                for (const [field, fieldErrors] of Object.entries(errors)) {
                    const errorElement = document.getElementById(`${field}_error`);

                    if (errorElement) {
                        // Handle array of errors
                        if (Array.isArray(fieldErrors)) {
                            errorElement.textContent = fieldErrors.join(', ');
                        }
                        // Handle string error
                        else if (typeof fieldErrors === 'string') {
                            errorElement.textContent = fieldErrors;
                        }

                        // Add error class to the input field
                        const inputField = document.getElementById(field);
                        if (inputField) {
                            inputField.classList.add('field-error');
                        }
                    }

                    // Handle billing list errors
                    if (field.startsWith('billing_list.')) {
                        const parts = field.split('.');
                        if (parts.length === 3) {
                            const index = parseInt(parts[1]);
                            const subField = parts[2];

                            const billingItems = document.querySelectorAll('.billing-item');
                            if (billingItems.length > index) {
                                let errorClass = subField === 'billing_no' ? '.billing-no-error' : '.billing-amount-error';
                                const errorElement = billingItems[index].querySelector(errorClass);
                                if (errorElement) {
                                    if (Array.isArray(fieldErrors)) {
                                        errorElement.textContent = fieldErrors.join(', ');
                                    } else {
                                        errorElement.textContent = fieldErrors;
                                    }
                                }

                                let inputClass = subField === 'billing_no' ? '.billing-no' : '.billing-amount';
                                const inputField = billingItems[index].querySelector(inputClass);
                                if (inputField) {
                                    inputField.classList.add('field-error');
                                }
                            }
                        }
                    }
                }
            }
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

        // Add new billing item
        document.getElementById('add-billing').addEventListener('click', function() {
            const billingItem = document.createElement('div');
            billingItem.className = 'billing-item';
            billingItem.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Nomor Tagihan:</label>
                        <input type="text" class="form-control billing-no" placeholder="Contoh: OPB/20250416/00001" required>
                        <div class="error-feedback billing-no-error"></div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Jumlah Tagihan:</label>
                        <input type="number" class="form-control billing-amount" placeholder="Contoh: 90000" required>
                        <div class="error-feedback billing-amount-error"></div>
                    </div>
                </div>
                <span class="remove-billing">&times;</span>
            `;
            document.getElementById('billing-container').appendChild(billingItem);

            // Add event listener to the newly created remove button
            billingItem.querySelector('.remove-billing').addEventListener('click', function() {
                this.parentElement.remove();
                calculateTotal();
            });

            // Add event listener to the amount input for recalculating total
            billingItem.querySelector('.billing-amount').addEventListener('input', calculateTotal);
        });

        // Initial setup for first billing item
        document.querySelector('.remove-billing').addEventListener('click', function() {
            if (document.querySelectorAll('.billing-item').length > 1) {
                this.parentElement.remove();
                calculateTotal();
            } else {
                showToast('Info', 'Minimal harus ada satu tagihan', 'info');
            }
        });

        // Add event listener to the first amount input
        document.querySelector('.billing-amount').addEventListener('input', calculateTotal);

        // Calculate total amount from all billing items
        function calculateTotal() {
            let total = 0;
            const amountInputs = document.querySelectorAll('.billing-amount');

            amountInputs.forEach(input => {
                const amount = parseFloat(input.value) || 0;
                total += amount;
            });

            document.getElementById('total-amount').textContent = 'Rp ' + total.toLocaleString('id-ID');
            document.getElementById('total_amount_input').value = total;
        }

        document.getElementById('checkPatientForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitBtn = document.querySelector('button[type="submit"]');

            // Clear previous errors
            clearValidationErrors();

            // Collect form data in the required format
            const medical_record_no = document.getElementById('medical_record_no').value;
            const registration_no = document.getElementById('registration_no').value;
            const payment_method = document.getElementById('payment_method').value;
            const total_amount = document.getElementById('total_amount_input').value;

            // Collect billing items
            const billingItems = document.querySelectorAll('.billing-item');
            const billing_list = [];

            for (const item of billingItems) {
                const billing_no = item.querySelector('.billing-no').value;
                const billing_amount = item.querySelector('.billing-amount').value;

                if (!billing_no || !billing_amount) {
                    showToast('Error', 'Semua kolom tagihan harus diisi', 'error');
                    return;
                }

                billing_list.push({
                    billing_no: billing_no,
                    billing_amount: billing_amount
                });
            }

            // Create request payload
            const requestData = {
                medical_record_no,
                registration_no,
                billing_list,
                total_amount,
                payment_method
            };

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
                    body: JSON.stringify(requestData),
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Buat QR Code';

                // Close the loading alert
                Swal.close();

                // Handle response based on status
                if (result.code == 200) {
                    // Success case
                    if (result.data && result.data.qrContent) {
                        document.getElementById('qrCodeContainer').classList.remove('d-none');
                        document.getElementById('qrcode').innerHTML = '';

                        var typeNumber = 0; // 0 untuk auto-detect ukuran
                        var errorCorrectionLevel = 'L';
                        var qr = qrcode(typeNumber, errorCorrectionLevel);
                        qr.addData(result.data.qrContent);
                        qr.make();
                        document.getElementById('qrcode').innerHTML = qr.createImgTag(4);

                        // Show success toast
                        showToast('Berhasil', 'QR Code berhasil dibuat', 'success');
                    } else if (result.message) {
                        // Success but with a message (like "Transaksi sudah dibayar")
                        showToast('Informasi', result.message, 'info');
                    }
                } else {
                    // Error case with validation errors
                    if (result.code == 400 && typeof result.message === 'object') {
                        // Handle validation errors
                        displayValidationErrors(result.message);

                        // Show summary error notification
                        Swal.fire({
                            title: 'Validasi Gagal',
                            text: 'Mohon periksa kembali data yang Anda masukkan',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // General error case
                        const errorMessage = typeof result.message === 'string' ?
                            result.message :
                            'Terjadi kesalahan saat memproses permintaan.';

                        Swal.fire({
                            title: 'Gagal',
                            text: errorMessage,
                            icon: 'error',
                            confirmButtonText: 'Coba Lagi'
                        });
                    }

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
                submitBtn.innerHTML = 'Buat QR Code';
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
