<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisNotification extends Model
{
    protected $table = 'bri.qris_notifications';

    use HasFactory;

    protected $fillable = [
        'reference_no',
        'partner_reference_no',
        'transaction_status',
        'transaction_status_desc',
        'customer_number',
        'account_type',
        'destination_account_name',
        'amount',
        'currency',
        'bank_code',
        //'additional_info',
        'raw_data',
        'raw_header',
        'external_id',
    ];
}
