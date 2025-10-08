<?php

class CRM_Helloassosync_BAO_Settings {
  private string $settingApiEndpoint = 'helloasso_api_endpoint';
  private string $settingClientId = 'helloasso_clientid';
  private string $settingClientSecret = 'helloasso_clientsecret';
  private string $settingOrganizationSlug = 'helloasso_organization_slug';

  public function getApiEndpoint() {
    return Civi::settings()->get($this->settingApiEndpoint);
  }

  public function getClientId() {
    return Civi::settings()->get($this->settingClientId);
  }

  public function getClientSecret() {
    return Civi::settings()->get($this->settingClientSecret);
  }

  public function getOrganizationSlug() {
    return Civi::settings()->get($this->settingOrganizationSlug);
  }

  public function setApiEndpoint($value) {
    Civi::settings()->set($this->settingApiEndpoint, $value);
  }

  public function setClientId($value) {
    Civi::settings()->set($this->settingClientId, $value);
  }

  public function setClientSecret($value) {
    Civi::settings()->set($this->settingClientSecret, $value);
  }

  public function setOrganizationSlug($value) {
    Civi::settings()->set($this->settingOrganizationSlug, $value);
  }
}
