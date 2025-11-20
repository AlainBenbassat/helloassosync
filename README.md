# helloassosync

Synchronisation des formulaires HelloAsso

## Configuration de la connexion HelloAsso

Administrer > HelloAsso Synchronisation > Paramètres API

## Confiration de la synchronisation d'un formulaire

1. Administrer > Paramètres système > Tâches programmées
1. Add Scheduled Job
   * Nom: p.ex. synchro du formulaire de don numéro (slug) 3
   * Fréquence d'exécution: p.ex. Quotidien
   * Entité de la requête API: HelloAssoSync
   * Action de la requête API: getpayments
   * Paramètres de la commande:
     * form_slug=3 (ou autre slug du formulaire)
     * form_type=Donation (ou Membership)
     * date_from=yesterday (ou une date YYYY-MM-DD)
     * date_to=yesterday (ou une date YYYY-MM-DD)
     * campaign_id=55 (identifiant de la campagne, voir CiviCampaign)
