<table>
  <tr>
    <th>Slug</th>
    <th>Titre</th>
    <th>Type</th>
    <th>État</th>
    <th>Lien</th>
  </tr>
  {foreach from=$formList item=form}
    <tr>
      <td>{$form.slug}</td>
      <td>{$form.title}</td>
      <td>{$form.type}</td>
      <td>{$form.status}</td>
      <td><a target="_blank" href="{$form.url}">lien</a></td>
    </tr>
  {/foreach}
</table>
