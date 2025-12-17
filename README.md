# helloassosync

Synchronisation des formulaires HelloAsso

## 1. Configurer les paramètres de connexion à l'API HelloAsso

Administrer > HelloAsso Synchronisation > Paramètres API

## 2. Configurer la synchronisation d'un formulaire

1. Administrer > Paramètres système > Tâches programmées
1. Cliquer sur "Add Scheduled Job"
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
