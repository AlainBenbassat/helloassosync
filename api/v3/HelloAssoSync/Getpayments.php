<?php
use CRM_Helloassosync_ExtensionUtil as E;

function _civicrm_api3_hello_asso_sync_Getpayments_spec(&$spec) {
  $spec['form_slug']['api.required'] = 1;
  $spec['form_type']['api.required'] = 1;
  $spec['date_from']['api.required'] = 0;
  $spec['date_to']['api.required'] = 0;
}

function civicrm_api3_hello_asso_sync_Getpayments($params) {
  try {
    $dateFrom = civicrm_api3_hello_asso_sync_processDateParam($params, 'date_from');
    $dateTo = civicrm_api3_hello_asso_sync_processDateParam($params, 'date_to');

    $helloAsso = new CRM_Helloassosync_BAO_HelloAsso();
    $msg = $helloAsso->syncFormPayments($params['form_slug'], $params['form_type'], $dateFrom, $dateTo);

    return civicrm_api3_create_success($msg, $params, 'HelloAssoSync', 'Getpayments');
  }
  catch (Exception $e) {
    throw new CRM_Core_Exception('Error in HelloAssoSync.Getpayments API: ' . $e->getMessage(), 999);
  }
}

function civicrm_api3_hello_asso_sync_processDateParam($params, $fieldName) {
  if (empty($params[$fieldName])) {
    return date('Y-m-d');
  }

  if ($params[$fieldName] == 'yesterday') {
    return date('Y-m-d', strtotime('-1 day'));
  }

  return $params[$fieldName];
}
