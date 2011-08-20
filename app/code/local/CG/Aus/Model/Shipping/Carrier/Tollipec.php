<?php
class CG_Aus_Model_Shipping_Carrier_Tollipec 
	extends Mage_Shipping_Model_Carrier_Abstract 
{
	protected $_code = "tollipec";
	
	protected $_request;
	
	public function collectRates(Mage_Shipping_Model_Rate_Request $request)
	{
		if (!$this->getConfigFlag('active')) {
            return false;
        }
        
        //handling fee
        $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling_fee');
        //surcharge rate
        $surcharge = Mage::getStoreConfig('carriers/'.$this->_code.'/surcharge_rate');
        //get postcode
        $postcode = $request->getDestPostcode();
		$totalWeight = array();
		
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
				$location = Mage::getModel('catalog/product')->load($item->getProduct()->getId())->getAttributeText('stock_location');
				if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }
				
                if(!array_key_exists($location, $totalWeight)){
                	$totalWeight[$location] = 0;
                }
                $totalWeight[$location] += $item->getWeight()*$item->getQty();
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
	        $method->setCarrier('tollipec');
	        $method->setCarrierTitle($this->getConfigData('title'));
	        $method->setMethod('tollipec');
	        $method->setMethodTitle($this->getConfigData('name'));
	            
	        $method->setPrice($shippingPrice);
	        $method->setCost($shippingPrice);
	        $result->append($method);
        }catch(Exception $e){
        	//standard result object
        	$result = Mage::getModel('shipping/rate_result');
        	$error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier('tollipec');
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
		$shippingPrice = 0;
		$shippingPriceGstIncluded = $handling;
		$dst = $this->getZone($postcode);
		foreach($totalWeight as $orig => $weight){
			//if item ship from mel to mel or syd to syd, use local calculation method
			if(($dst == 'MEL' || $dst == 'SYD') && $dst == $orig){
				switch($dst){
					case 'MEL':
						if($weight < 25){
							$shippingPrice += 6.17;
						}
						if($weight >= 25 && $weight < 50){
							$shippingPrice += 12.33;
						}
						if($weight >= 50 && $weight <75){
							$shippingPrice += 18.49;
						}
						if($weight >=75 && $weight < 100){
							$shippingPrice += 24.65;
						}						
						break;
					case 'SYD':
						if($weight < 25){
							$shippingPrice += 5.5;
						}
						if($weight >= 25 && $weight < 50){
							$shippingPrice += 11;
						}
						if($weight >= 50 && $weight <75){
							$shippingPrice += 16.5;
						}
						if($weight >=75 && $weight < 100){
							$shippingPrice += 22;
						}
						break;
					default:
						break;
				}
			}else{
				$rs = $this->getChargeInfo($orig,$dst);
				$shippingPrice += ($weight*$rs['charge_per_kg'] + $rs['basic_charge'])*(1+$surcharge/100);
			}
		}
		return round($shippingPrice*1.1 + $shippingPriceGstIncluded,2);
	}

	/**
	 * 
	 * get dst zone name by postcode
	 * @param string $postcode
	 */
	protected function getZone($postcode)
	{
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->fetchRow("SELECT zone FROM shipping_tollipec_zone WHERE start_code =".$postcode." OR (start_code <=".$postcode." AND end_code >=".$postcode.")");
		if($rs)
			return $rs['zone'];
		else
			throw new Exception('No zone found in database');
	}
	
	/**
	 * 
	 * get basic charge & kg charge
	 * @string $orig
	 * @string $dst
	 */
	protected function getChargeInfo($orig,$dst)
	{
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$rs = $db->fetchRow("SELECT basic_charge, charge_per_kg FROM shipping_tollipec_postage WHERE orig_location='".$orig."' AND dst_location='".$dst."'");
		if($rs)
			return $rs;
		else 
			throw new Exception($dst.' not supported for toll ipec');
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