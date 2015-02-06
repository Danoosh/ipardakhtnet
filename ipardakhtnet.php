<?php 
/*
* iPresta.ir
*
* Do not edit or remove author copyright
* if you have any problem contact us at iPresta.ir
*
*  @author Danoosh Miralayi - iPresta.ir
*  @copyright  2014-2015 iPresta.ir
*  نکته مهم:
*  حذف یا تغییر این اطلاعات به هر شکلی ممنوع بوده و پیگرد قانونی دارد
*/

class iPardakhtNet extends PaymentModule
{  
	private $_html = '';

	private  $_service_url = 'http://pardakhtnet.com/webservice/index.php';
	private $_go_url = 'http://pardakhtnet.com/webservice/go.php';
    private $_verify_url = 'http://pardakhtnet.com/webservice/verify.php';

	public function __construct(){  
		$this->name = 'ipardakhtnet';
		$this->tab = 'payments_gateways';
		$this->version = '1.1';
		$this->bootstrap = true;
		$this->author = 'iPresta.ir';

		$this->currencies = true;
  		$this->currencies_mode = 'checkbox';

		parent::__construct();
		$this->context = Context::getContext();
		$this->page = basename(__FILE__, '.php');
		$this->displayName = $this->l('Pardakhtnet Payment');  
		$this->description = $this->l('A free module to pay online.');  
		$this->confirmUninstall = $this->l('Are you sure, you want to delete your details?');
		if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module');
		$config = Configuration::getMultiple(array('IPRESTA_PARDAKHTNET_UserName', 'IPRESTA_PARDAKHTNET_UserPassword'));			
		if (!isset($config['IPRESTA_PARDAKHTNET_UserName']))
			$this->warning = $this->l('Your Pardakhtnet username must be configured in order to use this module');

	}  
	public function install(){
		if (!parent::install()
	    	OR !Configuration::updateValue('IPRESTA_PARDAKHTNET_USER', '')
			OR !Configuration::updateValue('IPRESTA_PARDAKHTNET_TEST', 0)
            OR !Configuration::updateValue('IPRESTA_PARDAKHTNET_DEBUG', 0)
	      	OR !$this->registerHook('payment')
	      	OR !$this->registerHook('paymentReturn')){
			    return false;
		}else{
		    return true;
		}
	}
	public function uninstall(){
		if (!Configuration::deleteByName('IPRESTA_PARDAKHTNET_USER') 
            OR !Configuration::deleteByName('IPRESTA_PARDAKHTNET_TEST')
			OR !Configuration::deleteByName('IPRESTA_PARDAKHTNET_DEBUG')
			OR !parent::uninstall())
			return false;
		return true;
	}
	
	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Merchant Code'),
						'name' => 'IPRESTA_PARDAKHTNET_USER',
						'class' => 'fixed-width-lg',
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Enable Debug Mode'),
						'name' => 'IPRESTA_PARDAKHTNET_DEBUG',
						'class' => 'fixed-width-xs',
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitPardakhtNet';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}
	
	public function getConfigFieldsValues()
	{
		return array(
			'IPRESTA_PARDAKHTNET_USER' => Tools::getValue('IPRESTA_PARDAKHTNET_USER', Configuration::get('IPRESTA_PARDAKHTNET_USER')),
			'IPRESTA_PARDAKHTNET_DEBUG' => Tools::getValue('IPRESTA_PARDAKHTNET_DEBUG', (bool)Configuration::get('IPRESTA_PARDAKHTNET_DEBUG')),
		);
	}


    public function getContent()
	{
		$output = '';
		$errors = array();
		if (isset($_POST['submitPardakhtNet']))
		{
			if (empty($_POST['IPRESTA_PARDAKHTNET_USER']))
				$errors[] = $this->l('Your merchant code is required.');

			if (!count($errors))
			{
				Configuration::updateValue('IPRESTA_PARDAKHTNET_USER', $_POST['IPRESTA_PARDAKHTNET_USER']);
				Configuration::updateValue('IPRESTA_PARDAKHTNET_DEBUG', $_POST['IPRESTA_PARDAKHTNET_DEBUG']);
				$output = $this->displayConfirmation($this->l('Your settings have been updated.'));
			}
			else
				$output = $this->displayError(implode('<br />', $errors));
		}
		return $output.$this->renderForm();
	}
	
