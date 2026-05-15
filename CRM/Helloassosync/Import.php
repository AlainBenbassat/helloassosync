<?php

// cv php:eval 'CRM_Helloassosync_Import::run();'

class CRM_Helloassosync_Import {
  public static function run() {
    die('OBSOLETE');

    $dateFrom = '2026-01-02';
    $dateTo = '2026-01-31';

//    self::processFormWithinDateRange(1, "Donation", $dateFrom, $dateTo, 10, 56);
//    self::processFormWithinDateRange(2, "Donation", $dateFrom, $dateTo, 21, 3);
//    self::processFormWithinDateRange(5, "Donation", $dateFrom, $dateTo, 19, null);
//    self::processFormWithinDateRange(6, "Donation", $dateFrom, $dateTo, 10, 56);
//    self::processFormWithinDateRange(10, "Donation", $dateFrom, $dateTo, 12, 55);
    self::processFormWithinDateRange('adhesion-2026', "Membership", $dateFrom, $dateTo, 13, 109);
  }

  private static function processFormWithinDateRange($form_slug, $form_type, $date_from, $date_to, $financial_type_id, $camaign_id) {
    $dateStart = new DateTime($date_from);
    $dateEnd = new DateTime($date_to);

    while ($dateStart <= $dateEnd) {
      $currentDate = $dateStart->format('Y-m-d');

      try {
        echo "Formulaire\t$form_slug\tpaiements du\t$currentDate\t";

        $result = civicrm_api3('HelloAssoSync', 'getpayments', [
          'form_slug' => $form_slug,
          'form_type' => $form_type,
          'date_from' => $currentDate,
          'date_to' => $currentDate,
          'financial_type_id' => $financial_type_id,
          'campaign_id' => $camaign_id,
        ]);

        echo $result['values'] . "\n";
      }
      catch (Exception $e) {
        echo "ERREUR " . $e->getMessage() . "\n";
      }

      sleep(2);

      $dateStart->modify('+1 day');
    }
  }
}
