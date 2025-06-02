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

    public function findPatientPayment($registrationNo, $billingList)
    {
        return
            $this->with(['patientPayment', 'patientPayment.patientPaymentDetail'])
            ->whereHas('patientPayment', function ($query) use ($registrationNo) {
                $query->where('registration_no', $registrationNo);
            })
            ->whereHas('patientPayment.patientPaymentDetail', function ($query) use ($billingList) {
                $listBillingNo = collect($billingList)->pluck('billing_no')->toArray();
                $query->whereIn('billing_no', $listBillingNo);
            })
            ->latest()
            ->first();
    }
}
