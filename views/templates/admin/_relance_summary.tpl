{*
  SJ4WEB - Abandoned Cart Reminder (sj4webrelancepanier)
  Copyright (C) 2025  SJ4WEB.FR
  Licensed under GPL-3.0-or-later
  See LICENSE file for more details.
*}

{if $active_campaign}
    <div class="alert alert-info">
        <strong>{l s='Active campaign:' d='Modules.Sj4webrelancepanier.Admin'} {$active_campaign.name|escape:'html'}</strong><br>
        {l s='Estimated reminders to send - T1: %count%' sprintf=['%count%' => $active_campaign.count] d='Modules.Sj4webrelancepanier.Admin'}
    </div>
{/if}

{foreach from=$followup_campaigns item=camp}
    <div class="alert alert-warning">
        <strong>{l s='Follow-up for campaign:' d='Modules.Sj4webrelancepanier.Admin'} {$camp.name|escape:'html'}</strong><br>
        {if $camp.t2}T2: {$camp.t2}{/if}
        {if $camp.t2 && $camp.t3} – {/if}
        {if $camp.t3}T3: {$camp.t3}{/if}
    </div>
{/foreach}
