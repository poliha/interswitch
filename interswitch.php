<?php
/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2013 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

$akpaymentinclude = include_once JPATH_ADMINISTRATOR.'/components/com_akeebasubs/assets/akpayment.php';
if(!$akpaymentinclude) { unset($akpaymentinclude); return; } else { unset($akpaymentinclude); }

class plgAkpaymentInterswitch extends plgAkpaymentAbstract
{
	public function __construct(&$subject, $config = array())
	{
		$config = array_merge($config, array(
			'ppName'		=> 'interswitch',
			'ppKey'			=> 'PLG_AKPAYMENT_INTERSWITCH_TITLE',
			'ppImage'		=> 'TO BE ADDED'
		));

		parent::__construct($subject, $config);
	}

	/**
	 * Returns the payment form to be submitted by the user's browser. The form must have an ID of
	 * "paymentForm" and a visible submit button.
	 *
	 * @param string $paymentmethod
	 * @param JUser $user
	 * @param AkeebasubsTableLevel $level
	 * @param AkeebasubsTableSubscription $subscription
	 * @return string
	 */
	public function onAKPaymentNew($paymentmethod, $user, $level, $subscription)
	{
		if($paymentmethod != $this->ppName) return false;

		/*explode name into name and surname*/
		$nameParts = explode(' ', $user->name, 2);
		$firstName = $nameParts[0];
		if(count($nameParts) > 1) {
			$lastName = $nameParts[1];
		} else {
			$lastName = '';
		}

		$slug = FOFModel::getTmpInstance('Levels','AkeebasubsModel')
				->setId($subscription->akeebasubs_level_id)
				->getItem()
				->slug;

		$rootURL = rtrim(JURI::base(),'/');
		$subpathURL = JURI::base(true);
		if(!empty($subpathURL) && ($subpathURL != '/')) {
			$rootURL = substr($rootURL, 0, -1 * strlen($subpathURL));
		}
		
		$tx_ref = $this->getTxnRef();
		$site_redirect = JURI::base().'index.php?option=com_akeebasubs&view=callback&paymentmethod=interswitch';
		$tx_hash = $this->getHash($tx_ref, $site_redirect);
		
		$data = (object)array(
			'payment_url' =>$this->getPaymentURL(),
			'product_id' =>$this->getProductID(),
			'amount' =>$subscription->net_amount, //TO DO:check if net amount is the correct value
			'currency' =>$this->getCurrency(),
			'site_redirect_url' => $site_redirect,
			'txn_ref' =>$tx_ref,
			'hash' =>$tx_hash,
			'pay_item_id' =>$this->getPayItemID(),
			
		
		);

		//REMEBER TO STORE TO DATABASE
		//$kuser = FOFModel::getTmpInstance('Users','AkeebasubsModel')->user_id($user->id)->getFirstItem();
		
		@ob_start();
		include dirname(__FILE__).'/interswitch/form.php';
		$html = @ob_get_clean();

		return $html;
	}




	
	/**
	 * callback function
	 */
	public function onAKPaymentCallback($paymentmethod, $data)
	{
		JLoader::import('joomla.utilities.date');

		// Check if we're supposed to handle this
		if($paymentmethod != $this->ppName) return false;
		
		
		//prepare transaction query for WebPay
		$webPayUrl = $this->getWebPayUrl();
		$tx_query_str = "?transactionreference=" . $data->txn_ref;
		$tx_query_str .= "&productid=". $data->product_id;
		$tx_query_str .= "&amount=". $data->amount;
		//$tx_query_str .= "&hash=". $data->hash;
	    $webPayUrl .=$tx_query_str;
	    
	    //query transaction on WebPay
	    $ch = curl_init($webPayUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true);

		$tx_query_resp = curl_exec($ch);
		curl_close($ch);
		
		$tx_output = json_decode(tx_query_resp, true);
		print_r($tx_output);
		
	}
	
	/**
	 * Gets the form action URL for the payment
	 */
	private function getPaymentURL()
	{
		$testmode = $this->params->get('testmode');
		if($testmode) {
			return $this->params->get('testmode_url');
		} else {
			return $this->params->get('livemode_url');
		}
	}

	/**
	 * Gets the interswitch Product ID (provided by interswitch)
	 */
	private function getProductID()
	{
		$testmode = $this->params->get('testmode');
		if($testmode) {
			return $this->params->get('testmode_pid');
		} else {
			return $this->params->get('livemode_pid');
		}
	}
	
	/**
	 * Gets the interswitch Defined currency code (usually 566 for naira)
	 */
	private function getCurrency()
	{
		return $this->params->get('currency','');
	}

	
	/**
	 * Gets the unique Tx ref for interswitch 
	 */
	private function getTxnRef()
	{
		$ref = strtotime ("now").substr(str_replace(".", "",$_SERVER['REMOTE_ADDR']),0,4);
		return $ref;
		 
	}
	
	/**
	 * Gets the unique hash for the transaction
	 */
	private function getHash($tx_ref, $site_redirect)
	{
		
		 
        $hashstr = $ref .$this->getProductID().$this->getPayItemID(). 
					$subscription->net_amount.$site_redirect.$this->getMacKey();
        return hash("sha512", $hashstr);
	}
	
	
	/**
	 * Gets the interswitch Pay Item ID (provided by interswitch)
	 */
	private function getPayItemID()
	{
		$testmode = $this->params->get('testmode');
		if($testmode) {
			return $this->params->get('testmode_payid');
		} else {
			return $this->params->get('livemode_payid');
		}
	}
	
	
	/**
	 * Gets the interswitch Mac Key (provided by interswitch)
	 */
	private function getMacKey()
	{
		$testmode = $this->params->get('testmode',0);
		if($testmode) {
			return $this->params->get('testmode_mackey','');
		} else {
			return $this->params->get('livemode_mackey','');
		}
	}

}
