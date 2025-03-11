<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisInquiry extends Model
{
    protected $table = 'qris_inquiries';

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'qris_transaction_id',
        'reference_no',
        'terminal_id',
        'response_code',
        'response_message',
        'transaction_status',
        'transaction_status_code',
        'transaction_status_desc',
        'raw_response',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'raw_response' => 'json',
    ];

    /**
     * Get the transaction that owns the inquiry.
     */
    public function transaction()
    {
        return $this->belongsTo(QrisTransaction::class, 'qris_transaction_id');
    }
}
