{l s='Your order on %s is complete.' sprintf=$shop_name mod='ipardakhtnet'}
		{if !isset($reference)}
			<br /><br />{l s='Your order number' mod='ipardakhtnet'}: {$id_order}
		{else}
			<br /><br />{l s='Your order number' mod='ipardakhtnet'}: {$id_order}
			<br /><br />{l s='Your order reference' mod='ipardakhtnet'}: {$reference}
		{/if}		<br /><br />{l s='An email has been sent with this information.' mod='ipardakhtnet'}
		<br /><br /> <strong>{l s='Your order will be sent as soon as posible.' mod='ipardakhtnet'}</strong>
		<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='ipardakhtnet'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='expert customer support team' mod='ipardakhtnet'}</a>.
	</p><br />