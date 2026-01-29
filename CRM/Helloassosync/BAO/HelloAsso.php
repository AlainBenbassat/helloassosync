<?php

class CRM_Helloassosync_BAO_HelloAsso {
  private const DONATION_FREQUENCY_ONETIME = 1;
  private const DONATION_FREQUENCY_MONTHLY = 2;

  private $formsApi;
  private $paymentsApi;
  private $orderApi;

  public function __construct() {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $this->formsApi = new \OpenAPI\Client\Api\FormulairesApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
    $this->paymentsApi = new \OpenAPI\Client\Api\PaiementsApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
    $this->orderApi = new \OpenAPI\Client\Api\CommandesApi(new \GuzzleHttp\Client(), CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->config);
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

    $result = $this->formsApi->organizationsOrganizationSlugFormsGet(
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

  public function syncFormPayments(string $formSlug, string $formType, int $financialTypeId, ?int $campaignId, string $dateFrom, string $dateTo): int {
    $totalProcessed = 0;
    $continuationToken = null;
    $pageIndex = 1;
    $hasMoreData = TRUE;

    while ($hasMoreData) {
      $payments = $this->getPayments($formSlug, $formType, $dateFrom, $dateTo,'Asc', $continuationToken, $pageIndex, $hasMoreData);
      $totalProcessed += $this->processPayments($formSlug, $payments, $formType, $financialTypeId, $campaignId);
      $pageIndex++;
    }

    return $totalProcessed;
  }

  public function getPayments(string $formSlug, string $formType, string $dateFrom, string $dateTo, string $sortOrder, ?string &$continuationToken, int $pageIndex, bool &$hasMoreData) {
    $pageSize = 20;
    $paymentList = [];

    // date to is exclusive according to the helloasso api so we add one day to make it inclusive
    $dateTo = (new \DateTime($dateTo))->modify('+1 day')->format('Y-m-d');

    $result = $this->paymentsApi->organizationsOrganizationSlugFormsFormTypeFormSlugPaymentsGet(
      CRM_Helloassosync_BAO_HelloAssoConfig::getInstance()->organizationSlug,
      $formSlug,
      $formType,
      "{$dateFrom}T00:00:00.000Z",
      "{$dateTo}T00:00:00.000Z",
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

  public function processMailingSubscriptions($item, $contactId) {
    // mailing preferences are stored in custom fields of the order
    $customFields = $item->getCustomFields();
    foreach ($customFields as $customField) {
      $customFieldName = $customField->getName();
      $customFieldAnswer = $customField->getAnswer();
      CRM_Helloassosync_BAO_Contact::updateCommunicationPreferences($contactId, $customFieldName, $customFieldAnswer);
    }
  }

  private function processPayments($formSlug, $payments, $formType, $financialTypeId, $campaignId) {
    $totalProcessed = 0;

    // loop over all the payments
    foreach ($payments as $payment) {
      $this->logPayment($formSlug, $payment);
      $this->processPayment($formSlug, $payment, $formType, $financialTypeId, $campaignId);
      $totalProcessed++;
    }

    return $totalProcessed;
  }

  private function processPayment($formSlug, $payment, $formType, $financialTypeId, $campaignId) {
    // process the common things
    [$orgId, $personId, $order, $donationFrequency] = $this->processPaymentCommon($payment, $formType);

    // process the specific things
    if ($formType == 'Membership') {
      $this->processPaymentMembership($formSlug, $orgId, $personId, $payment, $donationFrequency, $financialTypeId, $campaignId, $order);
    }
    else {
      $this->processPaymentDonation($formSlug, $orgId, $personId, $payment, $donationFrequency, $financialTypeId, $campaignId);
    }
  }

  public function processPaymentCommon($payment, $formType): array {
    // create or update the person and optionally the organization
    // an organization gets precedence over a person for address
    [$orgId, $personId, $status] = CRM_Helloassosync_BAO_Contact::findOrCreate($payment['company'], $payment['first_name'], $payment['last_name'], $payment['email']);
    CRM_Helloassosync_BAO_Contact::createOrUpdateAddress($orgId ?? $personId, $payment['address'], $payment['city'], $payment['postal_code'], $payment['country']);
    if (!empty($payment['birth_date'])) {
      CRM_Helloassosync_BAO_Contact::updateBirthDate($personId, $payment['birth_date']);
    }

    // get the order details for: memberships OR new contacts OR one-time donations OR for the first monthly donation
    // the order contains custom fields like the mailing preferences
    // we don't need to update mailing preferences for recurring donations (except for the first installment)
    $order = NULL;
    $donationFrequency = $this->extractFrequence($payment);
    if ($formType == 'Membership' || $status == 'new contact' || $donationFrequency == self::DONATION_FREQUENCY_ONETIME || $payment['installment_number'] == 1) {
      $order = $this->orderApi->ordersOrderIdGet($payment['order_id']);

      // update mailing preferences, extracting them from the first item of the order
      $items = $order->getItems();
      if (count($items) > 0) {
        // an organization gets precedence over a person for mailing preferences
        $this->processMailingSubscriptions($items[0], $orgId ?? $personId);
      }
    }

    return [$orgId, $personId, $order, $donationFrequency];
  }

  /**
   * @param string $formSlug
   * @param mixed $orgId
   * @param mixed $personId
   * @param $payment
   * @param int $donationFrequency
   * @param $financialTypeId
   * @param $campaignId
   * @param \OpenAPI\Client\Model\HelloAssoApiV5ModelsStatisticsOrderDetail|null $order
   *
   * @return void
   */
  public function processPaymentMembership(string $formSlug, mixed $orgId, int $personId, $payment, int $donationFrequency, $financialTypeId, $campaignId, ?\OpenAPI\Client\Model\HelloAssoApiV5ModelsStatisticsOrderDetail $order): void {
    $payerContactId = $orgId ?? $personId;
    $softCredits = [];
    $contributionId = NULL;
    if (!empty($orgId)) {
      // and org always have the membership
      $payerHasMembership = TRUE;
    }
    else {
      // we will decide later if the payer will have a membership
      $payerHasMembership = FALSE;
    }

    if (CRM_Helloassosync_BAO_Order::contributionExists($payerContactId,  $payment['id'])) {
      return;
    }

    $totalAmount = $payment['amount'];
    $extraDonation = 0;

    // the items contain multiple people, so-called parrain - filleuil, and/or an extra donation
    foreach ($order->getItems() as $item) {
      $amount = $this->extractAmountFromItem($item);

      // process an optional extra donation on top of the membership
      if ($this->isExtraDonationInItem($item)) {
        // this is a donation on top of the membership
        $donationFinancialTypeId = 12; // Don
        $extraDonation = $amount;
        $totalAmount -= $extraDonation;
        $extraDonationContribId = CRM_Helloassosync_BAO_Order::createDonation($payerContactId, $payment['id'] . '-1', $payment['date'], $payment['status'], $extraDonation, $payment['payment_means'], $payment['installment_number'], $donationFrequency, $donationFinancialTypeId, $campaignId);
        if ($orgId) {
          // the donation is linked to the organization, so we need to create a soft contribution for the person
          // 5 = Dons dans le cadre professionnel
          CRM_Helloassosync_BAO_Order::createSoftContribution($extraDonationContribId, $personId, $extraDonation, 5);
        }
        continue;
      }

      // extract the contact details from the item
      [$firstName, $lastName, $email] = $this->extractPersonDetailsFromItem($item);
      [$address, $postalCode, $city, $country] = $this->extractAddressFromItem($item);
      $birthDate = $this->extractBirthDateFromItem($item);
      $phoneNumber = $this->extractPhoneNumberFromItem($item);

      // process the contact details
      [$ignore1, $personId, $ignore2] = CRM_Helloassosync_BAO_Contact::findOrCreate(NULL, $firstName, $lastName, $email);
      CRM_Helloassosync_BAO_Contact::createOrUpdateAddress($personId, $address, $city, $postalCode, $country);
      CRM_Helloassosync_BAO_Contact::updateBirthDate($personId, $birthDate);
      CRM_Helloassosync_BAO_Contact::createOrUpdatePhone($personId, $phoneNumber);

      if ($personId == $payerContactId) {
        // this is the payer, we will create the contribution later
        $payerHasMembership = TRUE;
      }
      else {
        // this is another person, we will create a soft credit for him/her
        $softCredits[] = [$personId, $amount];
        $this->processMailingSubscriptions($item, $personId);
      }
    }

    // create the contribution for the payer
    $contributionId = CRM_Helloassosync_BAO_Order::createDonation($payerContactId, $payment['id'], $payment['date'], $payment['status'], $totalAmount, $payment['payment_means'], $payment['installment_number'], $donationFrequency, $financialTypeId, $campaignId);

    // create the membership for the payer, if needed
    if ($payerHasMembership) {
      CRM_Helloassosync_BAO_Order::createOrUpdateMembership($formSlug, $payment['date'], $payerContactId);
    }

    // manage the soft credits
    foreach ($softCredits as [$personId, $amount]) {
      CRM_Helloassosync_BAO_Order::createSoftContribution($contributionId, $personId, $amount, 11); // 11=Parrainage
      if (empty($orgId)) {
        // for organisations: only a membership on the organisation, not the people in the items
        CRM_Helloassosync_BAO_Order::createOrUpdateMembership($formSlug, $payment['date'], $personId);
      }
    }
  }

  public function processPaymentDonation($formSlug, mixed $orgId, mixed $personId, $payment, int $donationFrequency, $financialTypeId, $campaignId): void {
    $contributionId = CRM_Helloassosync_BAO_Order::createDonation($orgId ?? $personId, $payment['id'], $payment['date'], $payment['status'], $payment['amount'], $payment['payment_means'], $payment['installment_number'], $donationFrequency, $financialTypeId, $campaignId);
    if ($orgId) {
      // the donation is linked to the organization, so we need to create a soft contribution for the person
      // 5 = Dons dans le cadre professionnel
      CRM_Helloassosync_BAO_Order::createSoftContribution($contributionId, $personId, $payment['amount'], 5);
    }
  }

  private function isExtraDonationInItem($item): bool {
    // we know it is an extra donation if the user is empty
    if (empty($item->getUser())) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function extractPersonDetailsFromItem($item) {
    $firstName = $item->getUser()->getFirstName();
    $lastName = $item->getUser()->getLastName();

    $email = '';

    $customFields = $item->getCustomFields();
    foreach ($customFields as $customField) {
      if ($customField->getName() == 'Email') {
        $email = $customField->getAnswer();
        break;
      }
    }

    return [$firstName, $lastName, $email];
  }

  private function extractAddressFromItem($item) {
    $address = '';
    $postalCode = '';
    $city = '';
    $country = 'FRA';

    $customFields = $item->getCustomFields();
    foreach ($customFields as $customField) {
      switch ($customField->getName()) {
        case 'Adresse':
          $address = $customField->getAnswer();
          break;
        case 'Code postal':
        case 'Code Postal':
          $postalCode = $customField->getAnswer();
          break;
        case 'Ville':
          $city = $customField->getAnswer();
          break;
        case 'Pays':
          // not available as custom field yet, but we add it in case it becomes available
          $country = $customField->getAnswer();
          break;
        default:
          //echo "Custom field not handled: " . $customField->getName() . "\n";
      }
    }

    return [$address, $postalCode, $city, $country];
  }

  private function extractBirthDateFromItem($item) {
    $birthDate = '';

    $customFields = $item->getCustomFields();
    foreach ($customFields as $customField) {
      if ($customField->getName() == 'Date de naissance') {
        $birthDate = $customField->getAnswer();
        if (strlen($birthDate) == 10) {
          // reformat from d/m/Y to Y-m-d
          $birthDate = substr($birthDate, 6, 4) . '-' . substr($birthDate, 3, 2) . '-' . substr($birthDate, 0, 2);
        }
        else {
          $birthDate = '';
        }
        break;
      }
    }

    return $birthDate;
  }

  private function extractPhoneNumberFromItem($item) {
    $phoneNumber = '';

    $customFields = $item->getCustomFields();
    foreach ($customFields as $customField) {
      if ($customField->getName() == 'Numéro de téléphone') {
        $phoneNumber = $customField->getAnswer();
        break;
      }
    }

    return $phoneNumber;
  }

  private function extractAmountFromItem($item) {
    return $item->getAmount() / 100;
  }

  private function logPayment($formSlug, $payment) {
    \Civi::log()->debug('Processing payment', [
      'form' => $formSlug,
      'name' => $payment['first_name'] . ' ' . $payment['last_name'],
      'company' => $payment['company'],
      'email' => $payment['email'],
      'payment date' => $payment['date'],
      'amount' => $payment['amount']
    ]);
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

    $dateOfBirth = $payer->getDateOfBirth();
    if ($dateOfBirth) {
      $formattedBirthDate = $dateOfBirth->format('Y-m-d');
    }
    else {
      $formattedBirthDate = '';
    }

    return [
      'id' => $p->getId(),
      'date' => $p->getDate()->format('Y-m-d H:i:s'),
      'amount' => $p->getAmount() / 100,
      'status' => $p->getState(),
      'first_name' => $payer->getFirstName(),
      'last_name' => $payer->getLastName(),
      'birth_date' => $formattedBirthDate,
      'email' => $payer->getEmail(),
      'address' => $payer->getAddress(),
      'city' => $payer->getCity(),
      'postal_code' => $payer->getZipCode(),
      'country' => $payer->getCountry(),
      'company' => $payer->getCompany(),
      'type' => $p->getItems()[0]->getType() ?? 0,
      'order_id' => $p->getOrder()->getId(),
      'installment_number' => $p->getInstallmentNumber(),
      'payment_means' => $p->getPaymentMeans(),
    ];
  }

}
