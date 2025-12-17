<?php

class CRM_Helloassosync_BAO_HelloAsso {
  private const DONATION_FREQUENCY_ONETIME = 1;
  private const DONATION_FREQUENCY_MONTHLY = 2;

  public function __construct() {
    require_once __DIR__ . '/../../../vendor/autoload.php';
  }

  public function getOrganizationInfo() {
    $orgApi = new \OpenAPI\Client\Api\OrganisationApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
    $result = $orgApi->organizationsOrganizationSlugGet(
      CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->organizationSlug
    );

    return [
      'name' => $result->getName(),
      'description' => $result->getDescription(),
      'address' => $result->getAddress(),
    ];
  }

  public function getFormList() {
    $formList = [];

    $formsApi = new \OpenAPI\Client\Api\FormulairesApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);

    $result = $formsApi->organizationsOrganizationSlugFormsGet(
      CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->organizationSlug,
      'Public',
      null,
    );

    $arrayOfForms = $result->getData();
    foreach ($arrayOfForms as $f) {
      $formList[] = [
        'slug' => $f->getFormSlug(),
        'title' => $f->getTitle(),
        'type' => $f->getFormType(),
        'status' => $f->getState(),
        'url' => $f->getUrl(),
      ];
    }

    return $formList;
  }

  public function syncFormPayments(string $formSlug, string $formType, ?int $campaignId, string $dateFrom, string $dateTo): int {
    $totalProcessed = 0;
    $continuationToken = null;
    $pageIndex = 1;
    $hasMoreData = TRUE;

    while ($hasMoreData) {
      $payments = $this->getPayments($formSlug, $formType, $dateFrom, $dateTo,'Asc', $continuationToken, $pageIndex, $hasMoreData);
      $totalProcessed += $this->processPayments($formSlug, $payments, $formType, $campaignId);
      $pageIndex++;
    }

    return $totalProcessed;
  }

  public function getPayments(string $formSlug, string $formType, string $dateFrom, string $dateTo, string $sortOrder, ?string &$continuationToken, int $pageIndex, bool &$hasMoreData) {
    $pageSize = 20;
    $paymentList = [];

    $paymentsApi = new \OpenAPI\Client\Api\PaiementsApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
    $result = $paymentsApi->organizationsOrganizationSlugFormsFormTypeFormSlugPaymentsGet(
      CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->organizationSlug,
      $formSlug,
      $formType,
      "{$dateFrom}T00:00:00.000Z",
      "{$dateTo}T23:59:59.999Z",
      null,
      $pageIndex,
      $pageSize,
      null, // used to be $continuationToken, but is seems to be broken
      null,
      $sortOrder,
      'Date',
      TRUE
    );

    // continuation token is broken as of December 2025
    $continuationToken = $result->getPagination()->getContinuationToken();

    $arrayOfPayments = $result->getData();

    // instead of continuation token, we assume there is more if the count equals the page size
    $hasMoreData = (count($arrayOfPayments) == $pageSize);

    foreach ($arrayOfPayments as $p) {
      // initially, we synced all. As of 20 November 2025 we sync only authorized payments
      if ($p->getState() == 'Authorized') {
        $paymentList[] = $this->transformPaymentToArray($p);
      }
    }

    return $paymentList;
  }

  public function processMailingSubscriptions($orderId, $contactId) {
    // mailing preferences are stored in custom fields of the order
    $orderApi = new \OpenAPI\Client\Api\CommandesApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
    $order = $orderApi->ordersOrderIdGet($orderId);
    $items = $order->getItems();
    foreach ($items as $item) {
      $customFields = $item->getCustomFields();
      foreach ($customFields as $customField) {
        $customFieldName = $customField->getName();
        $customFieldAnswer = $customField->getAnswer();
        CRM_Helloassosync_BAO_Contact::updateCommunicationPreferences($contactId, $customFieldName, $customFieldAnswer);
      }
    }
  }

  private function processPayments($formSlug, $payments, $formType, $campaignId) {
    $totalProcessed = 0;
    $donationFrequency = self::DONATION_FREQUENCY_ONETIME;

    foreach ($payments as $payment) {
      \Civi::log()->debug('Processing payment', [
        'name' => $payment['first_name'] . ' ' . $payment['last_name'],
        'company' => $payment['company'],
        'email' => $payment['email'],
        'payment date' => $payment['date'],
        'amount' => $payment['amount']
      ]);

      [$orgId, $personId, $status] = CRM_Helloassosync_BAO_Contact::findOrCreate($payment['company'], $payment['first_name'], $payment['last_name'], $payment['email']);
      CRM_Helloassosync_BAO_Contact::createOrUpdateAddress($orgId ?? $personId, $payment['address'], $payment['city'], $payment['postal_code'], $payment['country']);

      if ($formType == 'Membership') {
        CRM_Helloassosync_BAO_Order::createOrUpdateMembership($formSlug, $personId, $payment['id'], $payment['date'], $payment['status'], $payment['amount']);
      }
      else {
        $donationFrequency = $this->extractFrequence($payment);
        CRM_Helloassosync_BAO_Order::createDonation($orgId, $personId, $payment['id'], $payment['date'], $payment['status'], $payment['amount'], $donationFrequency, $campaignId);
      }

      // update mailing preferences for new contacts, one-time donations, or for the first monthly donation
      if ($status == 'new contact' || $donationFrequency == self::DONATION_FREQUENCY_ONETIME || $payment['installment_number'] == 1) {
        $this->processMailingSubscriptions($payment['order_id'], $personId);
      }

      // create an activity for the first monthly donation
      if ($donationFrequency == self::DONATION_FREQUENCY_MONTHLY && $payment['installment_number'] == 1) {
        CRM_Helloassosync_BAO_Contact::createActivityFirstRecurringDonation($orgId ?? $personId);
      }

      $totalProcessed++;
    }

    return $totalProcessed;
  }

  private function extractFrequence($payment): int {
    // Ponctuel = 1, Mensuel = 2
    if (!empty($payment['type'])) {
      return ($payment['type'] == 'MonthlyDonation') ? self::DONATION_FREQUENCY_MONTHLY : self::DONATION_FREQUENCY_ONETIME;
    }

    return self::DONATION_FREQUENCY_ONETIME;
  }

  private function transformPaymentToArray($p) {
    $payer = $p->getPayer();
    return [
      'id' => $p->getId(),
      'date' => $p->getDate()->format('Y-m-d H:i:s'),
      'amount' => $p->getAmount() / 100,
      'status' => $p->getState(),
      'first_name' => $payer->getFirstName(),
      'last_name' => $payer->getLastName(),
      'email' => $payer->getEmail(),
      'address' => $payer->getAddress(),
      'city' => $payer->getCity(),
      'postal_code' => $payer->getZipCode(),
      'country' => $payer->getCountry(),
      'company' => $payer->getCompany(),
      'type' => $p->getItems()[0]->getType() ?? 0,
      'order_id' => $p->getOrder()->getId(),
      'installment_number' => $p->getInstallmentNumber(),
    ];
  }

}
