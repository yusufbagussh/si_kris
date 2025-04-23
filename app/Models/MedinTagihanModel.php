<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MedinTagihanModel extends Model
{
    use HasFactory;

    protected $connection = 'medinfras_dev';

    public static function getListPatient($visitDate, $serviceUnitId, $paramedicID)
    {
        $binding = ['visitDate' => $visitDate, 'serviceUnitId' => $serviceUnitId];

        $query = "
		SELECT
				r.RegistrationID, r.RegistrationNo,
				pt.MedicalNo, pt.FullName AS PatientName, pt.DateOfBirth,
				pm.FullName AS DoctorName, cv.Session, cv.QueueNo,
				scG.StandardCodeName AS Gender, scCT.StandardCodeName AS CustomerType
            FROM
				ConsultVisit cv
            JOIN Registration r ON r.RegistrationID = cv.RegistrationID
            JOIN ParamedicMaster pm ON pm.ParamedicID = cv.ParamedicID
            JOIN Patient pt ON pt.MRN = r.MRN
            JOIN Address ads ON ads.AddressID = pt.HomeAddressID
            JOIN StandardCode scG ON scG.StandardCodeID = pt.GCGender
            JOIN StandardCode scCT ON scCT.StandardCodeID = r.GCCustomerType
            JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = cv.HealthcareServiceUnitID
            WHERE
				cv.VisitDate = :visitDate
				AND hsu.ServiceUnitID = :serviceUnitId
				AND r.TransactionCode = 1202
				AND GCCustomerType = 'X004^999'
				";

        if ($paramedicID != 'all') {
            $query .= "
				AND cv.ParamedicID = :paramedicID
				";

            $binding = ['visitDate' => $visitDate, 'serviceUnitId' => $serviceUnitId, 'paramedicID' => $paramedicID];
        }

        $query .= "
			ORDER BY
				cv.VisitDate DESC
				";

        // OFFSET 0 ROWS
        // FETCH NEXT 1000 ROWS ONLY

        $instance = new static();
        $result = DB::connection($instance->connection)->select($query, $binding);

        return $result;
    }

    public static function getPatientByRegistrationID($RegistrationID)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
            SELECT
				r.*, pt.*, ads.*, su.*, pm.FullName AS Doctor, scG.StandardCodeName AS Gender, scCT.StandardCodeName AS CustomerType, cv.*
            FROM
				ConsultVisit cv
            JOIN Registration r ON r.RegistrationID = cv.RegistrationID
            JOIN ParamedicMaster pm ON pm.ParamedicID = cv.ParamedicID
            JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = cv.HealthcareServiceUnitID
            JOIN ServiceUnitMaster su ON su.ServiceUnitID = hsu.ServiceUnitID
            JOIN Patient pt ON pt.MRN = r.MRN
            JOIN Address ads ON ads.AddressID = pt.HomeAddressID
            JOIN StandardCode scG ON scG.StandardCodeID = pt.GCGender
            JOIN StandardCode scCT ON scCT.StandardCodeID = r.GCCustomerType
            WHERE
				cv.RegistrationID = :RegistrationID
				AND r.TransactionCode = 1202
            ORDER BY
				cv.VisitDate DESC
		",
            ['RegistrationID' => $RegistrationID]
        );

        // OFFSET 0 ROWS
        //FETCH NEXT 100 ROWS ONLY
        return $result;
    }

    public static function getTagihanByRegistrationID($RegistrationID)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
            SELECT
				r.*, pc.*, cv.*, su.*, scs.StandardCodeName AS Status, scs.StandardCodeID AS StatusID
            FROM
				ConsultVisit cv
            JOIN
				Registration r ON r.RegistrationID = cv.RegistrationID
            RIGHT JOIN PatientChargesHd pc ON pc.VisitID = cv.VisitID
            JOIN StandardCode scs ON scs.StandardCodeID = pc.GCTransactionStatus
            JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = pc.HealthcareServiceUnitID
            JOIN ServiceUnitMaster su ON su.ServiceUnitID = hsu.ServiceUnitID
            WHERE
				cv.RegistrationID = :RegistrationID
				AND r.TransactionCode = 1202
            ORDER BY
				pc.TransactionNo ASC;
        ",
            ['RegistrationID' => $RegistrationID]
        );

        //AND scs.StandardCodeID <> 'X121^005'
        //OFFSET 0 ROWS
        // FETCH NEXT 10 ROWS ONLY
        return $result;
    }

    public static function getPatientBillingNew($registrationId)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				pb.PatientBillingID, pb.PatientBillingNo, pb.BillingDate, pb.BillingTime, pb.TotalPatientAmount, pb.TotalAmount,
				scs.StandardCodeName, scs.StandardCodeID,
				'' AS TransactionNo
			FROM
				PatientBill pb
			LEFT JOIN StandardCode scs ON scs.StandardCodeID = pb.GCTransactionStatus
			WHERE
				pb.RegistrationID = :registrationId
			ORDER BY
				pb.PatientBillingID ASC;
        ",
            ['registrationId' => $registrationId]
        );

        return $result;
    }

    public static function getPatientBillTransaction($patientBillingId)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				pch.TransactionID, pch.VisitID, pch.TransactionNo, pch.TransactionDate, pch.TransactionTime, pch.TotalPatientAmount, pch.TotalAmount,
				su.ServiceUnitName,
				scs.StandardCodeName, scs.StandardCodeID
			FROM
				PatientChargesHd pch
			LEFT JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = pch.HealthcareServiceUnitID
			LEFT JOIN ServiceUnitMaster su ON su.ServiceUnitID = hsu.ServiceUnitID
			LEFT JOIN StandardCode scs ON scs.StandardCodeID = pch.GCTransactionStatus
			WHERE
				pch.PatientBillingID = :patientBillingId
			ORDER BY
				pch.TransactionID
        ",
            ['patientBillingId' => $patientBillingId]
        );

        return $result;
    }

    public static function getPatientBilling($registrationID)
    {
        $instance = new static();
        $query = DB::connection($instance->connection)->select(
            "
			SELECT DISTINCT
				pb.PatientBillingNo, pb.BillingDate, pb.BillingTime, pb.TotalPatientAmount,
				pb.TotalPayerAmount, pb.TotalAmount, pb.CreatedDate, pb.LastUpdatedDate,
				su.DepartmentID, su.ServiceUnitName,
				scs.StandardCodeName, scs.StandardCodeID,
				pm.FullName
			FROM
				PatientBill pb
			LEFT JOIN ConsultVisit cv ON cv.RegistrationID = pb.RegistrationID
			LEFT JOIN StandardCode scs ON scs.StandardCodeID = pb.GCTransactionStatus
			LEFT JOIN Registration r ON r.RegistrationID = cv.RegistrationID
			LEFT JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = cv.HealthcareServiceUnitID
			LEFT JOIN ServiceUnitMaster su ON su.ServiceUnitID = hsu.ServiceUnitID
			LEFT JOIN ParamedicMaster pm ON pm.ParamedicID = cv.ParamedicID
            WHERE
				cv.RegistrationID = :RegistrationID
            ORDER BY
				pb.BillingDate ASC;
        ",
            ['RegistrationID' => $registrationID]
        );

        return $query;
    }

    public static function getStandardCodePaymentInfo()
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				StandardCodeID, StandardCodeName, ParentID, IsActive
			FROM
				StandardCode
			WHERE
				StandardCodeID LIKE 'X168^%'
				OR StandardCodeID = 'X035^001'
				OR StandardCodeID = 'X035^002'
				OR StandardCodeID = 'X035^003'
				OR StandardCodeID = 'X035^004'
				OR StandardCodeID = 'X035^020'
				OR StandardCodeID = 'X035^021'
				OR StandardCodeID = 'X169^012'
				AND isActive = 'True';
        "
        );

        return $result;
    }

    public static function getEDCMachineByBankID($bankCode)
    {
        $instance = new static();
        $query = DB::connection($instance->connection)->select(
            "
                    SELECT em.EDCMachineName, em.EDCMachineCode
                    FROM EDCMachine em
                             JOIN Bank b ON em.BankID = b.BankID
                    WHERE em.IsDeleted = 0
                      AND b.BankCode = :BankCode
                ",
            ['BankCode' => $bankCode]
        );
        return $query;
    }

    public static function getCardTypeByEDCMachineID($edcMachineCode)
    {
        $instance = new static();
        $query = DB::connection($instance->connection)->select(
            "
                    SELECT cc.GCCardType, sc1.StandardCodeName as CardType, cc.GCCardProvider, sc2.StandardCodeName as CardProvider
                    FROM EDCMachine em
                             JOIN CreditCard cc ON em.EDCMachineID = cc.EDCMachineID
                             JOIN StandardCode sc1 ON cc.GCCardType = sc1.StandardCodeID
                             JOIN StandardCode sc2 ON cc.GCCardProvider = sc2.StandardCodeID
                    WHERE em.IsDeleted = 0
                      AND em.EDCMachineCode = :EDCMachineCode
                ",
            ['EDCMachineCode' => $edcMachineCode]
        );
        return $query;
    }

    public static function getBankInfo()
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				BankCode, BankName
			FROM
				Bank
			WHERE
				IsDeleted = 'False'
			ORDER BY
				BankName ASC;
        "
        );

        return $result;
    }

    public static function getPatientRegistrationLock($registrationId)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				IsLockDown
			FROM
				Registration
			WHERE
				RegistrationID = :registrationId;
        ",
            ['registrationId' => $registrationId]
        );

        return $result;
    }

    public static function getOutpatientClinic()
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				ServiceUnitID, ServiceUnitCode, ServiceUnitName
			FROM
				ServiceUnitMaster
			WHERE
				DepartmentID = 'OUTPATIENT'
				AND IsDeleted <> 'True'
			ORDER BY
				ServiceUnitName;
        "
        );

        return $result;
    }

    public static function getOutpatientParamedic()
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
			SELECT
				scG.StandardCodeName, pm.ParamedicID, pm.ParamedicCode, pm.fullName, sm.ServiceUnitID, sm.ServiceUnitCode, sm.ServiceUnitName
			FROM
				ParamedicMaster pm
			JOIN StandardCode scG ON scG.StandardCodeID = pm.GCParamedicMasterType
			JOIN ServiceUnitParamedic sup ON sup.ParamedicID = pm.ParamedicID
			JOIN HealthcareServiceUnit hsu ON hsu.HealthcareServiceUnitID = sup.HealthcareServiceUnitID
			JOIN ServiceUnitMaster sm ON sm.ServiceUnitID = hsu.ServiceUnitID
			WHERE
				pm.GCParamedicMasterType = 'X019^001'
				AND pm.IsAvailable <> 'FALSE'
				AND pm.IsDeleted <> 'TRUE'
				AND sm.DepartmentID = 'OUTPATIENT'
				AND sm.IsDeleted <> 'TRUE'
			ORDER BY
				pm.fullName;
		"
        );

        return $result;
    }

    // Cetakan
    public static function getTransactionPayment($RegistrationID)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
		SELECT pt.fullName, pph.paymentID, u.UserName, scS.StandardCodeName AS TransactionStatus, scT.StandardCodeName as PaymentType, pph.paymentNo, pph.GCTransactionStatus,
			pph.RegistrationID, pph.TotalPaymentAmount, pph.TotalPatientBillAmount, pph.TotalPayerBillAmount, pph.PaymentDate, pph.PaymentTime
		FROM PatientPaymentHd pph
			JOIN StandardCode scS ON scS.StandardCodeID = pph.GCTransactionStatus
			JOIN StandardCode scT ON scT.StandardCodeID = pph.GCPaymentType
			JOIN Registration r ON r.RegistrationID = pph.RegistrationID
			JOIN Patient pt ON r.MRN = pt.MRN
			JOIN [User] u ON u.UserID = pph.CreatedBy
		WHERE pph.RegistrationID = :RegistrationID
			AND pph.GCTransactionStatus <> 'X121^999'
			AND pph.PaymentReceiptID IS NULL
			ORDER BY pph.CreatedDate
		",
            ['RegistrationID' => $RegistrationID]
        );

        return $result;
    }

    public static function getListReceipt($RegistrationID)
    {
        $instance = new static();
        $result = DB::connection($instance->connection)->select(
            "
		SELECT
			pr.PaymentReceiptID, pr.PaymentReceiptNo, pr.ReceiptAmount, PR.PaymentReceiptDateTime, pr.LastPrintedDate, pr.PrintAsName, pr.PrintNumber,
			u.UserName, us.UserName AS LastUpdatedName, pr.LastUpdatedDate, scV.StandardCodeName AS sVoidReason, scR.StandardCodeName AS sReprintReason,
			pr.ReprintReason, pr.VoidReason, pr.IsDeleted, pr.RegistrationID
		FROM
			PaymentReceipt pr
			LEFT JOIN StandardCode scR ON scR.StandardCodeID = pr.GCReprintReason
			LEFT JOIN StandardCode scV ON scV.StandardCodeID = pr.GCVoidReason
			JOIN [User] u ON u.UserID = pr.CreatedBy
			LEFT JOIN [User] us ON us.UserID = pr.LastUpdatedBy
		WHERE
			pr.RegistrationID = :RegistrationID
		",
            ['RegistrationID' => $RegistrationID]
        );

        return $result;
    }
}
