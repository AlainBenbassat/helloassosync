<?php

class CRM_Helloassosync_BAO_Navigation {
  public static function createIfNotExists() {
    if (self::exists()) {
      return;
    }

    $topMenu = self::createTopMenu();
    self::createSettingsMenu($topMenu['id']);
  }

  private static function exists() {
    $navigation = \Civi\Api4\Navigation::get(FALSE)
      ->addWhere('name', '=', 'helloassosync')
      ->execute()
      ->first();

    return !empty($navigation);
  }

  private static function createTopMenu() {
    return \Civi\Api4\Navigation::create(FALSE)
      ->addValue('domain_id', 1)
      ->addValue('label', 'HelloAsso Synchronisation')
      ->addValue('name', 'helloassosync')
      ->addValue('url', null)
      ->addValue('permission', ['administer CiviCRM'])
      ->addValue('parent_id', self::getAdministerMenuId())
      ->addValue('is_active', 1)
      ->execute()
      ->first();
  }

  private static function createSettingsMenu(int $parentId) {
    return \Civi\Api4\Navigation::create(FALSE)
      ->addValue('domain_id', 1)
      ->addValue('label', 'Paramètres API')
      ->addValue('name', 'helloassosync_settings')
      ->addValue('url', 'civicrm/helloassosync/settings')
      ->addValue('permission', ['administer CiviCRM'])
      ->addValue('parent_id', $parentId)
      ->addValue('is_active', 1)
      ->execute()
      ->first();
  }

  private static function getAdministerMenuId() {
    $menu = \Civi\Api4\Navigation::get(FALSE)
      ->addWhere('name', '=', 'Administer')
      ->execute()
      ->first();

    return $menu['id'];
  }
}
