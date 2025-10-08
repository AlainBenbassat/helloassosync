<?php

use CRM_Helloassosync_ExtensionUtil as E;

class CRM_Helloassosync_Form_Settings extends CRM_Core_Form {
  private CRM_Helloassosync_BAO_Settings $settings;

  public function __construct($state = NULL, $action = CRM_Core_Action::NONE, $method = 'post', $name = NULL) {
    $this->settings = new CRM_Helloassosync_BAO_Settings();

    parent::__construct($state, $action, $method, $name);
  }

  public function buildQuickForm(): void {
    $this->setTitle('HelloAsso - paramètres de connexion API');

    $this->addFormFields();
    $this->setFormFieldsDefaultValues();
    $this->addFormButtons();

    $this->assign('elementNames', $this->getRenderableElementNames());
    $this->assign('testLink', CRM_Utils_System::url('civicrm/helloassosync/test'));
    $this->assign('formListLink', CRM_Utils_System::url('civicrm/helloassosync/list-forms'));
    $this->assign('paymentListLink', CRM_Utils_System::url('civicrm/helloassosync/list-payments'));

    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    $this->settings->setApiEndpoint($values['helloassosync_url']);
    $this->settings->setClientId($values['helloassosync_client_id']);
    $this->settings->setClientSecret($values['helloassosync_client_secret']);
    $this->settings->setOrganizationSlug($values['helloassosync_org_slug']);

    CRM_Core_Session::setStatus('Paramètres sauvegardés', E::ts('Settings'), 'success');

    parent::postProcess();
  }

  private function addFormFields(): void {
    $textFieldSize = 80;

    $this->add('text', 'helloassosync_url', E::ts('Endpoint'), ['size' => $textFieldSize], TRUE);
    $this->add('text', 'helloassosync_client_id', E::ts('Client ID'), ['size' => $textFieldSize], TRUE);
    $this->add('text', 'helloassosync_client_secret', E::ts('Client Secret'), ['size' => $textFieldSize], TRUE);
    $this->add('text', 'helloassosync_org_slug', E::ts('Organization Slug'), ['size' => $textFieldSize], TRUE);
  }

  private function addFormButtons() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ],
    ]);
  }

  private function setFormFieldsDefaultValues() {
    $defaults = [];
    $defaults['helloassosync_url'] = $this->settings->getApiEndpoint();
    $defaults['helloassosync_client_id'] = $this->settings->getClientId();
    $defaults['helloassosync_client_secret'] = $this->settings->getClientSecret();
    $defaults['helloassosync_org_slug'] = $this->settings->getOrganizationSlug();
    $this->setDefaults($defaults);
  }

  private function getRenderableElementNames(): array {
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
