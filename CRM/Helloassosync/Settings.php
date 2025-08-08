<?php

class CRM_Helloassosync_Settings {
  private string $settingApiEndpoint = 'helloasso_api_endpoint';
  private string $settingClientId = 'helloasso_clientid';
  private string $settingClientSecret = 'helloasso_clientsecret';

  public function getApiEndpoint() {
    return Civi::settings()->get($this->settingApiEndpoint);
  }

  public function getClientId() {
    return Civi::settings()->get($this->settingClientId);
  }

  public function getClientSecret() {
    return Civi::settings()->get($this->settingClientSecret);
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
}