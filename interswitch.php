<?php
/**
 * @package		akeebasubs
 * @copyright	Copyright (c)2010-2013 Nicholas K. Dionysopoulos / AkeebaBackup.com
 * @license		GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die();

$akpaymentinclude = include_once JPATH_ADMINISTRATOR.'/components/com_akeebasubs/assets/akpayment.php';
if(!$akpaymentinclude) { unset($akpaymentinclude); return; } else { unset($akpaymentinclude); }

class plgAkpaymentPaypal extends plgAkpaymentAbstract
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
			'amount' =>$subscription->net_amount,
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
	 * Gets the form action URL for the payment
	 */
	private function getPaymentURL()
	{
		$testmode = $this->params->get('testmode',0);
		if($testmode) {
			return 'interswitch test url';
		} else {
			return 'interswitch live';
		}
	}

	/**
	 * Gets the interswitch Product ID (provided by interswitch)
	 */
	private function getProductID()
	{
		$testmode = $this->params->get('testmode',0);
		if($testmode) {
			return $this->params->get('testmode_pid','');
		} else {
			return $this->params->get('real_pid','');
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
		$testmode = $this->params->get('testmode',0);
		if($testmode) {
			return $this->params->get('testmode_payid','');
		} else {
			return $this->params->get('real_payid','');
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
			return $this->params->get('real_mackey','');
		}
	}

	/**
	 * Validates the incoming data against PayPal's IPN to make sure this is not a
	 * fraudelent request.
	 */
	private function isValidIPN(&$data)
	{
		$sandbox = $this->params->get('sandbox',0);
		$hostname = $sandbox ? 'www.sandbox.paypal.com' : 'www.paypal.com';

		$url = 'ssl://'.$hostname;
		$port = 443;

		$req = 'cmd=_notify-validate';
		foreach($data as $key => $value) {
			$value = urlencode($value);
			$req .= "&$key=$value";
		}
		$header = '';
		$header .= "POST /cgi-bin/webscr HTTP/1.1\r\n";
		$header .= "Host: $hostname:$port\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n";
		$header .= "Connection: Close\r\n\r\n";


		$fp = fsockopen ($url, $port, $errno, $errstr, 30);

		if (!$fp) {
			// HTTP ERROR
			$data['akeebasubs_ipncheck_failure'] = 'Could not open SSL connection to ' . $hostname . ':' . $port;
			return false;
		} else {
			fputs ($fp, $header . $req);
			while (!feof($fp)) {
				$res = fgets ($fp, 1024);
				if (stristr($res, "VERIFIED")) {
					return true;
				} else if (stristr($res, "INVALID")) {
					$data['akeebasubs_ipncheck_failure'] = 'Connected to ' . $hostname . ':' . $port . '; returned data was "INVALID"';
					return false;
				}
			}
			fclose ($fp);
		}
	}

	private function _toPPDuration($days)
	{
		$ret = (object)array(
			'unit'		=> 'D',
			'value'		=> $days
		);

		// 0-90 => return days
		if($days < 90) return $ret;

		// Translate to weeks, months and years
		$weeks = (int)($days / 7);
		$months = (int)($days / 30);
		$years = (int)($days / 365);

		// Find which one is the closest match
		$deltaW = abs($days - $weeks*7);
		$deltaM = abs($days - $months*30);
		$deltaY = abs($days - $years*365);
		$minDelta = min($deltaW, $deltaM, $deltaY);

		// Counting weeks gives a better approximation
		if($minDelta == $deltaW) {
			$ret->unit = 'W';
			$ret->value = $weeks;

			// Make sure we have 1-52 weeks, otherwise go for a months or years
			if(($ret->value > 0) && ($ret->value <= 52)) {
				return $ret;
			} else {
				$minDelta = min($deltaM, $deltaY);
			}
		}

		// Counting months gives a better approximation
		if($minDelta == $deltaM) {
			$ret->unit = 'M';
			$ret->value = $months;

			// Make sure we have 1-24 month, otherwise go for years
			if(($ret->value > 0) && ($ret->value <= 24)) {
				return $ret;
			} else {
				$minDelta = min($deltaM, $deltaY);
			}
		}

		// If we're here, we're better off translating to years
		$ret->unit = 'Y';
		$ret->value = $years;

		if($ret->value < 0) {
			// Too short? Make it 1 (should never happen)
			$ret->value = 1;
		} elseif($ret->value > 5) {
			// One major pitfall. You can't have renewal periods over 5 years.
			$ret->value = 5;
		}

		return $ret;
	}
}
