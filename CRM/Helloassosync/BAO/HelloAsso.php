<?php

class CRM_Helloassosync_BAO_HelloAsso {
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

  public function syncFormPayments(string $formSlug, string $formType, string $dateFrom, string $dateTo): int {
    $totalProcessed = 0;
    $continuationToken = null;

    do {
      $payments = $this->getPayments($formSlug, $formType, $dateFrom, $dateTo,'Asc', $continuationToken);
      $totalProcessed += $this->processPayments($formSlug, $payments, $formType);
    }
    while (count($payments) > 0);

    return $totalProcessed;
  }

  public function getPayments(string $formSlug, string $formType, string $dateFrom, string $dateTo, string $sortOrder, ?string &$continuationToken) {
    $paymentList = [];

    $paymentsApi = new \OpenAPI\Client\Api\PaiementsApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);

    $result = $paymentsApi->organizationsOrganizationSlugFormsFormTypeFormSlugPaymentsGet(
      CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->organizationSlug,
      $formSlug,
      $formType,
      "{$dateFrom} 00:00:00",
      "{$dateTo} 23:59:59",
      null,
      null,
      null,
      $continuationToken,
      null,
      $sortOrder,
      'Date',
      true
    );

    $arrayOfPayments = $result->getData();
    $pagination = $result->getPagination();
    $continuationToken = $pagination->getContinuationToken();

    foreach ($arrayOfPayments as $p) {
      $paymentList[] = $this->transformPaymentToArray($p);
    }

    return $paymentList;
  }

  private function processPayments($formSlug, $payments, $formType) {
    $totalProcessed = 0;

    foreach ($payments as $payment) {
      \Civi::log()->debug('Processing payment', [
        'name' => $payment['first_name'] . ' ' . $payment['last_name'],
        'email' => $payment['email'],
        'amount' => $payment['amount']
      ]);

      $contact = CRM_Helloassosync_BAO_Contact::findOrCreate($payment['first_name'], $payment['last_name'], $payment['email']);
      CRM_Helloassosync_BAO_Contact::createOrUpdateAddress($contact['id'], $payment['address'], $payment['city'], $payment['postal_code'], $payment['country']);

      if ($formType == 'Membership') {
        CRM_Helloassosync_BAO_Order::createOrUpdateMembership($formSlug, $contact['id'], $payment['id'], $payment['date'], $payment['status'], $payment['amount']);
      }
      else {
        $donationFrequence = $this->extractFrequence($payment);
        CRM_Helloassosync_BAO_Order::createDonation($contact['id'], $payment['id'], $payment['date'], $payment['status'], $payment['amount'], $donationFrequence);
      }

      $totalProcessed++;
    }

    return $totalProcessed;
  }

  private function extractFrequence($payment): int {
    // Ponctuel = 1, Mensuel = 2
    if (!empty($payment['type'])) {
      return ($payment['type'] == 'MonthlyDonation') ? 2 : 1;
    }

    return 1;
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
    ];
  }

}
