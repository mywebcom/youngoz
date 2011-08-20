<?php
class CG_Shippingx_ajaxController
	extends Mage_Core_Controller_Front_Action
{
	public function estimateAction()
	{
		$pid = $this->_request->getParam('pid');
		$qty = $this->_request->getParam('qty');
		$postcode = $this->_request->getParam('zip');
		
		$quote = Mage::getModel('sales/quote');
		
		$quote->getShippingAddress()
			  ->setCountryId('AU')
			  ->setPostcode($postcode)
			  ->setCollectShippingRates(true);
			  
		$product = Mage::getSingleton('catalog/product')->load($pid);
		$quote->addProduct($product);
		
		$quote->getShippingAddress()->setCollectShippingRates(true);
		$quote->getShippingAddress()->collectTotals();
		$rates = $quote->getShippingAddress()->getShippingRatesCollection();
		$cheapest = 100000;
		foreach($rates as $rate){
			if($rate->getPrice() == 0) continue;
			$cheapest = ($rate->getPrice() < $cheapest)? $rate->getPrice():$cheapest;
		}
		$final_price = $cheapest*$qty;
		if($final_price != 0 && $final_price != 100000){
			$responseContent = '<b>Postage:</b> $'.number_format($final_price, 2, '.', '');
		}else{
			$responseContent = "<b>'".$postcode."'</b> not supported by our carrier. Please <a href=\"/contacts\" target=\"_blank\">contact us</a> to place an order.";
		}
		$this->getResponse()->setBody($responseContent);
		return;
	}
}
?>