<?php

class CRM_HelloAssosync_BAO_Order {
  private const CONTRIB_STATUS_PENDING = 2;
  private const CONTRIB_STATUS_PAID = 1;
  private const CONTRIB_STATUS_CANCELED = 3;
  private const FIN_TYPE_DON = 12;
  private const FIN_TYPE_COTISATION = 13;
  private const MEMBERSHIP_TYPE_NOVEMBER_ONE_YEAR_ID = 4;
  private const MEMBERSHIP_STATUS_PENDING = 5;
  private const PRICE_FIELD_ID = 65; // Montant libre de cotisation (15 euros minimum)
  private const PRICE_FIELD_VALUE_ID = 118; // Montant libre de cotisation (15 euros minimum)

  public static function createDonation($contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount, $donationFrequence) {
    if (self::contributionExists($contactId, $paymentId)) {
      return;
    }

    $params = [
      'contact_id' => $contactId,
      'total_amount' => $paymentAmount,
      'financial_type_id' => self::FIN_TYPE_DON,
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
    self::setDonationFrequence($order['id'], $donationFrequence);
    self::processPayment($order, $paymentStatus);
  }

  public static function createOrUpdateMembership($formSlug, $contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount) {
    if (self::contributionExists($contactId, $paymentId)) {
      return;
    }

    $membership = CRM_Helloassosync_BAO_Contact::getCurrentMembership($contactId);
    if (empty($membership)) {
      self::createMembership($formSlug, $contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount);
    }
    else {
      self::updateMembership($membership, $formSlug, $contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount);
    }
  }

  private static function createMembership($formSlug, $contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount) {
    $params = [
      'contact_id' => $contactId,
      'total_amount' => $paymentAmount,
      'financial_type_id' => self::FIN_TYPE_COTISATION,
      'receive_date' => $paymentDate,
      'source' => self::convertPaymentIdToSource($paymentId),
      'line_items' => [
        [
          'params' => [
            'membership_type_id' => self::MEMBERSHIP_TYPE_NOVEMBER_ONE_YEAR_ID,
            'contact_id' => $contactId,
            'skipStatusCal' => 1,
            'status_id' => self::MEMBERSHIP_STATUS_PENDING,
          ],
          'line_item' => [
            [
              'entity_table' => 'civicrm_membership',
              'price_field_id' => self::PRICE_FIELD_ID,
              'price_field_value_id' => self::PRICE_FIELD_VALUE_ID,
              'qty' => 1,
              'unit_price' => $paymentAmount,
              'line_total' => $paymentAmount,
            ],
          ],
        ],
      ],
    ];

    $order = self::createOrder($params);
    self::processPayment($order, $paymentStatus);
  }

  private static function updateMembership($membership, $formSlug, $contactId, $paymentId, $paymentDate, $paymentStatus, $paymentAmount) {
    $params = [
      'contact_id' => $contactId,
      'total_amount' => $paymentAmount,
      'financial_type_id' => self::FIN_TYPE_COTISATION,
      'receive_date' => $paymentDate,
      'source' => self::convertPaymentIdToSource($paymentId),
      'line_items' => [
        [
          'params' => [
            'membership_type_id' => self::MEMBERSHIP_TYPE_NOVEMBER_ONE_YEAR_ID,
            'id' => $membership['id'],
            'contact_id' => $contactId,
            'skipStatusCal' => 1,
            'status_id' => self::MEMBERSHIP_STATUS_PENDING,
          ],
          'line_item' => [
            [
              'entity_table' => 'civicrm_membership',
              'price_field_id' => self::PRICE_FIELD_ID,
              'price_field_value_id' => self::PRICE_FIELD_VALUE_ID,
              'qty' => 1,
              'unit_price' => $paymentAmount,
              'line_total' => $paymentAmount,
            ],
          ],
        ],
      ],
    ];

    $order = self::createOrder($params);
    self::processPayment($order, $paymentStatus);
    //self::correctStartDate($membership['id']);
  }

  private static function createOrder($params) {
    // not yet available in APIv4, use api3
    $results = civicrm_api3('Order', 'create', $params);
    return reset($results['values']);
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
        'contribution_status_id' => $contributionStatus,
      ]);
    }
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
    return "HelloAsso $paymentId";
  }

  private static function setDonationFrequence($orderId, $donationFrequence) {
    \Civi\Api4\Contribution::update(FALSE)
      ->addValue('Frequence.Fr_quence_Don', $donationFrequence)
      ->addWhere('id', '=', $orderId)
      ->execute();
  }
}
