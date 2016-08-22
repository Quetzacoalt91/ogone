<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class Ogone extends PaymentModule
{

	const AUTHORIZATION_CANCELLED = 'OGONE_AUTHORIZATION_CANCELLED';
	const CANCELLED = 'OGONE_CANCELLED';
	const PAYMENT_ACCEPTED = 'OGONE_PAYMENT_ACCEPTED';
	const PAYMENT_AUTHORIZED = 'OGONE_PAYMENT_AUTHORIZED';
	const PAYMENT_CANCELLED = 'OGONE_PAYMENT_CANCELLED';
	const PAYMENT_ERROR = 'OGONE_PAYMENT_ERROR';
	const PAYMENT_IN_PROGRESS = 'OGONE_PAYMENT_IN_PROGRESS';
	const PAYMENT_UNCERTAIN = 'OGONE_PAYMENT_UNCERTAIN';
	const REFUND = 'OGONE_REFUND';
	const REFUND_ERROR = 'OGONE_REFUND_ERROR';
	const REFUND_IN_PROGRESS = 'OGONE_REFUND_IN_PROGRESS';

	const OPERATION_SALE = 'SAL';
	const OPERATION_AUTHORISE = 'RES';

	private $ignore_key_list = array('secure_key', 'ORIG');

	private $needed_key_list = array('orderID', 'amount', 'currency', 'PM', 'ACCEPTANCE', 'STATUS',
		'CARDNO', 'PAYID', 'NCERROR', 'BRAND', 'SHASIGN');

	/*
	 * flag for eu_legal
	 * @see https://github.com/EU-Legal/
	 */
	public $is_eu_compatible = true;

	/**
	 * List of operations allowed
	 * @var array
	 */
	private $allowed_operations = array(self::OPERATION_SALE, self::OPERATION_AUTHORISE);

	/**
	 * Return codes
	 * @var array
	 */
	private $return_codes = array(
		0  => array('Incomplete or invalid', self::PAYMENT_CANCELLED),
		1  => array('Cancelled by customer', self::PAYMENT_CANCELLED),
		2  => array('Authorisation declined', self::PAYMENT_ERROR),
		4  => array('Order stored', self::PAYMENT_IN_PROGRESS),
		40 => array('Stored waiting external result', self::PAYMENT_IN_PROGRESS),
		41 => array('Waiting for client payment	', self::PAYMENT_IN_PROGRESS),
		5  => array('Authorised', self::PAYMENT_AUTHORIZED),
		50 => array('Authorized waiting external result', self::PAYMENT_IN_PROGRESS),
		51 => array('Authorisation waiting', self::PAYMENT_IN_PROGRESS),
		52 => array('Authorisation not known', self::PAYMENT_IN_PROGRESS),
		55 => array('Standby', self::PAYMENT_IN_PROGRESS),
		56 => array('OK with scheduled payments', self::PAYMENT_AUTHORIZED),
		57 => array('Not OK with scheduled payments', self::PAYMENT_ERROR),
		59 => array('Authoris. to be requested manually', self::PAYMENT_ERROR),
		6  => array('Authorised and cancelled', self::AUTHORIZATION_CANCELLED),
		61 => array('Author. deletion waiting', self::AUTHORIZATION_CANCELLED),
		62 => array('Author. deletion uncertain', self::AUTHORIZATION_CANCELLED),
		63 => array('Author. deletion refused', self::AUTHORIZATION_CANCELLED),
		64 => array('Authorised and cancelled', self::AUTHORIZATION_CANCELLED),
		7  => array('Payment deleted', self::CANCELLED),
		71 => array('Payment deletion pending', self::CANCELLED),
		72 => array('Payment deletion uncertain', self::CANCELLED),
		73 => array('Payment deletion refused', self::CANCELLED),
		74 => array('Payment deleted', self::CANCELLED),
		75 => array('Deletion processed by merchant', self::CANCELLED),
		8  => array('Refund', self::REFUND),
		81 => array('Refund pending', self::REFUND_IN_PROGRESS),
		82 => array('Refund uncertain', self::REFUND_IN_PROGRESS),
		83 => array('Refund refused', self::REFUND_ERROR),
		84 => array('Payment declined by the acquirer', self::REFUND_ERROR),
		85 => array('Refund processed by merchant', self::REFUND),
		9  => array('Payment requested', self::PAYMENT_ACCEPTED),
		91 => array('Payment processing', self::PAYMENT_IN_PROGRESS),
		92 => array('Payment uncertain', self::PAYMENT_UNCERTAIN),
		93 => array('Payment refused', self::PAYMENT_ERROR),
		94 => array('Refund declined', self::PAYMENT_ERROR),
		95 => array('Payment processed by merchant', self::PAYMENT_ACCEPTED),
		96 => array('Refund reversed', self::PAYMENT_ACCEPTED),
		99 => array('Being processed', self::PAYMENT_IN_PROGRESS)
	);

	/**
	 * List of new states to install
	 * At list names['en'] is mandatory
	 * @var array
	 */
	private $new_statuses = array(
		self::PAYMENT_IN_PROGRESS => array(
			'names' => array('en' => 'Ogone payment in progress', 'fr' => 'Ogone paiement en cours'),
			'properties' => array('color' => 'royalblue', 'logable' => true)
		),
		self::PAYMENT_UNCERTAIN => array(
			'names' => array('en' => 'Ogone payment uncertain', 'fr' => 'Ogone paiement incertain'),
			'properties' => array('color' => 'orange')
		),
		self::PAYMENT_AUTHORIZED => array(
			'names' => array('en' => 'Ogone payment authorized', 'fr' => 'Ogone paiement autorisé'),
			'properties' => array('color' => 'royalblue')
		)
	);


	public function __construct()
	{
		$this->name = 'ogone';
		$this->tab = 'payments_gateways';
		$this->version = '2.13';
		$this->author = 'Ingenico Payment Services';
		$this->module_key = '787557338b78e1705f2a4cb72b1dbb84';

		parent::__construct();

		$this->displayName = 'Ingenico Payment Services (Ogone)';
		$desc = 'With over 80 different payment methods and 200+ acquirer connections, Ingenico helps you manage, ';
		$desc .= 'collect and secure your online or mobile payments, help prevent fraud and drive your business!';

		$this->description = $this->l($desc);
		/* Backward compatibility */
		if (version_compare(_PS_VERSION_, '1.5', '<')) {
			require_once _PS_MODULE_DIR_.'ogone/backward_compatibility/backward.php';
		}
	}

	public function install()
	{
		/* For 1.4.3 and less compatibility */
		$update_config = array('PS_OS_CHEQUE', 'PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_CANCELED', 'PS_OS_REFUND', 'PS_OS_ERROR',
								'PS_OS_OUTOFSTOCK', 'PS_OS_BANKWIRE', 'PS_OS_PAYPAL', 'PS_OS_WS_PAYMENT');
		if (!Configuration::get('PS_OS_PAYMENT'))
			foreach ($update_config as $u)
			if (!Configuration::get($u) && defined('_'.$u.'_'))
			Configuration::updateValue($u, constant('_'.$u.'_'));

		return (parent::install() &&
				$this->addStatuses() &&
				Configuration::updateValue('OGONE_OPERATION', self::OPERATION_SALE) &&
				Configuration::get('OGONE_OPERATION') === self::OPERATION_SALE &&
				$this->registerHook('payment') &&
				$this->registerHook('orderConfirmation') &&
				$this->registerHook('backOfficeHeader') &&
				(!is_callable(array('Hook', 'getIdByName')) || (!Hook::getIdByName('displayPaymentEU') || $this->registerHook('displayPaymentEU'))));
	}

	/**
	 * Adds itermediary statuses. Needs to be public, because it's called by upgrade_module_2_11
	 * @return boolean
	 */
	public function addStatuses()
	{
		$result = true;

		$statuses = $this->getExistingStatuses();
		foreach ($this->new_statuses as $code => $status)
		{
				if (isset($statuses[$status['names']['en']]))
								continue;
						if (!$this->addStatus($code, $status['names'], isset($status['properties']) ? $status['properties'] : array()))
								$result = false;
		}
		if (version_compare(_PS_VERSION_, '1.5', 'ge') && is_callable('Cache', 'clean'))
			Cache::clean('OrderState::getOrderStates*');

		Configuration::updateValue(self::PAYMENT_ACCEPTED, Configuration::get('PS_OS_PAYMENT'), false, false);
		Configuration::updateValue(self::PAYMENT_ERROR, Configuration::get('PS_OS_ERROR'), false, false);

		return $result;
	}

	/**
	 * Returns list of existing order statuses
	 * @return multitype:number
	 */
	protected function getExistingStatuses()
	{
		$statuses = array();
		$select_lang_id = (int)Language::getIdByIso('en');
		if (!$select_lang_id)
			$select_lang_id = (int)Configuration::get('PS_LANG_DEFAULT');
		foreach (OrderState::getOrderStates($select_lang_id) as $status)
			$statuses[$status['name']] = (int)$status['id_order_state'];
		return $statuses;
	}


	/**
	 * Adds new order state on install
	 * @param string $code
	 * @param array $names
	 * @param array $properties
	 * @return boolean
	 */
	protected function addStatus($code, array $names = array(), array $properties = array())
	{
		$order_state = new OrderState();
		foreach (Language::getLanguages(false) as $language)
		{
			$iso_code = Tools::strtolower($language['iso_code']);
			$order_state->name[(int)$language['id_lang']] = isset($names[$iso_code]) ?  $names[$iso_code] : $names['en'];
		}
		foreach ($properties as $property => $value)
			$order_state->{$property} = $value;
		$order_state->module_name = $this->name;
		$result = $order_state->add() && Validate::isLoadedObject($order_state);
		if ($result)
		{
			Configuration::updateValue($code, $order_state->id, false, false);
			$source = dirname(__FILE__).DIRECTORY_SEPARATOR.'logo.gif';
			$targets = array(
				_PS_IMG_DIR_.DIRECTORY_SEPARATOR.'os'.DIRECTORY_SEPARATOR.sprintf('%d.gif', $order_state->id),
				_PS_TMP_IMG_DIR_.DIRECTORY_SEPARATOR.sprintf('order_state_mini_%d.gif', $order_state->id),
				version_compare(_PS_VERSION_, '1.5', 'ge') ?
					_PS_TMP_IMG_DIR_.DIRECTORY_SEPARATOR.sprintf('order_state_mini_%d_%d.gif', $order_state->id, Context::getContext()->shop->id) :
					null,
			);
			foreach (array_filter($targets) as $target)
				copy($source, $target);
		}
		return $result;
	}

	public function hookBackOfficeHeader()
	{
		if ((int)strcmp((_PS_VERSION_ < '1.5' ? Tools::getValue('configure') : Tools::getValue('module_name')), $this->name) == 0)
		{
			if (_PS_VERSION_ < '1.5')
				return '<script type="text/javascript" src="'.__PS_BASE_URI__.'js/jquery/jquery-ui-1.8.10.custom.min.js"></script>
					<script type="text/javascript" src="'.__PS_BASE_URI__.'js/jquery/jquery.fancybox-1.3.4.js"></script>
					<link type="text/css" rel="stylesheet" href="'.__PS_BASE_URI__.'css/jquery.fancybox-1.3.4.css" />';
			else
			{
				$this->context->controller->addJquery();
				$this->context->controller->addJQueryPlugin('fancybox');
			}
		}
		return '';
	}

	public function getContent()
	{
		if (!isset($this->_html) || empty($this->_html))
			$this->_html = '';

		if (Tools::isSubmit('submitOgone'))
		{
			Configuration::updateValue('OGONE_PSPID', Tools::getValue('OGONE_PSPID'));
			Configuration::updateValue('OGONE_SHA_IN', Tools::getValue('OGONE_SHA_IN'));
			Configuration::updateValue('OGONE_SHA_OUT', Tools::getValue('OGONE_SHA_OUT'));
			Configuration::updateValue('OGONE_MODE', (int)Tools::getValue('OGONE_MODE'));
			Configuration::updateValue('OGONE_OPERATION', in_array(Tools::getValue('OGONE_OPERATION'), $this->allowed_operations) ?
			Tools::getValue('OGONE_OPERATION') : self::OPERATION_SALE);
			$data_sync = (($pspid = Configuration::get('OGONE_PSPID'))
				? '<img src="http://api.prestashop.com/modules/ogone.png?pspid='.urlencode($pspid).'&mode='.
				(int)Tools::getValue('OGONE_MODE').'" style="float:right" />'
				: ''
			);

			$this->_html .= (version_compare(_PS_VERSION_, '1.6', 'ge') ? '<div class="conf bootstrap"><div class="conf alert alert-success">'.$this->l('Configuration updated').$data_sync.'</div></div>' : '<div class="conf">'.$this->l('Configuration updated').$data_sync.'</div>');
		}

		$hide_info_tabs = (bool)Configuration::get('OGONE_PSPID');

		$this->_html .= '<div data-acc-tgt="ogone_info" class="ogone_acc ogone_info '.($hide_info_tabs ? 'ogone_hide' : '').'" style="background-image: url('._MODULE_DIR_.$this->name;
		$this->_html .= '/views/img/desc_ogone.png);background-repeat:no-repeat;background-position:8px center;">'.$this->l('Description').'</div>';
		$this->_html .= $this->getTranslatedAdminTemplate('info');
		$this->_html .= '';

		$this->_html .= '<div data-acc-tgt="ogone_prices" class="ogone_acc ogone_prices '.($hide_info_tabs ? 'ogone_hide' : '').'" style="background-image: url('._MODULE_DIR_.$this->name;
		$this->_html .= '/views/img/gift_ogone.png);background-repeat:no-repeat;background-position:8px center;">'.$this->l('Rates').'</div>';
		$this->_html .= $this->getTranslatedAdminTemplate('prices');
		$this->_html .= '</fieldset>';

		$this->_html .= '<form action="'.Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']).'" method="post">
			<div data-acc-tgt="ogone_config"  class="ogone_acc ogone_config" style="background-image: url('._MODULE_DIR_.$this->name;
		$this->_html .= '/views/img/conf_ogone.png);background-repeat:no-repeat;background-position:8px center;">'.$this->l('Configuration').'</div>
				<div class="ogone ogone_acc_container">
				<div id="ogone_config" class="ogone_acc_tgt">
				<div class="float half">
					<label for="pspid">'.$this->l('PSPID').'</label>
					<div class="margin-form">
						<input type="text" id="pspid" size="20" name="OGONE_PSPID" value="'.Tools::safeOutput(Tools::getValue('OGONE_PSPID',
							Configuration::get('OGONE_PSPID'))).'" />
					</div>
					<div class="clear">&nbsp;</div>
					<label for="sha-in">'.$this->l('SHA-in signature').'</label>
					<div class="margin-form">
						<input type="text" id="sha-in" size="20" name="OGONE_SHA_IN" value="'.Tools::safeOutput(Tools::getValue('OGONE_SHA_IN',
							Configuration::get('OGONE_SHA_IN'))).'" />
					</div>
					<div class="clear">&nbsp;</div>
					<label for="sha-out">'.$this->l('SHA-out signature').'</label>
					<div class="margin-form">
						<input type="text" id="sha-out" size="20" name="OGONE_SHA_OUT" value="'.Tools::safeOutput(Tools::getValue('OGONE_SHA_OUT',
							Configuration::get('OGONE_SHA_OUT'))).'" />
					</div>
					<div class="clear">&nbsp;</div>
					<label>'.$this->l('Mode').'</label>
					<div class="margin-form">
						<span style="display:block;float:left;margin-top:3px;"><input type="radio" id="test" name="OGONE_MODE" value="0" style="vertical-align:middle;
						display:block;float:left;margin-top:2px;margin-right:3px;"
							'.(!Tools::getValue('OGONE_MODE', Configuration::get('OGONE_MODE')) ? 'checked="checked"' : '').' />
						<label for="test" style="color:#900;display:block;float:left;text-align:left;width:60px;">'.$this->l('Test').'</label>&nbsp;</span>
						<span style="display:block;float:left;margin-top:3px;">
						<input type="radio" id="production" name="OGONE_MODE" value="1" style="vertical-align:middle;display:block;float:left; margin-top:2px;
							margin-right:3px;"
							'.(Tools::getValue('OGONE_MODE', Configuration::get('OGONE_MODE')) ? 'checked="checked"' : '').' />
						<label for="production" style="color:#080;display:block;float:left;text-align:left;width:85px;">'.$this->l('Production').'</label></span>
					</div>
					<div class="clear">&nbsp;</div>
					<label>'.$this->l('Operation').'</label>
					<div class="margin-form">
						<span style="display:block;float:left;margin-top:3px;"><input type="radio" id="sal" name="OGONE_OPERATION" value="'.self::OPERATION_SALE.'"
							style="vertical-align:middle;display:block;float:left;margin-top:2px;margin-right:3px;"
							'.(Tools::getValue('OGONE_OPERATION', Configuration::get('OGONE_OPERATION')) !== self::OPERATION_AUTHORISE ? 'checked="checked"' : '').' />
						<label for="test" style="display:block;float:left;text-align:left;width:60px;">'.$this->l('Capture').'</label>&nbsp;</span>
						<span style="display:block;float:left;margin-top:3px;">
						<span style="display:block;float:left;margin-top:3px;"><input type="radio" id="res" name="OGONE_OPERATION" value="'.self::OPERATION_AUTHORISE.'"
							style="vertical-align:middle;display:block;float:left;margin-top:2px;margin-right:3px;"
							'.(Tools::getValue('OGONE_OPERATION', Configuration::get('OGONE_OPERATION')) === self::OPERATION_AUTHORISE ? 'checked="checked"' : '').' />
						<label for="test" style="display:block;float:left;text-align:left;width:100px;">'.$this->l('Authorisation only').'</label>'.
						'<br />'.$this->l('Attention: this operation requires manual acceptation in Ingenico backoffice').
						'&nbsp;</span>
					</div>
					<div class="clear">&nbsp;</div>
					<input type="submit" name="submitOgone" value="'.$this->l('Update settings').'" class="button" />
				</div>
				<div class="float half">
				</div>

							</div>
							<div class="clear">&nbsp;</div>
							</div>
			</DIV>
		</form>
		<div class="clear">&nbsp;</div>
		<script type="text/javascript">
			$(document).ready(function() {
				$(".ogone_acc").click(function(){
					var tgt_id = "#"+ $(this).data("acc-tgt");
					$(".ogone_acc_tgt").not(tgt_id).hide().removeClass("active");
					$(tgt_id).toggle().addClass("active");
				});

				$(".ogone_acc").each(function(){
					if ($(this).hasClass("ogone_hide")){
					var tgt_id = "#"+ $(this).data("acc-tgt");
					$(tgt_id).hide().removeClass("active");
					}
				});

			});
		</script>';

		return $this->_html;

	}

	protected function getTranslatedAdminTemplate($template, $default_lang_iso_code = 'fr')
	{
		$template = Tools::strtolower($template);
		$codes = array_filter(array(Tools::strtolower(Context::getContext()->language->iso_code), Tools::strtolower($default_lang_iso_code)));
		foreach ($codes as $lang_iso_code)
		{
			if (file_exists(dirname(__FILE__).'/views/templates/admin/'.$template.'_'.$lang_iso_code.'.tpl'))
				return $this->display(__FILE__, 'views/templates/admin/'.$template.'_'.$lang_iso_code.'.tpl');
		}
		return '';
	}

	public function getIgnoreKeyList()
	{
		return $this->ignore_key_list;
	}

	public function getNeededKeyList()
	{
		return $this->needed_key_list;
	/*	$needed_vars = $this->needed_key_list;
		if (version_compare($this->version, '2.13', 'ge'))
			$needed_vars[] = 'ORIG';
		return $needed_vars;*/
	}

	/**
	 * Assigns all vars to smarty
	 * @param unknown_type $params
	 */
	protected function assignOgonePaymentVars($params)
	{
		$currency = new Currency((int)$params['cart']->id_currency);
		$lang = new Language((int)$params['cart']->id_lang);
		$customer = new Customer((int)$params['cart']->id_customer);
		$address = new Address((int)$params['cart']->id_address_invoice);
		$country = new Country((int)$address->id_country, (int)$params['cart']->id_lang);

		$ogone_params = array();
		$ogone_params['PSPID'] = Configuration::get('OGONE_PSPID');
		$ogone_params['OPERATION'] = (Configuration::get('OGONE_OPERATION') === self::OPERATION_AUTHORISE ?
			self::OPERATION_AUTHORISE :
			self::OPERATION_SALE);

		$ogone_params['ORDERID'] = pSQL($params['cart']->id);
		$ogone_params['AMOUNT'] = number_format((float)number_format($params['cart']->getOrderTotal(true, Cart::BOTH), 2, '.', ''), 2, '.', '') * 100;
		$ogone_params['CURRENCY'] = $currency->iso_code;
		$ogone_params['LANGUAGE'] = $lang->iso_code.'_'.Tools::strtoupper($lang->iso_code);
		$ogone_params['CN'] = $customer->lastname;
		$ogone_params['EMAIL'] = $customer->email;
		$ogone_params['OWNERZIP'] = $address->postcode;
		$ogone_params['OWNERADDRESS'] = ($address->address1);
		$ogone_params['OWNERCTY'] = $country->iso_code;
		$ogone_params['OWNERTOWN'] = $address->city;
		$ogone_params['PARAMPLUS'] = 'secure_key='.$params['cart']->secure_key;
		if (!empty($address->phone))
			$ogone_params['OWNERTELNO'] = $address->phone;

		ksort($ogone_params);
		$ogone_params['ORIG'] = Tools::substr('ORPR'.str_replace('.', '', $this->version), 0, 10);

		$ogone_params['SHASign'] = $this->calculateShaSign($ogone_params, Configuration::get('OGONE_SHA_IN'));

		$this->context->smarty->assign('ogone_params', $ogone_params);
		$this->context->smarty->assign('OGONE_MODE', Configuration::get('OGONE_MODE'));

	}

	/**
	 * hookPayment replacement for compatibility with module eu_legal
	 * @param array $params
	 * @return string Generated html
	 */
	public function hookDisplayPaymentEU($params)
	{
		$this->assignOgonePaymentVars($params);
		return array(
			'cta_text' => $this->l('Ogone'),
			'logo' => $this->_path.'views/img/ogone.gif',
			'form' => $this->context->smarty->fetch(dirname(__FILE__).'/views/templates/front/ogone_eu.tpl')
		);
	}

	public function hookPayment($params)
	{
		$this->assignOgonePaymentVars($params);
		$template = (version_compare(_PS_VERSION_, '1.6', 'ge') ? 'ogone16.tpl' : 'ogone.tpl');
		return $this->display(__FILE__, 'views/templates/front/'.$template);
	}

	public function hookOrderConfirmation($params)
	{
		if ($params['objOrder']->module != $this->name)
			return;
		if ($params['objOrder']->valid || (Configuration::get('OGONE_OPERATION') == self::OPERATION_AUTHORISE && (int)$params['objOrder']->current_state === (int)Configuration::get('OGONE_PAYMENT_AUTHORIZED') ))
			$this->context->smarty->assign(array('status' => 'ok', 'id_order' => $params['objOrder']->id));
		else
			$this->context->smarty->assign('status', 'failed');

		$this->context->smarty->assign('operation', Configuration::get('OGONE_OPERATION') ? Configuration::get('OGONE_OPERATION') : self::OPERATION_SALE);

		$link = method_exists('Link', 'getPageLink') ? $this->context->link->getPageLink('contact', true) : Tools::getHttpHost(true).'contact';
		$this->context->smarty->assign('ogone_link', $link);
		return $this->display(__FILE__, 'views/templates/hook/hookorderconfirmation.tpl');
	}

	public function validate($id_cart, $id_order_state, $amount, $message = '', $secure_key)
	{
		$this->validateOrder((int)$id_cart, $id_order_state, $amount, $this->displayName, $message, null, null, true, pSQL($secure_key));
	}

	/**
	 * Gets translated description of Ogone payment status, based on code. Defaults to "Unknown code: xxx"
	 * @param int $code
	 * @return string  Translated Ogone payment status description
	 */
	public function getCodeDescription($code)
	{
		return isset($this->return_codes[(int)$code]) ? $this->l($this->return_codes[(int)$code][0]) : sprintf('%s %s', $this->l('Unknown code'), $code);
	}

	/**
	 * Gets name of Ogone payment status, based on code. Defaults to self::PAYMENT_ERROR
	 * @param int $code See Ogone::$return_codes
	 * @return string Ogone payment status
	 */
	public function getCodePaymentStatus($code)
	{
		return isset($this->return_codes[(int)$code]) ? $this->return_codes[(int)$code][1] : self::PAYMENT_ERROR;
	}

	/**
	 * Gets id of Prestashop order status corresponding to Ogone status. Defaults to PS_OS_ERROR
	 * @param string $ogone_status name of Ogone return state
	 * @return int
	 */
	public function getPaymentStatusId($ogone_status)
	{
		$status_id = (int)Configuration::get((string)$ogone_status);
		return ($status_id ? $status_id : (int)Configuration::get('PS_OS_ERROR'));
	}

	/**
	 * Adds message to order
	 * @param int $id_order
	 * @param string $message
	 * @return boolean
	 */
	public function addMessage($id_order, $message)
	{
		if (!is_int($id_order) || $id_order <= 0)
			return false;
		if (!Validate::isCleanHtml($message))
			return false;

		$message_obj = new Message();
		$message_obj->id_order = $id_order;
		$message_obj->message = $message;
		$message_obj->private = true;

		return $message_obj->add();
	}

	public function calculateShaSign($ogone_params, $sha_key)
	{
		ksort($ogone_params);
		$shasign = '';
		foreach ($ogone_params as $key => $value)
			$shasign .= Tools::strtoupper($key).'='.$value.$sha_key;
		return Tools::strtoupper(sha1($shasign));
	}
}
