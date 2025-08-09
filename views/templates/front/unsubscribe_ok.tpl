{*
  SJ4WEB - Abandoned Cart Reminder (sj4webrelancepanier)
  Copyright (C) 2025  SJ4WEB.FR
  Licensed under GPL-3.0-or-later
  See LICENSE file for more details.
*}

{extends file='page.tpl'}
{block name='page_content'}
    <div class="rp-unsubscribe">
        <h1>{l s='Unsubscription confirmed' d='Modules.Sj4webrelancepanier.Front'}</h1>
        <p>{l s='The email' d='Modules.Sj4webrelancepanier.Front'} <strong>{$email|escape:'html':'UTF-8'}</strong>
            {l s='has been unsubscribed from cart reminder emails.' d='Modules.Sj4webrelancepanier.Front'}</p>
    </div>
{/block}
