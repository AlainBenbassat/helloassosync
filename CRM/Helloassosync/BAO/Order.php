<?php

class CRM_HelloAssosync_BAO_Order {
  private const CONTRIB_STATUS_PENDING = 2;
  private const CONTRIB_STATUS_PAID = 1;
  private const CONTRIB_STATUS_FAILED = 4;

  public static function createDonation($contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount) {
    if (self::contributionExists($contactId, $paymentId)) {
      return;
    }

    $params = [
      'contact_id' => $contactId,
      'total_amount' => $paymentAmount,
      'financial_type_id' => 'Donation',
      'receive_date' => $paymentDate,
      'source' => self::convertPaymentIdToSource($paymentId),
      'line_items' => [
        'params' => [], // no related entity, just a contribution with a single line item
        'line_item' => [
          'qty' => 1,
          'unit_price' => $paymentAmount,
          'line_total' => $paymentAmount,
          'price_field_id' => 1,
        ],
      ],
    ];

    $order = self::createOrder($params);
    self::processPayment($order, $paymentStatus);
  }

  public static function createMembership() {
    $params = [

    ];

    return self::createOrder($params);
  }

  private static function createOrder($params) {
    // not yet available in APIv4, use api3
    return civicrm_api3('Order', 'create', $params);
  }

  private static function processPayment($order, $paymentStatus) {
    $contributionStatus = self::convertHelloAssoPaymentStatus($paymentStatus);
    if ($contributionStatus == 0) {
      return; // unkown status
    }

    if ($contributionStatus == self::CONTRIB_STATUS_PAID) {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $order['id'],
        'total_amount' => $order['total_amount'],
      ]);
    }
    else {
      civicrm_api3('Contribution', 'create', [
        'id' => $order['id'],
        'status_id' => $contributionStatus,
      ]);
    }
  }

  private static function convertHelloAssoPaymentStatus($paymentStatus) {
    switch ($paymentStatus) {
      case 'Authorized':
        return self::CONTRIB_STATUS_PAID; // Terminé
      case 'Refused':
        return self::CONTRIB_STATUS_FAILED; // échoué
      case 'Pending':
        return self::CONTRIB_STATUS_PENDING; // En instance
    }

    return 0;
  }

  private static function contributionExists($contactId, $paymentId) {
    $contrib = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('source', '=', self::convertPaymentIdToSource($paymentId))
      ->execute()
      ->first();

    return $contrib ? TRUE : FALSE;
  }

  private static function convertPaymentIdToSource($paymentId) {
    return "HA_$paymentId";
  }

}
