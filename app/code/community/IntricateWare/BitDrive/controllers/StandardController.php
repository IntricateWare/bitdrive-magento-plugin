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

/**
 * Controller for the BitDrive redirect, success and cancel pages.
 */
class IntricateWare_BitDrive_StandardController extends Mage_Core_Controller_Front_Action {
    /**
     * The order instance.
     */
    protected $_order;

    /**
     *  Get the order.
     *
     *  @return Mage_Sales_Model_Order
     */
    public function getOrder() {
        return $this->_order;
    }
    
    /**
     * When a customer chooses BitDrive Standard Checkout on the checkout/payment page
     */
    public function redirectAction() {
        $session = Mage::getSingleton('checkout/session');
        $session->setBitDriveStandardQuoteId($session->getQuoteId());
        $this->getResponse()->setBody($this->getLayout()->createBlock('bitdrive/standard_redirect')->toHtml());
        $session->unsQuoteId();
        $session->unsRedirectUrl();
    }
    
    /**
     * When a customer cancels the payment on BitDrive.
     */
    public function cancelAction() {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getBitDriveStandardQuoteId(true));
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            Mage::helper('bitdrive/checkout')->restoreQuote();
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * When a customer is returned from a successful BitDive payment. However, the order
     * won't be processed until you get validation from IPN.
     */
    public function successAction() {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getBitDriveStandardQuoteId(true));
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        $this->_redirect('checkout/onepage/success', array('_secure'=>true));
    }
}

?>