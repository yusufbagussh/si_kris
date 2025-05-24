<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EdcPayment extends Model
{
    use HasFactory;
    protected $table = 'edc_payments';
    protected $guarded = ['id'];

    public function patientPayment()
    {
        return $this->belongsTo(PatientPayment::class, 'patient_payment_id', 'id');
    }
}
