<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisPayment extends Model
{
    protected $table = 'qris_payments';
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_payment_id',
        'registration_no',
        'original_reference_no',
        'partner_reference_no',
        'value',
        'currency',
        'merchant_id',
        'terminal_id',
        'qr_content',
        'status',
        //'status_code',
        //'status_description',
        //'customer_name',
        //'customer_number',
        //'invoice_number',
        //'issuer_name',
        //'issuer_rrn',
        'response_code',
        'response_message',
        'expires_at',
        'paid_at',
        'last_inquiry_at',
        'amount',
        'medical_record_no',
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

    public function patientPayment()
    {
        return $this->belongsTo(PatientPayment::class);
    }

    public function findLastTransactionByRegistationNo($registrationNo)
    {
        return
            $this
                ->where('registration_no', $registrationNo)
                ->latest()
                ->first();
    }

    public function getTransactionWithSuccessStatus($medicalNo)
    {
        return
            $this->select('partner_reference_no', 'original_reference_no', 'response_code', 'response_message', 'qr_content', 'expires_at')
                //->where('partner_reference_no', 'like', $medicalNo . '%')
                ->where('registration_no', $medicalNo)
                ->where('status', 'SUCCESS')
                ->latest()
                ->first();
    }

    public function getTransactionNotExpired($medicalNo)
    {
        return
            $this->select('partner_reference_no', 'original_reference_no', 'response_code', 'response_message', 'qr_content', 'expires_at')
                //->where('partner_reference_no', 'like', $medicalNo . '%')
                ->where('registration_no', $medicalNo)
                ->where('status', 'PENDING')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();
    }

    public function getTransactionByPartnerRefNo($partnerReferenceNo)
    {
        return
            $this->where('partner_reference_no', $partnerReferenceNo)
                ->latest()
                ->first();
    }

    public function getTransactionByRefNo($referenceNo)
    {
        return
            $this->where('original_reference_no', $referenceNo)
                ->latest()
                ->first();
    }
}
