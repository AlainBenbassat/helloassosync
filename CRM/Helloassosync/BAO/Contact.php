<?php

class CRM_Helloassosync_BAO_Contact {
  private const EMPLOYER_RELATIONSHIP_TYPE_ID = 5;

  public static function findOrCreate($company, $firstName, $lastName, $email): array {
    $orgId = null;

    [$person, $status] = self::findOrCreateIndividual($firstName, $lastName, $email);
    $personId = $person['id'];

    if (!empty($company)) {
      $org = self::findOrCreateOrganization($company);
      $orgId = $org['id'];
      self::linkPersonToOrg($person['id'], $org['id']);
    }

    return [$orgId, $personId, $status];
  }

  public static function updateCommunicationPreferences($contactId, $question, $answer) {
    $fieldName = self::mapQuestionToField($question);
    $fieldValue = self::mapAnswerToValue($answer);

    if ($fieldName === '' || $fieldValue === -1) {
      return;
    }

    \Civi\Api4\Contact::update(FALSE)
      ->addValue($fieldName, $fieldValue)
      ->addWhere('id', '=', $contactId)
      ->execute();
  }

  private static function findOrCreateOrganization($company) {
    $org = self::findOrganization($company);
    if (empty($org)) {
      self::createOrganization($company);
      $org = self::findOrganization($company);
    }

    return $org;
  }

  private static function findOrCreateIndividual($firstName, $lastName, $email) {
    $status = 'existing contact';

    $contact = self::findIndividual($firstName, $lastName, $email);
    if (empty($contact)) {
      $contact = self::findIndividual($lastName, $firstName, $email);
    }

    if (empty($contact)) {
      self::createIndividual($firstName, $lastName, $email);
      $status = 'new contact';
      $contact = self::findIndividual($firstName, $lastName, $email);
    }

    return [$contact, $status];
  }

  private static function linkPersonToOrg($personId, $orgId) {
    // check if the employer is already linked to the person
    $employerId = CRM_Core_DAO::singleValueQuery("select employer_id from civicrm_contact where id = $personId");
    if ($employerId && $employerId == $orgId) {
      return;
    }

    // check if the relationship between the person and the org already exists
    $relId = CRM_Core_DAO::singleValueQuery("
      select
        id
      from
        civicrm_relationship
      where
        relationship_type_id = " . self::EMPLOYER_RELATIONSHIP_TYPE_ID . "
      and
        contact_id_a = $personId
      and
        contact_id_b = $orgId
      and
        is_active = 1
    ");
    if ($relId) {
      return;
    }

    // OK, create the relationship
    \Civi\Api4\Relationship::create(FALSE)
      ->addValue('contact_id_a', $personId)
      ->addValue('contact_id_b', $orgId)
      ->addValue('relationship_type_id', self::EMPLOYER_RELATIONSHIP_TYPE_ID)
      ->addValue('is_active', TRUE)
      ->execute();

    // make this the default employer if the person did not have an employer
    if (empty($employerId)) {
      \Civi\Api4\Contact::update(FALSE)
        ->addValue('employer_id', $orgId)
        ->addWhere('id', '=', $personId)
        ->execute();
    }
  }

  public static function createOrUpdateAddress($contactId, $streetAddress, $city, $postalCode, $countryCode) {
    $address = \Civi\Api4\Address::get(FALSE)
      ->addSelect('*')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->first();

    if (empty($address)) {
      self::createAddress($contactId, $streetAddress, $city, $postalCode, $countryCode);
    }
    elseif (self::isDifferentAddress($address, $streetAddress, $city, $postalCode, $countryCode)) {
      self::updateAddress($address['id'], $streetAddress, $city, $postalCode, $countryCode);
    }
  }

  private static function findIndividual($firstName, $lastName, $email) {
    return \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'first_name', 'last_name', 'email.email')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('last_name', '=', $lastName)
      ->addJoin('Email AS email', 'INNER', ['id', '=', 'email.contact_id'])
      ->addWhere('email.email', '=', $email)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->first();
  }

