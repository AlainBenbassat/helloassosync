<?php

class CRM_Helloassosync_BAO_Contact {

  public static function findOrCreate($firstName, $lastName, $email) {
    $contact = self::findContact($firstName, $lastName, $email);
    if (empty($contact)) {
      $contact = self::findContact($lastName, $firstName, $email);
    }

    if (empty($contact)) {
      self::createContact($firstName, $lastName, $email);
      $contact = self::findContact($firstName, $lastName, $email);
    }

    return $contact;
  }

  public static function createOrUpdateAddress($contactId, $streetAddress, $city, $postalCode, $countryCode) {
    $address = \Civi\Api4\Address::get(FALSE)
      ->addSelect('*')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute()
      ->first();

    if (empty($address)) {
      self::createAddress($contactId, $streetAddress, $city, $postalCode, $countryCode);
    }
    elseif (self::isDifferentAddress($address, $streetAddress, $city, $postalCode, $countryCode)) {
      self::updateAddress($address['id'], $streetAddress, $city, $postalCode, $countryCode);
    }
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

  private static function createAddress($contactId, $streetAddress, $city, $postalCode, $countryCode) {
    \Civi\Api4\Address::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('street_address', $streetAddress)
      ->addValue('city', $city)
      ->addValue('postal_code', $postalCode)
      ->addValue('country_id', self::convertCountryCode($countryCode))
      ->addValue('is_primary', TRUE)
      ->execute();
  }

  private static function isDifferentAddress($address, $streetAddress, $city, $postalCode, $countryCode) {
    return $address['street_address'] !== $streetAddress
      || $address['city'] !== $city
      || $address['postal_code'] !== $postalCode;
  }

  private static function updateAddress($addressId, $streetAddress, $city, $postalCode, $countryCode) {
    \Civi\Api4\Address::update(FALSE)
      ->addWhere('id', '=', $addressId)
      ->addValue('street_address', $streetAddress)
      ->addValue('city', $city)
      ->addValue('postal_code', $postalCode)
      ->addValue('country_id', self::convertCountryCode($countryCode))
      ->execute();
  }

  private static function convertCountryCode($countryCode) {
    if ($countryCode === 'FRA') {
      return 1076;
    }

    // HelloAsso country codes are not documented, so best effort
    $twoLetterCode = substr($countryCode, 0, 2);
    $countryId = CRM_Core_DAO::singleValueQuery("select min(id) from civicrm_country where iso_code = '$twoLetterCode'");

    // return the found ID or Afhanistan (1001)
    return $countryId ?? 1001;
  }

}

