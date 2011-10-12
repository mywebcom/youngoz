<?php
class CG_Aus_Model_Shipping_Carrier_Tnt
	extends Mage_Shipping_Model_Carrier_Abstract 
{
	protected $_code = "tnt";
	
	protected $_request;
	
	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		if (!$this->getConfigFlag('active')) {
            return false;
        }
        
        //handling fee
        $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling_fee');
        //surcharge rate
        $surcharge = Mage::getStoreConfig('carriers/'.$this->_code.'/surcharge');
        //get postcode
        $postcode = $request->getDestPostcode();
		$totalWeight = array();
		
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
				$location = Mage::getModel('catalog/product')->load($item->getProduct()->getId())->getAttributeText('stock_location');
				if($location == 'MEL'){
					$location = 'VV1';
				}else if($location == 'SYD'){
					$location = 'NN1';
				}else{
					return false;
				}
				
				if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }
				
                if(!array_key_exists($location, $totalWeight)){
                	$totalWeight[$location]= array();
                }
                $totalWeight[$location][] = $item;
            }
        }
        try {
			$shippingPrice = $this->getQuote($postcode,$handling,$surcharge,$totalWeight);
	       	//standard result object
	        $result = Mage::getModel('shipping/rate_result');
	        
	        //create new shipping method
	        $method = Mage::getModel('shipping/rate_result_method');
	
	        /**
	         * set carrier information
	         * name has to be lowercase 
	         */ 
	        $method->setCarrier('tnt');
	        $method->setCarrierTitle($this->getConfigData('title'));
	        $method->setMethod('tnt');
	        $method->setMethodTitle($this->getConfigData('name'));
	            
	        $method->setPrice($shippingPrice);
	        $method->setCost($shippingPrice);
	        $result->append($method);
        }catch(Exception $e){
        	//standard result object
        	$result = Mage::getModel('shipping/rate_result');
        	$error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier('tnt');
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($error->getErrorMessage());
            $result->append($error);
        }
        return $result;
	}
	
	/**
	 * 
	 * get postage quote
	 * @string $postcode
	 * @float $handling
	 * @int $surcharge
	 * @array $totalWeight
	 */
	protected function getQuote($postcode,$handling,$surcharge,$totalWeight)
	{
		$surcharge = $surcharge/100;
		$shippingPrice = 0;
		$shippingPriceGstIncluded = $handling;
		$dst = $this->getZone($postcode);
		$max = 0;
		foreach($totalWeight as $orig => $items){
			$rate = $this->getRate($orig, $dst);
			$rs = $this->getChargeInfo($rate);
			$basic_charge = $rs['basic_charge'];
			$charge_per_kg = $rs['charge_per_kg'];
			$charge_per_kg_tas = $rs['charge_per_kg_tas'];
			foreach($items as $item){
				$weight = $item->getWeight()*$item->getQty();
				if($weight > 10){
					$extra_kg =  $weight - 10;
					if($dst == 'TA1' || $dst == 'TA2'){
						$charge_rate = $charge_per_kg_tas;
					}else{
						$charge_rate = $charge_per_kg;
					}
					$cost = $basic_charge + ($extra_kg*$charge_rate);
				}else{
					$cost = $basic_charge;
				}
				//test max
				if($cost > $max){
					$max = $cost;
					$maxItem = $item;
				}
				$shippingPrice += $cost;
			}
		}
		//divide to 2
		$shippingPrice = ($shippingPrice - ($max/$maxItem->getQty()))/2 + $max/$maxItem->getQty();
		return round($shippingPrice*(1+$surcharge)*1.1 + $shippingPriceGstIncluded,2);
	}
	
	/**
	 * 
	 * get charge rate
	 * @param string $orig
	 * @param string $dst
	 */
	protected function getRate($orig,$dst)
	{
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->fetchRow("SELECT rate FROM shipping_tnt_roadexpress_rate WHERE origin ='".$orig."' AND dst='".$dst."'"); 
		if($rs)
			return $rs['rate'];
		else
			throw new Exception('No rate found in database');
	}

	/**
	 * 
	 * get dst zone name by postcode
	 * @param string $postcode
	 */
	protected function getZone($postcode)
	{
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$total = $db->fetchAll("SELECT * FROM shipping_tnt_roadexpress_zone");
		$rs = false;
		//@todo select from db directly
		foreach($total as $entry){
			if($entry['start_code'] == $postcode || ($entry['start_code'] <= $postcode && $entry['end_code'] >= $postcode))
			{
				$rs['zone'] = $entry['zone'];
				break;
			}
		}
		if($rs)
			return $rs['zone'];
		else
			throw new Exception('No zone found in database');
	}
	
	/**
	 * 
	 * get basic charge & kg charge
	 * @param int $orig
	 */
	protected function getChargeInfo($rate)
	{
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$total = $db->fetchAll("SELECT * FROM shipping_tnt_roadexpress_postage");
		$rs = false;
		//@todo select from db directly
		//$rs = $db->fetchRow("SELECT basic_charge, charge_per_kg, charge_per_kg_tas FROM shipping_tnt_roadexpress_postage WHERE rate_value=".$rate);
		foreach($total as $entry){
			if($entry['rate_value'] == $rate)
			{
				$rs = $entry;
				break;
			}
		}
		if($rs)
			return $rs;
		else 
			throw new Exception($rate.' not supported for tnt road express.');
	}
	
	/**
	 * @see app/code/core/Mage/Shipping/Model/Carrier/Mage_Shipping_Model_Carrier_Abstract::isTrackingAvailable()
	 */
	public function isTrackingAvailable()
	{
		return true;
	}
	
	/**
	 * get tracking infomation
	 * @param int $trackingNumber
	 */
	public function getTrackingInfo($trackingNumber)
	{
		return array('title'=>$this->getConfigData('title'),'number'=>$trackingNumber);
	}
}
?>