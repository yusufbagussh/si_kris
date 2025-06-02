<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientPayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function patientPaymentDetail()
    {
        return $this->hasMany(PatientPaymentDetail::class, 'patient_payment_id', 'id');
    }

    public function lastQrisPayment()
    {
        return $this->hasOne(QrisPayment::class, 'patient_payment_id', 'id')
            ->latest('created_at');
    }
}
