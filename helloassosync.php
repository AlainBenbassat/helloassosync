<?php

require_once 'helloassosync.civix.php';

use CRM_Helloassosync_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function helloassosync_civicrm_config(&$config): void {
  _helloassosync_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function helloassosync_civicrm_install(): void {
  _helloassosync_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function helloassosync_civicrm_enable(): void {
  _helloassosync_civix_civicrm_enable();
}
