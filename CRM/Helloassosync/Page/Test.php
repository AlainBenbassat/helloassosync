<?php
use CRM_Helloassosync_ExtensionUtil as E;
use League\OAuth2\Client\Provider\GenericProvider;

class CRM_Helloassosync_Page_Test extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(E::ts('HelloAsso - Info'));;

    $helloAsso = new CRM_Helloassosync_BAO_HelloAsso();
    $this->assign('orgInfo', $helloAsso->getOrganizationInfo());

    parent::run();
  }

}
