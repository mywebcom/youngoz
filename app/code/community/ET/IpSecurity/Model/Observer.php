<?php
/**
 * ET Web Solutions
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   ET
 * @package    ET_IpSecurity
 * @copyright  Copyright (c) 2010 ET Web Solutions (http://etwebsolutions.com)
 * @contacts   support@etwebsolutions.com
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class ET_IpSecurity_Model_Observer
{
	private $redirect_page = null;
	private $redirect_blank = null;
	private $raw_allow_ip_data = null;
	private $raw_block_ip_data = null;
	private $raw_except_ip_data = null;
	private $event_email = "";
	private $email_template = 0;
	private $email_identity = null;
	private $storetype = null;
	private $last_found_ip = null;
	private $isFrontend = false;
	private $alwaysNotify = false;


	public function __construct()
	{
	}

	public function apply_ip_check_frontend($observer)
	{
		$this->redirect_page = $this->trim_slashes(Mage::getStoreConfig('etipsecurity/ipsecurityfront/redirect_page'));
		$this->redirect_blank = Mage::getStoreConfig('etipsecurity/ipsecurityfront/redirect_blank');
		$this->raw_allow_ip_data = Mage::getStoreConfig('etipsecurity/ipsecurityfront/allow');
		$this->raw_block_ip_data = Mage::getStoreConfig('etipsecurity/ipsecurityfront/block');

		$this->raw_except_ip_data = Mage::getStoreConfig('etipsecurity/ipsecuritymaintetance/except');

		$this->event_email = Mage::getStoreConfig('etipsecurity/ipsecurityfront/email_event');
		$this->email_template = Mage::getStoreConfig('etipsecurity/ipsecurityfront/email_template');
		$this->email_identity = Mage::getStoreConfig('etipsecurity/ipsecurityfront/email_identity');
		$this->storetype = Mage::helper("catalog")->__("Frontend");
		$this->isFrontend = true;
		$this->alwaysNotify = Mage::getStoreConfig('etipsecurity/ipsecurityfront/email_always');
		$this->apply_ip_check($observer);
	}

	public function apply_ip_check_admin($observer)
	{
		$this->redirect_page = $this->trim_slashes(Mage::getStoreConfig('etipsecurity/ipsecurityadmin/redirect_page'));
		$this->redirect_blank = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/redirect_blank');
		$this->raw_allow_ip_data = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/allow');
		$this->raw_block_ip_data = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/block');
		$this->event_email = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/email_event');
		$this->email_template = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/email_template');
		$this->email_identity = Mage::getStoreConfig('etipsecurity/ipsecurityadmin/email_identity');
		$this->storetype = Mage::helper("core")->__("Admin");
		$this->isFrontend = false;
		$this->alwaysNotify = Mage::getStoreConfig('etipsecurity/ipsecurityfront/alwaysnotify');
		$this->apply_ip_check($observer);
	}

	public function apply_ip_check($observer)
	{
		$current_ip = $_SERVER['REMOTE_ADDR'];
		# start by allow all
		$allow = true;
		$allow_ips = null;
		$block_ips = null;
		$except_ips = null;
		$current_page = $this->trim_slashes(Mage::helper('core/url')->getCurrentUrl());

		// searching for CMS page storeId
		// if we don't do it - we have loop in redirect with setting Add Store Code to Urls = Yes (block access to admin redirects to admin)
		$stores       = array();
		$pageStoreIds = array();

		foreach (Mage::app()->getStores() as $store)
		{
			$stores[] = $store->getId();
			$pageId = Mage::getModel('cms/page')->checkIdentifier($this->redirect_page,$store->getId());
			if($pageId===false) continue;
			$pageStoreIds = Mage::getResourceModel('cms/page')->lookupStoreIds($pageId);
			if (count($pageStoreIds)) // found page
				break;
		}

		if (!count($pageStoreIds)) // no found in any store
			$pageStoreIds[] = 0;

		foreach ($pageStoreIds as $pageStoreId)
		{
			if ($pageStoreId > 0)
				break;
		}
		if ($pageStoreId == 0)
			$pageStoreId = $stores[0]; // first available store

		$this->redirect_page = $this->trim_slashes(Mage::app()->getStore($pageStoreId)->getBaseUrl())."/".$this->redirect_page;
		$neednotify = true;
		$scope = $this->isFrontend?'frontend':'admin';

		if(strlen($this->redirect_page)){$this->trim_slashes(Mage::getUrl('no-route'));}

		$allow_ips = $this->_ip_raw_to_array($this->raw_allow_ip_data);
		$block_ips = $this->_ip_raw_to_array($this->raw_block_ip_data);
		$except_ips = $this->_ip_raw_to_array($this->raw_except_ip_data);

		# look for allowed
		if(strlen(trim($this->raw_allow_ip_data)) > 0)
		{
			# block all except allowed
			$allow = false;

			# are there any allowed ips
			if($this->find_ip($current_ip,$allow_ips))
			{
				$allow = true;
			}
		}
		# look for blocked
		if(strlen(trim($this->raw_block_ip_data)) > 0)
		{
			# are there any allowed ips
			if($this->find_ip($current_ip,$block_ips))
			{
				$allow = false;
			}
		}

		if($this->redirect_blank==1 && !$allow)
		{
			header("HTTP/1.1 403 Forbidden");
			header("Status: 403 Forbidden");
			header("Content-type: text/html");
			$neednotify = $this->saveToLog(array('blocked_from' => $scope, 'blocked_ip'  => $current_ip ));
			if(($this->alwaysNotify) || $neednotify)
				$this->_send();
			exit("Access denied for IP:<b> ".$current_ip."</b>");
		}

		if($current_page!=$this->redirect_page && !$allow)
		{
			header('Location: '.$this->redirect_page);
			$neednotify = $this->saveToLog(array('blocked_from' => $scope, 'blocked_ip'  => $current_ip ));
			if(($this->alwaysNotify) || $neednotify)
				$this->_send();
			exit();
		}

		$maintenancemode = Mage::getStoreConfig('etipsecurity/ipsecuritymaintetance/enabled');
		if (($maintenancemode) && ($this->isFrontend))
		{
			$dontloadsite = true;
			# look for except
			if(strlen(trim($this->raw_except_ip_data)) > 0)
			{
				# are there any except ips
				if($this->find_ip($current_ip,$except_ips))
				{
					Mage::app()->getResponse()->appendBody(html_entity_decode( Mage::getStoreConfig('etipsecurity/ipsecuritymaintetance/remindermessage') , ENT_QUOTES, "utf-8"));
					$dontloadsite = false;
				}
			}

			if ($dontloadsite)
			{
				header('HTTP/1.1 503 Service Temporarily Unavailable');
				header('Status: 503 Service Temporarily Unavailable');
				header('Retry-After: 7200'); // in seconds
				print html_entity_decode( Mage::getStoreConfig('etipsecurity/ipsecuritymaintetance/message'), ENT_QUOTES, "utf-8");
				exit();
			}

		}


		return $this;
	}


	private function _findByRange($allIp)
	{

	}

	private function find_ip($search_ip,$array)
	{
		$found = false;
		if(count($array)>0)
		{
			foreach($array as $iptxt)
			{
				$ip=explode("|",$iptxt);
				$ip=trim($ip[0]);
				$ipRange=explode("-",$ip);
				//range

				if(count($ipRange)==2)
				{

					$ipFrom=explode(".",trim($ipRange[0]));
					$ipFrom=sprintf("%03d%03d%03d%03d",$ipFrom[0],$ipFrom[1],$ipFrom[2],$ipFrom[3]);
					$ipTo=explode(".",trim($ipRange[1]));
					$ipTo=sprintf("%03d%03d%03d%03d",$ipTo[0],$ipTo[1],$ipTo[2],$ipTo[3]);
					$ipCurrent=explode(".",$search_ip);
					$ipCurrent=sprintf("%03d%03d%03d%03d",$ipCurrent[0],$ipCurrent[1],$ipCurrent[2],$ipCurrent[3]);
					if((strcmp($ipFrom,$ipCurrent)<=0)&(strcmp($ipCurrent,$ipTo)<=0)){
						$found = true;
						$this->last_found_ip=$iptxt;
						return $found;
					}

				}
				//simple
				else if(preg_match('/^'.str_replace(array('\*','\?'), array('(.*?)','[0-9]'), preg_quote($ip)).'$/',$search_ip))
				{
					$found = true;
					$this->last_found_ip=$iptxt;
					return $found;
				}
			}
		}
		return $found;
	}

	private function trim_slashes($str)
	{
		$str = trim($str);
		return $str == '/' ? $str : rtrim($str, '/');
	}

	private function _send()
	{
		if(!$this->event_email)return ;
		$current_ip = $_SERVER['REMOTE_ADDR'];
		$storeId = 0;//admin

		$recipients = explode(",",$this->event_email);

		$emailTemplate = Mage::getModel('core/email_template');
		foreach($recipients as $k => $recipient)
			$sendresult = $emailTemplate->setDesignConfig(array('area'  => 'backend'))
							->sendTransactional(
								$this->email_template,
								$this->email_identity,
								trim($recipient),
								trim($recipient),
								array(
										'ip'  => $current_ip,
										'ip_rule'  => $this->last_found_ip,
										'date'  => Mage::app()->getLocale()->date(date("Y-m-d H:i:s"), Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM), null,true),//->toString('yyyy-MM-dd HH:ii:ss')
										'storetype'=>$this->storetype,
										'info'=>base64_encode(serialize(array($this->raw_allow_ip_data,$this->raw_block_ip_data))),
									)
							);

	}

	private function _ip_raw_to_array($text)
	{
		$ips = preg_split("/[\n\r]+/", $text);
		foreach($ips as $ipsk=>$ipsv)
			if(trim($ipsv)=="")unset($ips[$ipsk]);
		return $ips;
	}


	protected function saveToLog($params = array())
	{
		$neednotify = true;

		if (!((isset($params['blocked_ip'])) && (strlen(trim($params['blocked_ip']))>0)))
			$params['blocked_ip'] = $_SERVER['REMOTE_ADDR'];

		if (!((isset($params['blocked_from'])) && (strlen(trim($params['blocked_from']))>0)))
			$params['blocked_from'] = 'undefined';

		$now = now();

		$logtable = Mage::getModel('etipsecurity/ipsecuritylog')->getCollection();
		$logtable->getSelect()->where('blocked_from=?',$params['blocked_from'])->where('blocked_ip=?',$params['blocked_ip']);

		if (count($logtable)>0)
		{
			foreach($logtable as $row)
			{
				$timesBlocked = $row->getData('qty') + 1;
				$row->setData('qty', $timesBlocked);
				$row->setData('update_time', $now);
				$row->save();
				if (($timesBlocked%10) == 0)
					$neednotify = true;
				else
					$neednotify = false;
			}
		}
		else
		{
			$log = Mage::getModel('etipsecurity/ipsecuritylog');

			$log->setData('blocked_from', $params['blocked_from']);
			$log->setData('blocked_ip', $params['blocked_ip']);
			$log->setData('qty', '1');
			$log->setData('create_time', $now);
			$log->setData('update_time', $now);

			$log->save();
			$neednotify = true;
		}

		// if returns true - IP blocked for first time or timesBloked is 10, 20, 30 etc.
		return $neednotify;
	}



}