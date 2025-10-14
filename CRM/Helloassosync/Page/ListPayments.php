<?php
use CRM_Helloassosync_ExtensionUtil as E;

class CRM_Helloassosync_Page_ListPayments extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('HelloAsso - List Payments'));

    $formSlug = CRM_Utils_Request::retrieveValue('form_slug', 'String', '3');
    $formType = CRM_Utils_Request::retrieveValue('form_type', 'String', 'Donation');
    $dateFrom = CRM_Utils_Request::retrieveValue('date_from', 'String', date('Y-m-d'));
    $dateTo = CRM_Utils_Request::retrieveValue('date_to', 'String', date('Y-m-d'));

    $helloAsso = new CRM_Helloassosync_BAO_HelloAsso();

    $continuationToken = null;
    $payments = $helloAsso->getPayments($formSlug, $formType, $dateFrom, $dateTo,'Asc', $continuationToken);
    $i = 0;
    while (!empty($continuationToken)) {
      $payments = array_merge($payments, $helloAsso->getPayments($formSlug, $formType, $dateFrom, $dateTo,'Asc', $continuationToken));

      // limit the number of records (it's just a test page after all)
      if (++$i > 9) {
        break;
      }
    }

    $this->assign('paymentList', $payments);

    parent::run();
  }

}