  private static function createIndividual($firstName, $lastName, $email) {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', $firstName)
      ->addValue('last_name', $lastName)
      ->execute()
      ->first();

    self::storeContactInGroup($contact['id']);
    self::createEmail($contact['id'], $email);
  }

  private static function findOrganization($company) {
    return \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'organization_name')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('organization_name', '=', $company)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->first();
  }

  private static function createOrganization($company) {
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Organization')
      ->addValue('organization_name', $company)
      ->execute()
      ->first();

    self::storeContactInGroup($contact['id']);
  }

  private static function createEmail($contactId, $email) {
    \Civi\Api4\Email::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('email', $email)
      ->addValue('is_primary', TRUE)
      ->execute();
  }

  private static function storeContactInGroup($contactId) {
    $groupId = self::getGroupOfTheWeek();
    \Civi\Api4\GroupContact::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('group_id', $groupId)
      ->addValue('status', 'Added')
      ->execute();
  }

  private static function getGroupOfTheWeek() {
    $year = date('Y');
    $weekNumber = date('W');
    $name = "Synchro HelloAsso : nouveaux contacts $year-$weekNumber";

    $group = \Civi\Api4\Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('title', '=', $name)
      ->execute()
      ->first();
    if (empty($group)) {
      $group = \Civi\Api4\Group::create(FALSE)
        ->addValue('title', $name)
        ->addValue('is_active', TRUE)
        ->execute()
        ->first();
      $groupId = $group['id'];
    }
    else {
      $groupId = $group['id'];
    }

    return $groupId;
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

  public static function getCurrentMembership($contactId) {
    return \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status_id', 'IN', [1, 2, 3]) // new, current, grace
      ->addOrderBy('end_date', 'DESC')
      ->execute()
      ->first();
  }

  // TODO: use webhook here and a client specific extension?
  private static function mapQuestionToField($question) {
    if (empty($question)) {
      return '';
    }

    if (strpos($question, 'magazine trimestriel Goupil') !== FALSE) {
      return 'Abonnements.Goupil_papier_web_aucun_';
    }

    if (strpos($question, "Je souhaite recevoir de l'ASPAS") !== FALSE) {
      return 'Abonnements.Je_souhaite_recevoir_de_la_part_de_l_ASPAS_';
    }

    if (strpos($question, 'Je souhaite recevoir les informations locales') !== FALSE) {
      return 'Abonnements.Abonnement_lettre_d_information_locale';
    }

    if (strpos($question, 'Sélectionner le département à suivre') !== FALSE) {
      return 'Abonnements.D_partements_suivis';
    }

    return '';
  }

  // TODO: use webhook here and a client specific extension?
  private static function mapAnswerToValue($answer) {
    switch (strtolower($answer)) {
      case 'oui': return TRUE;
      case 'non': return FALSE;
      case 'en version papier': return 1;
      case 'en version électronique': return 2;
      case 'je ne souhaite pas recevoir le goupil': return 3;
      case 'uniquement la newsletter mensuelle': return 2;
      case 'aucune communication': return 3;
    }

    if (strpos(strtolower($answer), 'toutes les communications (') !== FALSE) {
      return 1;
    }

    if (self::isFrenchDepartment($answer)) {
      return trim($answer);
    }

    return -1;
  }

  private static function isFrenchDepartment($answer) {
    $dep = trim($answer);

    switch ($dep) {
      case '01':
      case '02':
      case '03':
      case '04':
      case '05':
      case '06':
      case '07':
      case '08':
      case '09':
      case '2A':
      case '2B':
        return TRUE;
    }

    if ($dep >= 10 && $dep <= 95) {
      return TRUE;
    }

    if ($dep >= 971 && $dep <= 976) {
      return TRUE;
    }

    return FALSE;
  }

}