	public function prePayment()
	{
					
		$purchase_currency = new Currency(Currency::getIdByIsoCode('IRR'));
		$current_currency = new Currency($this->context->cookie->id_currency);			
		if($current_currency->id == $purchase_currency->id)
            $amount = number_format($this->context->cart->getOrderTotal(true, 3), 0, '', '');
		else
            $amount = number_format($this->convertPriceFull($this->context->cart->getOrderTotal(true, 3), $current_currency, $purchase_currency), 0, '', '');

        $id = Configuration::get('IPRESTA_PARDAKHTNET_USER');
        $callback = $this->context->link->getModuleLink('ipardakhtnet', 'validation');
        $res_num = substr($this->context->cart->id.rand(),-8);
       try
        {
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL,$this->_service_url);
            curl_setopt($ch,CURLOPT_POSTFIELDS,"id=".$id."&amount=".(int)($amount / 10)."&callback=".$callback."&resnum=".$res_num);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            $result = curl_exec($ch);
			curl_close($ch);
        }
        catch(PrestaShopException $e){
            $this->context->controller->errors[] = $this->l('Could not connect to bank or service.');
            return false;
        }

        if(isset($result) && $result > 0)
        {
            $this->context->cookie->__set("RefId", $res_num);
            $this->context->cookie->__set("amount", (int)$amount);

            $this->context->smarty->assign(array(
                'redirect_link' => $this->_go_url,
                'ref_id' => $result
            ));
            return true;
        }

 //       return $res;

		else
		{
            $this->context->controller->errors[] = $this->showMessages($result);
            return false;
		}
			


	}

	public function verify($res_num,$ref_num)
	{
        $id = Configuration::get('IPRESTA_PARDAKHTNET_USER');
        $purchase_currency = new Currency(Currency::getIdByIsoCode('IRR'));
        $current_currency = new Currency($this->context->cookie->id_currency);
        if($current_currency->id == $purchase_currency->id)
            $amount = number_format($this->context->cart->getOrderTotal(true, 3), 0, '', '');
        else
            $amount = number_format($this->convertPriceFull($this->context->cart->getOrderTotal(true, 3), $current_currency, $purchase_currency), 0, '', '');
        try
        {
            $ch2 = curl_init();
            curl_setopt($ch2,CURLOPT_URL,$this->_verify_url);
            curl_setopt($ch2,CURLOPT_POSTFIELDS,"id=".$id."&resnum=".$res_num."&refnum=".$ref_num."&amount=".(int)($amount / 10));
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch2,CURLOPT_RETURNTRANSFER,true);
            $result = curl_exec($ch2);
            curl_close($ch2);
        }
        catch(PrestaShopException $e){
            $this->context->controller->errors[] = $this->l('Could not connect to bank or service.');
            return false;
        }

        if(isset($result) && $result > 0)
            return true;
		elseif(isset($result) && $result <= 0)
			return $result;

        return false;
	}



	public function showMessages($result)
	{                
		$err = 'Error!';
        switch($result)
		{ 
			case -1: $err = $this->l('کدپذیرنده صحیح نمی باشد یا درگاه پذیرنده فعال نیست'); break;
			case -2: $err = $this->l('مقدار مبلغ صحیح نمی باشد یا کمتر از 100 تومان می باشد'); break;
			case -3: $err = $this->l('آدرس بازگشت صحیح نمی باشد'); break;
			case -4: $err = $this->l('درگاه پذیرنده فعال نمی باشد'); break;
			case -5: $err = $this->l('شماره فاکتور صحیح نمی باشد'); break;
			case -6: $err = $this->l('شماره فاکتور تکراري می باشد'); break;
			case -7: $err = $this->l('مشکل در شبکه بانکی وجود دارد'); break;

			}
		return $err;
	}


	public function hookPayment($params){
		if (!$this->active)
			return ;
		return $this->display(__FILE__, 'payment.tpl');
	}

    public function hookPaymentReturn($params)
    {
        if (!$this->active)
            return ;

        $order = new Order(Tools::getValue('id_order'));

        $this->context->smarty->assign(array(
            'id_order' => Tools::getValue('id_order'),
			'reference' => $order->reference,
			'ref_num' => Tools::getValue('ref_num'),
            'res_num' => Tools::getValue('res_num'),
            'ver' => $this->version,

        ));

        return $this->display(__FILE__, 'confirmation.tpl');
    }

	/**
	 *
	 * @return float converted amount from a currency to an other currency
	 * @param float $amount
	 * @param Currency $currency_from if null we used the default currency
	 * @param Currency $currency_to if null we used the default currency
	 */
	public static function convertPriceFull($amount, Currency $currency_from = null, Currency $currency_to = null)
	{
		if ($currency_from === $currency_to)
			return $amount;
		if ($currency_from === null)
			$currency_from = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
		if ($currency_to === null)
			$currency_to = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
		if ($currency_from->id == Configuration::get('PS_CURRENCY_DEFAULT'))
			$amount *= $currency_to->conversion_rate;
		else
		{
            $conversion_rate = ($currency_from->conversion_rate == 0 ? 1 : $currency_from->conversion_rate);
			// Convert amount to default currency (using the old currency rate)
			$amount = Tools::ps_round($amount / $conversion_rate, 2);
			// Convert to new currency
			$amount *= $currency_to->conversion_rate;
		}
		return Tools::ps_round($amount, 2);
	}
}