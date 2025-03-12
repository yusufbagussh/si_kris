<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisNotification extends Model
{
    protected $table = 'qris_notifications';

    use HasFactory;

    protected $fillable = [
        'qris_transaction_id',
        'original_reference_no',
        'partner_reference_no',
        'external_id',
        'latest_transaction_status',
        'transaction_status_desc',
        'customer_number',
        'account_type',
        'destination_account_name',
        'amount',
        'currency',
        'bank_code',
        'session_id',
        'external_store_id',
        'reff_id',
        'issuer_name',
        'issuer_rrn',
        'raw_request',
        'raw_header',
        //'additional_info',
    ];
}
