<?php
use CRM_Helloassosync_ExtensionUtil as E;
use League\OAuth2\Client\Provider\GenericProvider;


class CRM_Helloassosync_Page_Test extends CRM_Core_Page {

  public function run() {

    CRM_Utils_System::setTitle(E::ts('HelloAsso Test'));

    $msg = 'HelloAsso Test';

    $msg = $this->testHelloAssoSyncGetForms();

    $this->assign('msg', $msg);

    parent::run();
  }

  private function testHelloAssoSync2(): string {
    $msg = '';
    $settings = new CRM_Helloassosync_Settings();

    try {
      require_once __DIR__ . '/../../../vendor/autoload.php';

      // 1) Get OAuth2 token via client credentials
      $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => $settings->getClientId(),
        'clientSecret'            => $settings->getClientSecret(),
        'urlAccessToken'          => $settings->getApiEndpoint() . '/oauth2/token',
        'urlAuthorize'            => '',
        'urlResourceOwnerDetails' => ''
      ]);

      $accessToken = $provider->getAccessToken('client_credentials');

      // 2) Configure HelloAsso OpenAPI client
      $config = \OpenAPI\Client\Configuration::getDefaultConfiguration()
        ->setAccessToken($accessToken->getToken());
      $config->setBooleanFormatForQueryString(\OpenAPI\Client\Configuration::BOOLEAN_FORMAT_STRING);


      // If your endpoint is not the default host used by the SDK, set it explicitly
      // Example: https://api.helloasso.com/v5
      //$config->setHost(rtrim($settings->getApiEndpoint(), '/'));

      // 3) Create Payments API client
      // Note: SDK package namespaces are generated from HelloAsso OpenAPI spec.
      // If your SDK version exposes a different API class name, adjust accordingly.
      $paymentsApi = new \OpenAPI\Client\Api\PaiementsApi(
        new \GuzzleHttp\Client(),
        $config
      );

      // 4) Fetch the 5 most recent payments
      // Common parameters seen in the SDK for listing:
      // - $pageIndex (default 1)
      // - $pageSize (here 5)
      // - $sortOrder ('Desc' for most recent first)
      // Other filters (organization, status, dates…) are optional and left null
      $pageIndex = 1;
      $pageSize = 5;
      $sortOrder = 'Desc';

      $organizationSlug = 'aspas-association-pour-la-protection-des-animaux-sauvages';
      $result = $paymentsApi->organizationsOrganizationSlugPaymentsGet(
        $organizationSlug,
        null,//'2024-05-01T00:00:00Z', // from
        null, //'2025-08-31T23:59:59Z', // to
        null, // userSearchKey
        1,    // pageIndex
        5,    // pageSize (latest 5 payments)
        null, // continuationToken
        null, // states
        null, // sortOrder (defaults to Desc)
        null, // sortField (defaults to Date)
        null // withCount
      );


      // 5) Build a readable message from the returned collection
      // Most HelloAsso list endpoints return an object with 'data' (array) and paging info.
      $lines = [];
      $lines[] = 'Latest 5 payments:';

      $payments = [];
      if (is_array($result)) {
        // Some SDKs may return the array directly
        $payments = $result;
      } elseif (is_object($result)) {
        // Typical generated model has getData()
        if (method_exists($result, 'getData')) {
          $payments = $result->getData() ?? [];
        } elseif (property_exists($result, 'data')) {
          $payments = $result->data ?? [];
        }
      }

      foreach ($payments as $p) {
        // Models are typically of type HelloAssoApiV5ModelsPaymentPublicPaymentModel
        $id = method_exists($p, 'getId') ? $p->getId() : ($p->id ?? null);
        $amountCents = method_exists($p, 'getAmount') ? $p->getAmount() : ($p->amount ?? 0);
        $status = method_exists($p, 'getStatus') ? $p->getStatus() : ($p->status ?? null);
        $date = method_exists($p, 'getDate') ? $p->getDate() : ($p->date ?? null);
        $payerFirst = method_exists($p, 'getPayerFirstName') ? $p->getPayerFirstName() : ($p->payer_first_name ?? '');
        $payerLast = method_exists($p, 'getPayerLastName') ? $p->getPayerLastName() : ($p->payer_last_name ?? '');

        $amount = number_format(((int)$amountCents) / 100, 2, '.', ' ');
        $dateStr = $date instanceof \DateTime ? $date->format('Y-m-d H:i:s') : (is_string($date) ? $date : '');

        $lines[] = sprintf(
          '#%s | %s € | %s | %s %s',
          (string)$id,
          $amount,
          is_object($status) && method_exists($status, '__toString') ? (string)$status : (string)$status,
          (string)$payerFirst,
          (string)$payerLast
        ) . ($dateStr ? ' | ' . $dateStr : '');
      }

      if (count($payments) === 0) {
        $lines[] = '(no payments found)';
      }

      $msg = implode("\n", $lines);
    } catch (\Throwable $e) {
      $msg = 'Error while fetching payments: ' . $e->getMessage();
    }

    return $msg;
  }

  private function testHelloAssoSyncGetForms(): string {
    $msg = '';
    $settings = new CRM_Helloassosync_Settings();

    try {
      require_once __DIR__ . '/../../../vendor/autoload.php';

      // 1) Get OAuth2 token via client credentials
      $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => $settings->getClientId(),
        'clientSecret'            => $settings->getClientSecret(),
        'urlAccessToken'          => $settings->getApiEndpoint() . '/oauth2/token',
        'urlAuthorize'            => '',
        'urlResourceOwnerDetails' => ''
      ]);

      $accessToken = $provider->getAccessToken('client_credentials');

      // 2) Configure HelloAsso OpenAPI client
      $config = \OpenAPI\Client\Configuration::getDefaultConfiguration()
        ->setAccessToken($accessToken->getToken());
      $config->setBooleanFormatForQueryString(\OpenAPI\Client\Configuration::BOOLEAN_FORMAT_STRING);

      $formsApi = new \OpenAPI\Client\Api\FormulairesApi(
        new \GuzzleHttp\Client(),
        $config
      );

      $organizationSlug = 'aspas-association-pour-la-protection-des-animaux-sauvages';
      $result = $formsApi->organizationsOrganizationSlugFormsGet(
        $organizationSlug,
        null,
      );


      $lines = [];
      $lines[] = 'Latest 5 forms:';

      $forms = [];
      if (is_array($result)) {
        // Some SDKs may return the array directly
        $forms = $result;
      } elseif (is_object($result)) {
        // Typical generated model has getData()
        if (method_exists($result, 'getData')) {
          $forms = $result->getData() ?? [];
        } elseif (property_exists($result, 'data')) {
          $forms = $result->data ?? [];
        }
      }

      foreach ($forms as $p) {
        $slug = $p->getFormSlug();
        $url = $p->getUrl();

        $lines[] = $slug . ', ' . $url;
      }

      if (count($forms) === 0) {
        $lines[] = '(no payments found)';
      }

      $msg = implode("<br>", $lines);
    } catch (\Throwable $e) {
      $msg = 'Error while fetching payments: ' . $e->getMessage();
    }

    return $msg;
  }

}
