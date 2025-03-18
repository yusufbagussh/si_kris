<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRIS Payment</title>
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

        .card {
            border-radius: 15px;
            border: none;
        }

        .btn-success {
            border-radius: 8px;
            padding: 10px;
            font-weight: 500;
        }

        /* Status badge styling */
        .status-badge {
            padding: 8px 16px;
            border-radius: 30px;
            display: inline-block;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #842029;
        }

        /* Transaction details styling */
        .transaction-details {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            text-align: left;
        }

        .detail-group {
            margin-bottom: 15px;
        }

        .detail-group h6 {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .detail-group p {
            margin-bottom: 0;
            font-size: 1rem;
        }

        .detail-divider {
            height: 1px;
            background-color: #dee2e6;
            margin: 15px 0;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <!-- Form Inquiry Payment -->
                        <h2 class="text-center mb-4">Status Pembayaran QRIS</h2>
                        <form id="inquiryForm" action="{{ route('qris.inquiry') }}" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nomor Referensi:</label>
                                <input type="text" name="original_reference_no" class="form-control" required
                                    placeholder="Masukkan nomor referensi transaksi">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Cek Status Pembayaran</button>
                        </form>

                        <!-- Tempat Hasil Inquiry -->
                        <div id="inquiryResult" class="text-center mt-4 d-none">
                            <div class="card bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Hasil Inquiry</h5>
                                    <div id="statusBadge" class="status-badge"></div>

                                    <div id="transactionDetails" class="transaction-details">
                                        <!-- Main Details -->
                                        <div class="detail-group">
                                            <h6>INFORMASI TRANSAKSI</h6>
                                            <p id="referenceNo"><strong>No. Referensi:</strong> <span>-</span></p>
                                            <p id="amount"><strong>Jumlah:</strong> <span>-</span></p>
                                            <p id="currency"><strong>Mata Uang:</strong> <span>-</span></p>
                                        </div>

                                        <div class="detail-divider"></div>

                                        <!-- Customer Details -->
                                        <div class="detail-group">
                                            <h6>INFORMASI CUSTOMER</h6>
                                            <p id="customerName"><strong>Nama:</strong> <span>-</span></p>
                                            <p id="customerNumber"><strong>Nomor:</strong> <span>-</span></p>
                                        </div>

                                        <div class="detail-divider"></div>

                                        <!-- Payment Details -->
                                        <div class="detail-group">
                                            <h6>INFORMASI PEMBAYARAN</h6>
                                            <p id="invoiceNumber"><strong>No. Invoice:</strong> <span>-</span></p>
                                            <p id="issuerName"><strong>Bank:</strong> <span>-</span></p>
                                            <p id="issuerRrn"><strong>RRN:</strong> <span>-</span></p>
                                            <p id="terminalId"><strong>Terminal ID:</strong> <span>-</span></p>
                                            <p id="mpan"><strong>MPAN:</strong> <span>-</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

        // Function to update field text
        function updateField(id, value, defaultText = '-') {
            const element = document.querySelector(`#${id} span`);
            if (element) {
                element.textContent = value || defaultText;
            }
        }

        document.getElementById('inquiryForm').addEventListener('submit', async function(event) {
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
                    text: 'Sedang memeriksa status pembayaran',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    willOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cek Status Pembayaran';

                // Close the loading alert
                Swal.close();

                // Handle response based on status
                if (result.status === 'ok' || result.code === 200) {
                    // Get the data object (handle both response structures)
                    const data = result.data || {};

                    // Success response from API
                    const transactionStatus = data.transactionStatusDesc || 'Unknown';
                    let statusType = 'pending';
                    let statusIcon = 'info';

                    // Determine status type for styling
                    if (transactionStatus.toUpperCase().includes('SUCCESS') ||
                        transactionStatus.toUpperCase().includes('BERHASIL')) {
                        statusType = 'success';
                        statusIcon = 'success';
                    } else if (transactionStatus.toUpperCase().includes('FAIL') ||
                        transactionStatus.toUpperCase().includes('GAGAL')) {
                        statusType = 'failed';
                        statusIcon = 'error';
                    }

                    // Show toast notification
                    showToast('Status Pembayaran', `Status: ${transactionStatus}`, statusIcon);

                    // Show result container
                    document.getElementById('inquiryResult').classList.remove('d-none');

                    // Update status badge
                    const statusBadge = document.getElementById('statusBadge');
                    statusBadge.className = `status-badge status-${statusType}`;
                    statusBadge.textContent = transactionStatus;

                    // Format amount (handle both object and string formats)
                    let amountValue = '-';
                    let currencyValue = '-';

                    if (data.amount) {
                        if (typeof data.amount === 'object') {
                            amountValue = parseFloat(data.amount.value).toLocaleString('id-ID');
                            currencyValue = data.amount.currency || 'IDR';
                        } else {
                            amountValue = parseFloat(data.amount).toLocaleString('id-ID');
                            currencyValue = 'IDR';
                        }
                    }

                    // Update main transaction details
                    updateField('referenceNo', data.originalReferenceNo);
                    updateField('amount', amountValue);
                    updateField('currency', currencyValue);

                    // Get additional info object
                    const additionalInfo = data.additionalInfo || {};

                    // Update customer details
                    updateField('customerName', additionalInfo.customerName);
                    updateField('customerNumber', additionalInfo.customerNumber);

                    // Update payment details
                    updateField('invoiceNumber', additionalInfo.invoiceNumber);
                    updateField('issuerName', additionalInfo.issuerName);
                    updateField('issuerRrn', additionalInfo.issuerRrn);
                    updateField('terminalId', data.terminalId);
                    updateField('mpan', additionalInfo.mpan);

                } else {
                    // Error case
                    const errorMessage = result.message ||
                    'Terjadi kesalahan saat memeriksa status pembayaran.';

                    Swal.fire({
                        title: 'Gagal',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonText: 'Coba Lagi'
                    });

                    // Hide result area if previously shown
                    document.getElementById('inquiryResult').classList.add('d-none');
                }
            } catch (error) {
                console.error('Error:', error);

                Swal.fire({
                    title: 'Error',
                    text: 'Terjadi kesalahan pada sistem. Silakan coba lagi nanti.',
                    icon: 'error',
                    confirmButtonText: 'Tutup'
                });

                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Cek Status Pembayaran';
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
