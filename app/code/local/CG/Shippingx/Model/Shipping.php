<?php
class CG_Shippingx_Model_Shipping
	extends Mage_Shipping_Model_Shipping
{
	public function getCarrierByCode($carrierCode, $storeId = null)
    {
    	if (!Mage::getStoreConfigFlag('carriers/'.$carrierCode.'/active', $storeId)) {
            return false;
        }
        $className = Mage::getStoreConfig('carriers/'.$carrierCode.'/model', $storeId);
        if (!$className) {
            return false;
            #Mage::throwException('Invalid carrier: '.$carrierCode);
        }
        $obj = Mage::getModel($className);
        if ($storeId) {
            $obj->setStore($storeId);
        }
        return $obj;
    }
}
?>