<?php
/**
* @version 1.0.0
* @package RSMembership! Zarinpal Webgate
* @copyright (C) www.zarinpal.com
* @license GPL, http://www.gnu.org/licenses/gpl-2.0.html
*/
error_reporting(0);
defined('_JEXEC') or die('Restricted access');

jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.html.parameter' );

if (file_exists(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_rsmembership'.DS.'helpers'.DS.'rsmembership.php'))
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_rsmembership'.DS.'helpers'.DS.'rsmembership.php');

class plgSystemRSMembershipZarinpal extends JPlugin
{
	function canRun()
	{
		return file_exists(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_rsmembership'.DS.'helpers'.DS.'rsmembership.php');
	}

	function plgSystemRSMembershipZarinpal(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_plugin =& JPluginHelper::getPlugin('system', 'rsmembershipzarinpal');
		$this->_params = new JParameter($this->_plugin->params);

		if (!$this->canRun()) return;
		RSMembership::addPlugin('دروازه پرداخت Zarinpal وب گیت', 'rsmembershipzarinpal');
	}
	public function onAfterRender()
	{
	        if($_GET['Status'] == "OK"){
	            $this->onPaymentNotification();
	        }
	}

	function onMembershipPayment($plugin, $data, $extra, $membership, $transaction)
	{
		if (!$this->canRun()) return;
		if ($plugin != $this->_plugin->name) return false;
		
		$desc = 'پرداخت مربوط به کاربر : ' . $transaction->user_email;
	    	$api = $this->_params->get('api');
	        $amount = $transaction->price;
	
	        $redirect = JURI::base() . 'index.php?option=com_rsmembership&task=thankyou';
	
	        $result = $this->send($desc, $api, $amount, $redirect);
	        if ($result->Status == 100) {
	            $go = 'https://www.zarinpal.com/pg/StartPay/' . $result->Authority;
	            $transaction->custom = $result->Status;
	            $html = 'در حال وصل شدن به درگاه...<META http-equiv="refresh" content="3;URL=' . $go . '">';
	        } else {
	            $html = 'در ارتباط با درگاه خطايي به وجود آمد :' . $result->Status;
	        }

		return $html;
	}
	public function onPaymentNotification()
	{
	        if ( !$this->canRun( ) ) {
	        } else {
			$database = JFactory::getDBO();;
			$api = $this->_params->get('api');
			$status = $_GET['Status'];
			$au = $_GET['Authority'];
			$amount = $transaction->price;
	
			$result = $this->get($api,$au,$amount);
			$database->setQuery( "SELECT * FROM #__rsmembership_transactions WHERE `custom`='".$au."' AND `status`!='1' AND `gateway`='دروازه پرداخت Zarinpal'" );
			$transaction = $database->loadObject( );
			if (empty( $transaction )) {
				echo 'اطلاعاتي در رباطه با درخواست شما يافت نشد';
			} else {
	
				if ($result->Status == 100) {
					RSMembership::approve($transaction->id);
					$database->setQuery( "UPDATE #__rsmembership_transactions SET `hash`='".$result->RefID."' WHERE `id`='".$transaction->id."' LIMIT 1" );
					$database->query();
					echo 'پرداخت شما با موفقيت صورت گرفت';
				} else {
					echo "مشکلي در پرداخت شما صورت گرفت ، شماره خطا:". $result->Status;
				}
						
			}
	
	        }
	}
	
	public function send($desc, $api, $amount, $redirect) {
		$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
		$res = $client->PaymentRequest(array(
			'MerchantID' 	=> $api,
			'Amount' 	=> $amount,
			'Description' 	=> $desc,
			'Email' 	=> '',
			'Mobile' 	=> '',
			'CallbackURL' 	=> $redirect
		));
	        return $res;
	}

	public function get($api,$au,$amount){
		$client = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
		$res = $client->PaymentVerification(array(
			'MerchantID'	 => $api,
			'Authority' 	 => $au,
			'Amount'	 => $amount
		));
		return $res;   
	}

}
