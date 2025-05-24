<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>EDC Payment Integration</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        .transaction-history {
            max-height: 400px;
            overflow-y: auto;
        }

        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .payment-method-btn {
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .status-pill {
            display: inline-block;
            padding: 0.25em 0.6em;
            border-radius: 10rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
        }

        .status-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-failed {
            background-color: #f8d7da;
            color: #842029;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #664d03;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
        }

        .transaction-item:not(:last-child) {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <h1 class="text-center mb-4">EDC Payment Integration</h1>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Transaksi Baru</h5>
                    </div>
                    <div class="card-body">
                        <form id="payment-form">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Jumlah Pembayaran (Rp)</label>
                                <input type="number" class="form-control" id="amount" name="amount" required
                                    min="1" step="1000" value="10000">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Metode Pembayaran</label>
                                <div>
                                    <button type="button" class="btn btn-outline-primary payment-method-btn"
                                        data-method="purchase">
                                        <i class="fas fa-credit-card"></i> Kartu Debit/Kredit
                                    </button>
                                    <button type="button" class="btn btn-outline-success payment-method-btn"
                                        data-method="brizzi">
                                        <i class="fas fa-wallet"></i> Brizzi
                                    </button>
                                    <button type="button" class="btn btn-outline-info payment-method-btn"
                                        data-method="qris">
                                        <i class="fas fa-qrcode"></i> QRIS
                                    </button>
                                    <button type="button" class="btn btn-outline-warning payment-method-btn"
                                        data-method="contactless">
                                        <i class="fas fa-wifi"></i> Contactless
                                    </button>
                                </div>
                                <input type="hidden" id="payment-method" name="method" value="purchase">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="process-payment-btn">
                                    <i class="fas fa-money-bill-wave"></i> Proses Pembayaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Status Transaksi Terakhir</h5>
                    </div>
                    <div class="card-body" id="current-transaction-status">
                        <div class="text-center text-muted">
                            <p>Belum ada transaksi aktif</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Riwayat Transaksi</h5>
                        <button class="btn btn-sm btn-light" id="clear-history-btn">
                            <i class="fas fa-trash"></i> Bersihkan
                        </button>
                    </div>
                    <div class="card-body transaction-history" id="transaction-history">
                        <div class="text-center text-muted">
                            <p>Belum ada riwayat transaksi</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="spinner-container">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 id="loading-message">Memproses Pembayaran...</h5>
            <p class="text-muted" id="loading-submessage">Silahkan ikuti instruksi di EDC</p>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="receipt-content">
                    <!-- Receipt content will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="print-receipt-btn">Cetak</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite('resources/js/app.js')

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentForm = document.getElementById('payment-form');
            const methodButtons = document.querySelectorAll('.payment-method-btn');
            const paymentMethodInput = document.getElementById('payment-method');
            const loadingOverlay = document.getElementById('loading-overlay');
            const loadingMessage = document.getElementById('loading-message');
            const loadingSubmessage = document.getElementById('loading-submessage');
            const transactionHistory = document.getElementById('transaction-history');
            const currentTransactionStatus = document.getElementById('current-transaction-status');
            const clearHistoryBtn = document.getElementById('clear-history-btn');
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            const receiptContent = document.getElementById('receipt-content');
            const printReceiptBtn = document.getElementById('print-receipt-btn');

            let currentActiveTransaction = null;

            // Aktifkan button metode pembayaran yang terpilih
            methodButtons.forEach(button => {
                button.addEventListener('click', function() {
                    methodButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    paymentMethodInput.value = this.dataset.method;
                });
            });

            // Default aktifkan metode "purchase"
            methodButtons[0].classList.add('active');

            // Riwayat transaksi dari localStorage
            let transactions = JSON.parse(localStorage.getItem('edc-transactions')) || [];
            renderTransactionHistory();

            // Form submit handler
            paymentForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const amount = document.getElementById('amount').value;
                const method = paymentMethodInput.value;
                const transactionId = generateTransactionId();

                // Tampilkan loading overlay
                loadingOverlay.style.display = 'flex';

                // Simpan transaksi aktif
                currentActiveTransaction = {
                    id: transactionId,
                    amount: amount,
                    method: method,
                    status: 'pending',
                    timestamp: new Date().toISOString(),
                    message: 'Menunggu Pembayaran'
                };

                // Tambahkan ke riwayat sebagai pending
                addTransaction(currentActiveTransaction);

                // Update tampilan transaksi saat ini
                updateCurrentTransaction(currentActiveTransaction);

                const methodNames = {
                    'purchase': 'Kartu Debit/Kredit',
                    'brizzi': 'Brizzi',
                    'qris': 'QRIS',
                    'contactless': 'Contactless'
                };

                if (method === 'contactless') {
                    loadingMessage.textContent = 'Memproses Pembayaran Contactless...';
                    loadingSubmessage.textContent = 'Silahkan tap kartu pada EDC';

                    try {
                        const response = await fetch('/ajax/apm/ecrlink/contactless', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                amount: parseInt(amount),
                                transaction_id: transactionId
                            })
                        });

                        const data = await response.json();

                        if (data.status === 'accepted') {
                            // Transaksi dikirim ke EDC, sekarang kita tunggu event
                            console.log('Permintaan contactless dikirim ke EDC', data);
                        } else {
                            throw new Error(data.message ||
                                'Terjadi kesalahan saat memproses pembayaran');
                        }
                    } catch (error) {
                        console.error('Error:', error);

                        // Update transaksi menjadi gagal
                        updateTransaction(transactionId, {
                            status: 'failed',
                            message: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran'
                        });

                        updateCurrentTransaction({
                            ...currentActiveTransaction,
                            status: 'failed',
                            message: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran'
                        });

                        // Sembunyikan loading
                        loadingOverlay.style.display = 'none';

                        // Tampilkan error
                        Swal.fire({
                            title: 'Gagal',
                            text: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran',
                            icon: 'error'
                        });
                    }
                } else {
                    // Proses sale normal (purchase, brizzi, qris)
                    loadingMessage.textContent =
                        `Memproses Pembayaran ${methodNames[method] || method}...`;
                    loadingSubmessage.textContent = 'Silahkan ikuti instruksi di EDC';

                    try {
                        const response = await fetch('/ajax/apm/ecrlink/sale', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                amount: parseInt(amount),
                                trx_id: transactionId,
                                method: method
                            })
                        });

                        const data = await response.json();

                        if (data.status === 'accepted') {
                            // Transaksi dikirim ke EDC, sekarang kita tunggu event
                            console.log('Permintaan pembayaran dikirim ke EDC', data);
                        } else {
                            throw new Error(data.message ||
                                'Terjadi kesalahan saat memproses pembayaran');
                        }
                    } catch (error) {
                        console.error('Error:', error);

                        // Update transaksi menjadi gagal
                        updateTransaction(transactionId, {
                            status: 'failed',
                            message: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran'
                        });

                        updateCurrentTransaction({
                            ...currentActiveTransaction,
                            status: 'failed',
                            message: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran'
                        });

                        // Sembunyikan loading
                        loadingOverlay.style.display = 'none';

                        // Tampilkan error
                        Swal.fire({
                            title: 'Gagal',
                            text: error.message ||
                                'Terjadi kesalahan saat memproses pembayaran',
                            icon: 'error'
                        });
                    }
                }
            });

            // Clear history button handler
            clearHistoryBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Bersihkan Riwayat?',
                    text: 'Apakah Anda yakin ingin menghapus semua riwayat transaksi?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Bersihkan',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        transactions = [];
                        localStorage.setItem('edc-transactions', JSON.stringify(transactions));
                        renderTransactionHistory();
                        Swal.fire('Berhasil', 'Riwayat transaksi telah dibersihkan', 'success');
                    }
                });
            });

            // Generate ID transaksi unik
            function generateTransactionId() {
                return 'TRX' + Date.now() + Math.floor(Math.random() * 1000);
            }

            // Tambahkan transaksi ke riwayat
            function addTransaction(transaction) {
                transactions.unshift(transaction);
                localStorage.setItem('edc-transactions', JSON.stringify(transactions));
                renderTransactionHistory();
            }

            // Update transaksi yang sudah ada
            function updateTransaction(transactionId, updateData) {
                const index = transactions.findIndex(t => t.id === transactionId);
                if (index !== -1) {
                    transactions[index] = {
                        ...transactions[index],
                        ...updateData
                    };
                    localStorage.setItem('edc-transactions', JSON.stringify(transactions));
                    renderTransactionHistory();
                }
            }

            // Update transaksi saat ini
            function updateCurrentTransaction(transaction) {
                const methodLabels = {
                    'purchase': 'Kartu Debit/Kredit',
                    'brizzi': 'Brizzi',
                    'qris': 'QRIS',
                    'contactless': 'Contactless'
                };

                const statusLabels = {
                    'pending': '<span class="status-pill status-pending">Menunggu</span>',
                    'success': '<span class="status-pill status-success">Berhasil</span>',
                    'failed': '<span class="status-pill status-failed">Gagal</span>'
                };

                const formattedAmount = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(transaction.amount);

                const formattedDate = new Date(transaction.timestamp).toLocaleString('id-ID', {
                    dateStyle: 'medium',
                    timeStyle: 'medium'
                });

                currentTransactionStatus.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Transaksi #${transaction.id}</h5>
                            <div class="row mb-2">
                                <div class="col-5">Status:</div>
                                <div class="col-7">${statusLabels[transaction.status]}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5">Jumlah:</div>
                                <div class="col-7">${formattedAmount}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5">Metode:</div>
                                <div class="col-7">${methodLabels[transaction.method] || transaction.method}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5">Waktu:</div>
                                <div class="col-7">${formattedDate}</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-5">Pesan:</div>
                                <div class="col-7">${transaction.message}</div>
                            </div>
                            ${transaction.status === 'success' ? `
                                        <div class="d-grid mt-2">
                                            <button class="btn btn-sm btn-outline-primary show-receipt-btn" data-transaction-id="${transaction.id}">
                                                <i class="fas fa-receipt"></i> Lihat Struk
                                            </button>
                                        </div>
                                        ` : ''}
                        </div>
                    </div>
                `;

                // Add event listener for receipt button if transaction is successful
                if (transaction.status === 'success') {
                    document.querySelector('.show-receipt-btn').addEventListener('click', function() {
                        showReceipt(transaction);
                    });
                }
            }

            // Tampilkan struk pembayaran dalam modal
            function showReceipt(transaction) {
                const response = transaction.responseData || {};

                // Format data untuk struk
                const formattedAmount = new Intl.NumberFormat('id-ID', {
                    style: 'currency',
                    currency: 'IDR'
                }).format(transaction.amount);

                const formattedDate = response.transaction_date ||
                    new Date(transaction.timestamp).toLocaleString('id-ID', {
                        dateStyle: 'medium',
                        timeStyle: 'medium'
                    });

                // Membuat konten struk
                receiptContent.innerHTML = `
                    <div class="text-center mb-3">
                        <h4>STRUK PEMBAYARAN</h4>
                        <p>EDC BRI</p>
                    </div>

                    <div class="line"></div>

                    <div class="row mb-2">
                        <div class="col-5">TID:</div>
                        <div class="col-7">${response.acq_tid || 'N/A'}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5">MID:</div>
                        <div class="col-7">${response.acq_mid || 'N/A'}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5">Tanggal:</div>
                        <div class="col-7">${formattedDate}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5">Trace Number:</div>
                        <div class="col-7">${response.trace_number || 'N/A'}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-5">Ref Number:</div>
                        <div class="col-7">${response.reference_number || 'N/A'}</div>
                    </div>

                    <div class="line"></div>

                    <div class="row mb-2">
                        <div class="col-5">Kartu:</div>
                        <div class="col-7">${response.card_name || response.card_type || transaction.method || 'N/A'}</div>
                    </div>
                    ${response.pan ? `
                                <div class="row mb-2">
                                    <div class="col-5">No. Kartu:</div>
                                    <div class="col-7">${response.pan}</div>
                                </div>
                                ` : ''}
                    ${response.approval ? `
                                <div class="row mb-2">
                                    <div class="col-5">Approval:</div>
                                    <div class="col-7">${response.approval}</div>
                                </div>
                                ` : ''}

                    <div class="line"></div>

                    <div class="row mb-2">
                        <div class="col-5">Jumlah:</div>
                        <div class="col-7">${formattedAmount}</div>
                    </div>

                    <div class="line"></div>

                    <div class="text-center mt-3">
                        <p>TRANSAKSI ${response.status === 'success' || response.status === 'paid' ? 'BERHASIL' : 'GAGAL'}</p>
                        <p>${response.msg || ''}</p>
                        <p class="mt-3">Terima Kasih</p>
                    </div>
                `;

                // Tampilkan modal
                receiptModal.show();
            }

            // Render riwayat transaksi
            function renderTransactionHistory() {
                if (transactions.length === 0) {
                    transactionHistory.innerHTML = `
                        <div class="text-center text-muted">
                            <p>Belum ada riwayat transaksi</p>
                        </div>
                    `;
                    return;
                }

                const methodLabels = {
                    'purchase': 'Kartu Debit/Kredit',
                    'brizzi': 'Brizzi',
                    'qris': 'QRIS',
                    'contactless': 'Contactless'
                };

                const statusLabels = {
                    'pending': '<span class="status-pill status-pending">Menunggu</span>',
                    'success': '<span class="status-pill status-success">Berhasil</span>',
                    'failed': '<span class="status-pill status-failed">Gagal</span>'
                };

                const methodIcons = {
                    'purchase': 'fas fa-credit-card',
                    'brizzi': 'fas fa-wallet',
                    'qris': 'fas fa-qrcode',
                    'contactless': 'fas fa-wifi'
                };

                let html = '';

                transactions.forEach(transaction => {
                    const formattedAmount = new Intl.NumberFormat('id-ID', {
                        style: 'currency',
                        currency: 'IDR'
                    }).format(transaction.amount);

                    const formattedDate = new Date(transaction.timestamp).toLocaleString('id-ID', {
                        dateStyle: 'medium',
                        timeStyle: 'short'
                    });

                    html += `
                        <div class="transaction-item" data-transaction-id="${transaction.id}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="${methodIcons[transaction.method] || 'fas fa-money-bill'}"></i>
                                        ${methodLabels[transaction.method] || transaction.method}
                                    </h6>
                                    <p class="mb-1 text-muted small">${formattedDate}</p>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-1">${formattedAmount}</h6>
                                    <p class="mb-0">${statusLabels[transaction.status]}</p>
                                </div>
                            </div>
                            <div class="mt-2 small">
                                <strong>ID:</strong> ${transaction.id}<br>
                                <strong>Pesan:</strong> ${transaction.message || '-'}
                            </div>
                            ${transaction.status === 'success' ? `
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary show-receipt-btn" data-transaction-id="${transaction.id}">
                                                <i class="fas fa-receipt"></i> Lihat Struk
                                            </button>
                                        </div>
                                        ` : ''}
                        </div>
                    `;
                });

                transactionHistory.innerHTML = html;

                // Add event listeners for receipt buttons
                document.querySelectorAll('.show-receipt-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const transactionId = this.dataset.transactionId;
                        const transaction = transactions.find(t => t.id === transactionId);
                        if (transaction) {
                            showReceipt(transaction);
                        }
                    });
                });
            }

            // Set up Echo listeners untuk event real-time
            setTimeout(() => {
                if (typeof window.Echo !== 'undefined') {
                    console.log('Echo terhubung dengan sukses');

                    // Listen for PaymentCompletedEvent
                    window.Echo.channel('payments')
                        .listen('PaymentCompletedEvent', (e) => {
                            console.log('Payment Completed:', e);

                            if (!currentActiveTransaction || currentActiveTransaction.id !== e
                                .transaction_id) {
                                console.log('Transaksi aktif tidak cocok dengan event yang diterima');
                                return;
                            }

                            // Hide loading overlay
                            loadingOverlay.style.display = 'none';

                            // Update transaction in history
                            updateTransaction(e.transaction_id, {
                                status: 'success',
                                timestamp: e.timestamp,
                                message: e.response?.msg || 'Pembayaran berhasil',
                                responseData: e.response
                            });

                            // Update current transaction display
                            updateCurrentTransaction({
                                ...currentActiveTransaction,
                                status: 'success',
                                timestamp: e.timestamp,
                                message: e.response?.msg || 'Pembayaran berhasil',
                                responseData: e.response
                            });

                            // Clear current active transaction
                            currentActiveTransaction = null;

                            // Show success notification
                            Swal.fire({
                                title: 'Pembayaran Berhasil',
                                text: e.response?.msg || 'Transaksi berhasil diproses',
                                icon: 'success'
                            });
                        });

                    // Listen for PaymentFailedEvent
                    window.Echo.channel('payments')
                        .listen('PaymentFailedEvent', (e) => {
                            console.log('Payment Failed:', e);

                            if (!currentActiveTransaction || currentActiveTransaction.id !== e
                                .transaction_id) {
                                console.log('Transaksi aktif tidak cocok dengan event yang diterima');
                                return;
                            }

                            // Hide loading overlay
                            loadingOverlay.style.display = 'none';

                            // Update transaction in history
                            updateTransaction(e.transaction_id, {
                                status: 'failed',
                                timestamp: e.timestamp,
                                message: e.error || e.response?.msg ||
                                    'Pembayaran gagal',
                                responseData: e.response
                            });

                            // Update current transaction display
                            updateCurrentTransaction({
                                ...currentActiveTransaction,
                                status: 'failed',
                                timestamp: e.timestamp,
                                message: e.error || e.response?.msg ||
                                    'Pembayaran gagal',
                                responseData: e.response
                            });

                            // Clear current active transaction
                            currentActiveTransaction = null;

                            // Show failed notification
                            Swal.fire({
                                title: 'Pembayaran Gagal',
                                text: e.error || e.response?.msg ||
                                    'Terjadi kesalahan saat memproses pembayaran',
                                icon: 'error'
                            });
                        });
                } else {
                    console.error(
                        'Echo tidak terdefinisi - pastikan Laravel Echo diinstal dan dikonfigurasi dengan benar'
                    );
                }
            }, 500);
        });
    </script>
</body>

</html>
