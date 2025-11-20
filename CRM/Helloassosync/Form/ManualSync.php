<?php

use CRM_Helloassosync_ExtensionUtil as E;

class CRM_Helloassosync_Form_ManualSync extends CRM_Core_Form {


  public function buildQuickForm(): void {
    $this->setTitle('HelloAsso - synchronisation manuelle');

    $this->addFormFields();
    $this->setFormFieldsDefaultValues();
    $this->addFormButtons();

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    try {
      $result = civicrm_api3('HelloAssoSync', 'getpayments', [
        'form_slug' => $values['form_slug'],
        'form_type' => $values['form_type'],
        'date_from' => $values['payment_date'],
        'date_to' => $values['payment_date'],
        'campaign_id' => $values['campaign_id'] ?? NULL,
      ]);

      CRM_Core_Session::setStatus(print_r($result['values'], TRUE), '','success');
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus($e->getMessage(), 'Erreur', 'error');
    }

    parent::postProcess();
  }

  private function addFormFields(): void {
    $this->add('text', 'form_slug', 'Slug du formulaire', [], TRUE);
    $this->addRadio('form_type', 'Type du formulaire', ['Membership' => 'Cotisation', 'Donation' => 'Don']);
    $this->add('text', 'campaign_id', 'Id de la campagne', [], FALSE);
    $this->add('datepicker', 'payment_date', 'Date', [], TRUE, ['time' => FALSE]);
  }

  private function setFormFieldsDefaultValues() {
    $defaults = [];
    $defaults['form_type'] = 'Donation';
    $this->setDefaults($defaults);
  }

  private function addFormButtons() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => 'Synchroniser les paiements',
        'isDefault' => TRUE,
      ],
    ]);
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
