<?php

/*
 * Copyright (c) 2015 IntricateWare Inc.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class IntricateWare_BitDrive_Model_Standard extends Mage_Payment_Model_Method_Abstract {
    /**
     * The BitDrive checkout URL.
     * @type string
     */
    private $_checkoutUrl = 'https://www.bitdrive.io/pay';
    
    /**
     * The Magento store object.
     * @type object
     */
    private $_store = null;
    
    /**
     * Unique identifier code for the model.
     * @type string
     */
    protected $_code = 'bitdrive_standardcheckout';
    
    /**
     * The standard form block.
     * @type string
     */
    protected $_formBlockType = 'bitdrive/standard_form';
    
    /**
     * Flag which indicates whether or not initalisation is needed.
     * @type boolean
     */
    protected $_isInitializeNeeded = true;
    
    /**
     * Whether method is available for specified currency.
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode) {
        return ( in_array(strtolower($currencyCode), array('usd', 'btc')) );
    }
    
    /**
     * Get checkout session namespace.
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }
    
    /**
     * Get the BitDrive checkout URL.
     *
     * @return string
     */
    public function getCheckoutUrl() {
        return $this->_checkoutUrl;
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }
    
    /**
     * Get a configured payment method value.
     *
     * @return mixed
     */
    public function getConfigValue($key)
    {
        if (!$this->_store) {
            $this->_store = Mage::app()->getStore();
        }
        $storeId = $this->_store->getId();
        return Mage::getStoreConfig('payment/' . $this->_code . '/' . $key, $storeId);
    }

    /**
     * Create the main block for the standard checkout form.
     */
    public function createFormBlock($name)
    {
        $block = $this->getLayout()->createBlock('bitdrive/standard_form', $name)
            ->setMethod('bitdrive_standardcheckout')
            ->setPayment($this->getPayment())
            ->setTemplate('bitdrive/standard/form.phtml');

        return $block;
    }
    
    /**
     * Return Order place redirect url.
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('bitdrive/standard/redirect', array('_secure' => true));
    }
    
    /**
     * Get the form fields for BitDrive Standard Checkout.
     *
     * @return array
     */
    public function getStandardCheckoutFormFields() {
        $orderIncrementId = $this->getCheckout()->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        
        return array(
            'bd-cmd'            => 'pay',
            'bd-merchant'       => $this->getConfigValue('merchant_id'),
            'bd-currency'       => $order->getBaseCurrencyCode(),
            'bd-amount'         => $order->getBaseGrandTotal(),
            'bd-memo'           => $this->_buildTransactionMemo($order),
            'bd-invoice'        => $orderIncrementId,
            'bd-success-url'    => Mage::getUrl('bitdrive/standard/success', array('_secure' => true)),
            'bd-error-url'      => Mage::getUrl('bitdrive/standard/cancel', array('_secure' => true))
        );
    }
    
    /**
     * Instantiate state and set it to state object.
     * 
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject) {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }
    
    /**
     * Build the transaction memo based on the order attributes.
     * 
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    private function _buildTransactionMemo(&$order) {
        $memo = sprintf('Payment for order #%s', $order->getIncrementId());
        $items = $order->getAllItems();
        if (count($items) == 1) {
            // Build the string as "Qty x Item Name"
            $item = $items[0];
            $qty = intval($item->getQtyOrdered());
            $itemString = (($qty > 0) ? $qty . ' x ' : '') . $item->getName();
            
            $newMemo = $memo . ': ' . $itemString;
            if (strlen($newMemo) <= 200) {
                $memo = $newMemo;
            }
        }
        
        return $memo;
    }  
}

?>