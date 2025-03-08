<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisTransaction extends Model
{
    protected $table = 'bri.qris_transactions';
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'qris_transaction_id',
        'reference_no',
        'partner_reference_no',
        'amount',
        'currency',
        'merchant_id',
        'terminal_id',
        'qr_content',
        'status',
        'status_code',
        'status_description',
        'customer_name',
        'customer_number',
        'invoice_number',
        'issuer_name',
        'issuer_rrn',
        'response_code',
        'response_message',
        'paid_at',
        'last_inquiry_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'last_inquiry_at' => 'datetime',
    ];

    /**
     * Get the inquiries for the transaction.
     */
    public function inquiries()
    {
        return $this->hasMany(QrisInquiry::class);
    }

    /**
     * Get the payment notification for the transaction.
     */
    public function payment()
    {
        return $this->hasOne(QrisNotification::class, 'original_reference_no', 'reference_no');
    }
}
