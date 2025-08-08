<?php

use CRM_Helloassosync_ExtensionUtil as E;

class CRM_Helloassosync_Form_Settings extends CRM_Core_Form {
  private CRM_Helloassosync_Settings $settings;

  public function __construct($state = NULL, $action = CRM_Core_Action::NONE, $method = 'post', $name = NULL) {
    $this->settings = new CRM_Helloassosync_Settings();

    parent::__construct($state, $action, $method, $name);
  }

  public function buildQuickForm(): void {
    $this->setTitle(E::ts('HelloAsso API Settings'));

    $this->addFormFields();
    $this->setFormFieldsDefaultValues();
    $this->addFormButtons();

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    parent::postProcess();
  }

  private function addFormFields(): void {
    $this->add('text', 'helloassosync_url', E::ts('Endpoint'), [], TRUE);
    $this->add('text', 'helloassosync_client_id', E::ts('Client ID'), [], TRUE);
    $this->add('text', 'helloassosync_client_secret', E::ts('Client Secret'), [], TRUE);
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
