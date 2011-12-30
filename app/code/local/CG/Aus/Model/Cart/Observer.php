<?php
class CG_Aus_Model_Cart_Observer extends Mage_Core_Model_Abstract
{
    public function setAddToCartAfter($observer)
    {
        $event = $observer->getEvent();
        $data = $event->getData();
        $quote = $data['quote_item'];
        if($quote->getQty() == 1){
        	$message = 'Combined postage for multiple items  ?  - Save 50%  on 2nd item onwards ! ';
        	Mage::getSingleton('core/session')->addNotice($message);
        }
    }
}
