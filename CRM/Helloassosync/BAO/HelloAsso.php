<?php

class CRM_Helloassosync_BAO_HelloAsso {
  private $config;
  private $organizationSlug;

  public function __construct() {
    require_once __DIR__ . '/../../../vendor/autoload.php';

    // get the saved connection settings (see civicrm/helloassosync/settings)
    $settings = new CRM_Helloassosync_BAO_Settings();
    $this->organizationSlug = $settings->getOrganizationSlug();

    // create an OAuth2 client
    $provider = new \League\OAuth2\Client\Provider\GenericProvider([
      'clientId'                => $settings->getClientId(),
      'clientSecret'            => $settings->getClientSecret(),
      'urlAccessToken'          => $settings->getApiEndpoint() . '/oauth2/token',
      'urlAuthorize'            => '',
      'urlResourceOwnerDetails' => '',
    ]);

    // get an access token
    $accessToken = $provider->getAccessToken('client_credentials');

    // configure the OpenAPI client
    $this->config = \OpenAPI\Client\Configuration::getDefaultConfiguration()->setAccessToken($accessToken->getToken());
    $this->config->setBooleanFormatForQueryString(\OpenAPI\Client\Configuration::BOOLEAN_FORMAT_STRING);
  }

  public function getOrganizationInfo() {
    $orgApi = new \OpenAPI\Client\Api\OrganisationApi(new \GuzzleHttp\Client(), $this->config);
    $result = $orgApi->organizationsOrganizationSlugGet(
      $this->organizationSlug
    );

    return [
      'name' => $result->getName(),
      'description' => $result->getDescription(),
      'address' => $result->getAddress(),
    ];
  }

  public function getForms() {
    $formList = [];

    $formsApi = new \OpenAPI\Client\Api\FormulairesApi(new \GuzzleHttp\Client(), $this->config);

    $result = $formsApi->organizationsOrganizationSlugFormsGet(
      $this->organizationSlug,
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

  public function getPayments() {
    $paymentList = [];

    $paymentsApi = new \OpenAPI\Client\Api\PaiementsApi(new \GuzzleHttp\Client(), $this->config);

    $result = $paymentsApi->organizationsOrganizationSlugFormsFormTypeFormSlugPaymentsGet(
      $this->organizationSlug,
      '3',
      \OpenAPI\Client\Model\HelloAssoApiV5ModelsEnumsFormType::DONATION,
      '2025-01-01T00:00:00Z',
      '2025-09-09T23:59:59Z',
      null,
      null,
      null,
      null,
      null,
      'Asc',
      'Date',
      true
    );

    $arrayOfPayments = $result->getData();
    $pagination = $result->getPagination();
    $continuationToken = $pagination->getContinuationToken();

    foreach ($arrayOfPayments as $p) {
      $payer = $p->getPayer();

      $formList[] = [
        'id' => $p->getId(),
        'date' => $p->getDate()->format('Y-m-d H:i:s'),
        'amount' => $p->getAmount(),
        'status' => $p->getState(),
        'first_name' => $payer->getFirstName(),
        'last_name' => $payer->getLastName(),
        'email' => $payer->getEmail(),
        'address' => $payer->getAddress(),
        'city' => $payer->getCity(),
        'postal_code' => $payer->getZipCode(),
        'country' => $payer->getCountry(),
        'company' => $payer->getCompany(),
      ];
    }

    return $formList;
  }
}
