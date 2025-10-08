<?php
use CRM_Helloassosync_ExtensionUtil as E;

class CRM_Helloassosync_Page_ListForms extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('HelloAsso - List Forms'));;

    $helloAsso = new CRM_Helloassosync_BAO_HelloAsso();
    $this->assign('formList', $helloAsso->getForms());

    parent::run();
  }

}
