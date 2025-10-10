<?php

class CRM_HelloAssosync_BAO_Contribution {
  public static function create($contactId, $formType, $paymentId, $paymentDate, $paymentStatus, $amount) {
    $contributionStatusId = self::convertPaymentStatusToId($paymentStatus);
    if ($contributionStatusId == 0) {
      return null;
    }

    $financialTypeId = $formType == 'Membership' ? 13 : 12; // cotisation ou don

    return \Civi\Api4\Contribution::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('total_amount', $amount)
      ->addValue('receive_date', $paymentDate)
      ->addValue('contribution_status_id', $contributionStatusId)
      ->addValue('source', "HA_$paymentId")
      ->execute()
      ->first();
  }

  private static function convertPaymentStatusToId($paymentStatus) {
    switch ($paymentStatus) {
      case 'Authorized':
        return 1; // Terminé
      case 'Refused':
        return 4; // échoué
      case 'Pending':
        return 2; // En instance
    }

    // unknown status
    return 0;
  }
}
