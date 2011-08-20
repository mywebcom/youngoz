<?php
class CG_Paypal_Model_Standard extends Mage_Paypal_Model_Standard {
 	public function canCapture()
    {
        return true;
    }
    
	public function ipnPostSubmit()
    {
        $sReq = '';
        $sReqDebug = '';
        foreach($this->getIpnFormData() as $k=>$v) {
            $sReq .= '&'.$k.'='.urlencode(stripslashes($v));
            $sReqDebug .= '&'.$k.'=';

        }
        //append ipn commdn
        $sReq .= "&cmd=_notify-validate";
        $sReq = substr($sReq, 1);

        if ($this->getDebug()) {
            $debug = Mage::getModel('paypal/api_debug')
                    ->setApiEndpoint($this->getPaypalUrl())
                    ->setRequestBody($sReq)
                    ->save();
        }
        $http = new Varien_Http_Adapter_Curl();
        $http->write(Zend_Http_Client::POST,$this->getPaypalUrl(), '1.1', array(), $sReq);
        $response = $http->read();
        $response = preg_split('/^\r?$/m', $response, 2);
        $response = trim($response[1]);
        if ($this->getDebug()) {
            $debug->setResponseBody($response)->save();
        }

         //when verified need to convert order into invoice
        $id = $this->getIpnFormData('invoice');
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($id);

        if ($response=='VERIFIED') {
            if (!$order->getId()) {
                /*
                * need to have logic when there is no order with the order id from paypal
                */

            } else {

                if ($this->getIpnFormData('mc_gross')!=$order->getBaseGrandTotal()) {
                    //when grand total does not equal, need to have some logic to take care
                    $order->addStatusToHistory(
                        $order->getStatus(),//continue setting current order status
                        Mage::helper('paypal')->__('Order total amount does not match paypal gross total amount')
                    );
                    $order->save();
                } else {
                    /*
                    //quote id
                    $quote_id = $order->getQuoteId();
                    //the customer close the browser or going back after submitting payment
                    //so the quote is still in session and need to clear the session
                    //and send email
                    if ($this->getQuote() && $this->getQuote()->getId()==$quote_id) {
                        $this->getCheckout()->clear();
                        $order->sendNewOrderEmail();
                    }
                    */

                    // get from config order status to be set
                    $newOrderStatus = $this->getConfigData('order_status', $order->getStoreId());
                    if (empty($newOrderStatus)) {
                        $newOrderStatus = $order->getStatus();
                    }

                    /*
                    if payer_status=verified ==> transaction in sale mode
                    if transactin in sale mode, we need to create an invoice
                    otherwise transaction in authorization mode
                    */
                    if ($this->getIpnFormData('payment_status') == 'Completed') {
                       if (!$order->canInvoice()) {
                           //when order cannot create invoice, need to have some logic to take care
                           $order->addStatusToHistory(
                                $order->getStatus(), // keep order status/state
                                Mage::helper('paypal')->__('Error in creating an invoice', true),
                                $notified = true
                           );

                       } else {
                           //need to save transaction id
                           $order->getPayment()->setTransactionId($this->getIpnFormData('txn_id'));
                           //need to convert from order into invoice
                           $invoice = $order->prepareInvoice();
                           $invoice->register()->pay();
                           Mage::getModel('core/resource_transaction')
                               ->addObject($invoice)
                               ->addObject($invoice->getOrder())
                               ->save();
                           $order->setState(
                               Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus,
                               Mage::helper('paypal')->__('Invoice #%s created', $invoice->getIncrementId()),
                               $notified = true
                           );
                       }
                    } else {
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PROCESSING, $newOrderStatus,
                            Mage::helper('paypal')->__('Received IPN verification'),
                            $notified = true
                        );
                    }

                    $ipnCustomerNotified = true;
                    if (!$order->getPaypalIpnCustomerNotified()) {
                        $ipnCustomerNotified = false;
                        $order->setPaypalIpnCustomerNotified(1);
                    }

                    $order->save();

                    if (!$ipnCustomerNotified) {
                        $order->sendNewOrderEmail();
                    }

                }//else amount the same and there is order obj
                //there are status added to order
            }
        }else{
            /*
            Canceled_Reversal
            Completed
            Denied
            Expired
            Failed
            Pending
            Processed
            Refunded
            Reversed
            Voided
            */
            $payment_status= $this->getIpnFormData('payment_status');
            $comment = $payment_status;
            if ($payment_status == 'Pending') {
                $comment .= ' - ' . $this->getIpnFormData('pending_reason');
            } elseif ( ($payment_status == 'Reversed') || ($payment_status == 'Refunded') ) {
                $comment .= ' - ' . $this->getIpnFormData('reason_code');
            }
            //response error
            if (!$order->getId()) {
                /*
                * need to have logic when there is no order with the order id from paypal
                */
            } else {
                $order->addStatusToHistory(
                    $order->getStatus(),//continue setting current order status
                    Mage::helper('paypal')->__('Paypal IPN Invalid %s.', $comment)
                );
                $order->save();
            }
        }
    }
}
?>