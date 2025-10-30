<?php

class CRM_Helloassosync_BAO_HelloAssoConfig {
  private static CRM_Helloassosync_BAO_HelloAssoConfig $instance;

  public $config;
  public $organizationSlug;

  public static function getInstance(): CRM_Helloassosync_BAO_HelloAssoConfig {
    if (!self::$instance) {
      self::$instance = new CRM_Helloassosync_BAO_HelloAssoConfig();
    }
    return self::$instance;
  }

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

}
