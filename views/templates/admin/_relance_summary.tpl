{if $active_campaign}
    <div class="alert alert-info">
        <strong>{l s='Active campaign:' mod='sj4webrelancepanier'} {$active_campaign.name|escape:'html'}</strong><br>
        {l s='Estimated reminders to send - T1: %count%' sprintf=['%count%' => $active_campaign.count] mod='sj4webrelancepanier'}
    </div>
{/if}

{foreach from=$followup_campaigns item=camp}
    <div class="alert alert-warning">
        <strong>{l s='Follow-up for campaign:' mod='sj4webrelancepanier'} {$camp.name|escape:'html'}</strong><br>
        {if $camp.t2}T2: {$camp.t2}{/if}
        {if $camp.t2 && $camp.t3} – {/if}
        {if $camp.t3}T3: {$camp.t3}{/if}
    </div>
{/foreach}
