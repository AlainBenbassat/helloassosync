<?php

class CRM_Helloassosync_BAO_Contact {

  public static function findOrCreate($firstName, $lastName, $email) {
    $contact = self::findContact($firstName, $lastName, $email);
    if (empty($contact)) {
      $contact = self::findContact($lastName, $firstName, $email);
    }

    if (empty($contact)) {
      self::createContact($firstName, $lastName, $email);
      $contact = self::findContact($lastName, $firstName, $email);
    }

    return $contact;
  }

  private static function findContact($firstName, $lastName, $email) {
    return \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'first_name', 'last_name', 'email.email')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('last_name', '=', $lastName)
      ->addJoin('Email AS email', 'INNER', ['id', '=', 'email.contact_id'])
      ->addWhere('email.email', '=', $email)
      ->addWhere('is_deleted', '=', 0)
      ->execute()
      ->first();
  }

  private static function createContact($firstName, $lastName, $email) {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', $firstName)
      ->addValue('last_name', $lastName)
      ->execute()
      ->first();

    \Civi\Api4\Email::create(FALSE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('email', $email)
      ->addValue('is_primary', TRUE)
      ->execute();
  }


}

