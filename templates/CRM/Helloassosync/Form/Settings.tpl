
{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<p>
  <a href="{$testLink}">Tester la connexion</a> | <a href="{$formListLink}">Liste de formulaires</a> | <a href="{$paymentListLink}">Liste de paiements</a>
</p>
