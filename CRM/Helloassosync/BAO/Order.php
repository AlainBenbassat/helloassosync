<?php

class CRM_HelloAssosync_BAO_Order {
  private const CONTRIB_STATUS_PENDING = 2;
  private const CONTRIB_STATUS_PAID = 1;
  private const CONTRIB_STATUS_CANCELED = 3;
  private const PAYED_WITH_CARD = 2;
  private const PAYED_WITH_SEPA = 11;
  private const FIN_TYPE_DON = 12;
  private const FIN_TYPE_COTISATION = 13;
  private const MEMBERSHIP_TYPE_NOVEMBER_ONE_YEAR_ID = 4;
  private const MEMBERSHIP_STATUS_PENDING = 5;
  private const PRICE_FIELD_ID = 65; // Montant libre de cotisation (15 euros minimum)
  private const PRICE_FIELD_VALUE_ID = 118; // Montant libre de cotisation (15 euros minimum)

  public static function createDonation($mainContactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount, $paymentMethod, $installmentNumber, $donationFrequency, $financialTypeId, $campaignId): int {
    // check if the contribution already exists
    $contributionId = self::contributionExists($mainContactId, $paymentId);
    if ($contributionId > 0) {
      return $contributionId;
    }

    if (strtolower($paymentMethod) == 'sepa') {
      $paymentInstrumentId = self::PAYED_WITH_SEPA;
    }
    else {
      $paymentInstrumentId = self::PAYED_WITH_CARD;
    }

    $params = [
      'contact_id' => $mainContactId,
      'total_amount' => $paymentAmount,
      'financial_type_id' => $financialTypeId,
      'payment_instrument_id' => $paymentInstrumentId,
      'campaign_id' => $campaignId,
      'receive_date' => $paymentDate,
      'source' => self::convertPaymentIdToSource($paymentId),
      'line_items' => [
        [
          'params' => [],
          'line_item' => [
            [
              'qty' => 1,
              'unit_price' => $paymentAmount,
              'line_total' => $paymentAmount,
              'price_field_id' => 1,
            ],
          ],
        ],
      ],
    ];

    $order = self::createOrder($params);
    self::setDonationFrequence($order['id'], $donationFrequency);
    self::processPayment($order, $paymentDate, $paymentStatus);

    // create an activity for the first monthly donation
    if ($donationFrequency != 1 && $installmentNumber == 1) {
      CRM_Helloassosync_BAO_Contact::createActivityFirstRecurringDonation($mainContactId, $financialTypeId, $paymentDate);
    }

    return $order['id'];
  }

  public static function createOrUpdateMembership(string $formSlug, string $paymentDate, int $contactId) {
    $year = substr($formSlug, -4);

    $membership = CRM_Helloassosync_BAO_Contact::getCurrentMembership($contactId, $year);
    if (empty($membership)) {
      self::createMembership($contactId, $paymentDate, $year);
    }
    else {
      self::updateMembership($membership, $year);
    }
  }

  private static function createMembership(int $contactId, string $joinDate, int $year) {
    \Civi\Api4\Membership::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('join_date', $joinDate)
      /*->addValue('start_date', "$year-01-01")
      ->addValue('end_date', "$year-12-31")*/
      ->addValue('membership_type_id', self::MEMBERSHIP_TYPE_NOVEMBER_ONE_YEAR_ID)
      ->execute();
  }

  private static function updateMembership($membership, int $year) {
    $newEndDate = $year . substr($membership['end_date'], 4);
    $sql = "update civicrm_membership set status_id = 2, end_date = '" . $newEndDate . "' where id = " . $membership['id'];
    CRM_Core_DAO::executeQuery($sql);
  }

  private static function createOrder($params) {
    // not yet available in APIv4, use api3
    $results = civicrm_api3('Order', 'create', $params);
    return reset($results['values']);
  }

  private static function processPayment($order, $paymentDate, $paymentStatus) {
    $contributionStatus = self::convertHelloAssoPaymentStatus($paymentStatus);
    if ($contributionStatus == 0) {
      return; // unkown status
    }

    if ($contributionStatus == self::CONTRIB_STATUS_PAID) {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $order['id'],
        'total_amount' => $order['total_amount'],
        'trxn_date' => $paymentDate,
        'is_send_contribution_notification' => 0,
      ]);
    }
    else {
      civicrm_api3('Contribution', 'create', [
        'id' => $order['id'],
        'contribution_status_id' => $contributionStatus,
      ]);
    }
  }

  public static function createSoftContribution($contributionId, $contactId, $paymentAmount, $softCreditType) {
    \Civi\Api4\ContributionSoft::create(FALSE)
      ->addValue('contribution_id', $contributionId)
      ->addValue('contact_id', $contactId)
      ->addValue('soft_credit_type_id', $softCreditType)
      ->addValue('amount', $paymentAmount)
      ->execute();
  }

  private static function convertHelloAssoPaymentStatus($paymentStatus) {
    switch ($paymentStatus) {
      case 'Authorized':
        return self::CONTRIB_STATUS_PAID; // Terminé
      case 'Refused':
        return self::CONTRIB_STATUS_CANCELED; // échoué
      case 'Pending':
        return self::CONTRIB_STATUS_PENDING; // En instance
    }

    return 0;
  }

  public static function contributionExists($contactId, $paymentId): int {
    $contrib = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('source', '=', self::convertPaymentIdToSource($paymentId))
      ->execute()
      ->first();

    return $contrib ? $contrib['id'] : 0;
  }

  private static function convertPaymentIdToSource($paymentId) {
    return "HelloAsso $paymentId";
  }

  private static function setDonationFrequence($orderId, $donationFrequence) {
    \Civi\Api4\Contribution::update(FALSE)
      ->addValue('Frequence.Fr_quence_Don', $donationFrequence)
      ->addWhere('id', '=', $orderId)
      ->execute();
  }
}
