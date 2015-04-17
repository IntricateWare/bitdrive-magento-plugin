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

class IntricateWare_BitDrive_Model_Ipn {
    /**
     * The default debug log file.
     * @type string
     */
    const DEFAULT_LOG_FILE = 'bitdrive_ipn.log';
    
    /**
     * The 'ORDER_CREATED' notification type string.
     * @type string
     */
    const ORDER_CREATED = 'ORDER_CREATED';
    
    /**
     * The 'PAYMENT_COMPLETED' notification type string.
     * @type string
     */
    const PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';
    
    /**
     * The 'TRANSACTION_CANCELLED' notification type string.
     * @type string
     */
    const TRANSACTION_CANCELLED = 'TRANSACTION_CANCELLED';
    
    /**
     * The 'TRANSACTION_EXPIRED' notification type string.
     * @type string
     */
    const TRANSACTION_EXPIRED = 'TRANSACTION_EXPIRED';
    
    /**
     * Debug flag
     * @type boolean
     */
    private $_debug = false;
    
    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();
    
    /**
     * The IPN JSON object.
     * 
     * @type object
     */
    protected $_json = null;
    
    /**
     * The merchant ID.
     *
     * @type string
     */
    protected $_merchantId = null;
    
    /**
     * Store order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order = null;
    
    /**
     * The required IPN message parameters.
     * 
     * @type array
     */
    private $_requiredParams = array(
        'notification_type',
        'sale_id',
        'merchant_invoice',
        'amount',
        'bitcoin_amount'
    );
    
    /**
     * Generate an "IPN" comment with additional explanation.
     * Returns the generated comment or order status history object
     *
     * @param string $comment
     * @param bool $addToHistory
     * 
     * @return string|Mage_Sales_Model_Order_Status_History
     */
    protected function _createIpnComment($comment = '', $addToHistory = false)
    {
        $paymentStatus = $this->getPaymentStatus();
        $message = sprintf('IPN "%s".', $paymentStatus);
        if ($comment) {
            $message .= ' ' . $comment;
        }
        if ($addToHistory) {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
        return $message;
    }
    
    /**
     * Log debug data to file
     */
    protected function _debug()
    {
        if ($this->_debug) {
            Mage::getModel('core/log_adapter', self::DEFAULT_LOG_FILE)->log($this->_debugData);
        }
    }
    
    /**
     * Load and validate order, instantiate proper configuration
     *
     * @return Mage_Sales_Model_Order
     * @throws Exception
     */
    protected function _getOrder()
    {
        if (empty($this->_order)) {
            // get proper order
            $id = $this->getJson()->merchant_invoice;
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($id);
            if (!$this->_order->getId()) {
                $this->_debugData['exception'] = sprintf('Wrong order ID: "%s".', $id);
                $this->_debug();
                Mage::app()->getResponse()
                    ->setHeader('HTTP/1.1','503 Service Unavailable')
                    ->sendResponse();
                exit;
            }
            
            $this->_verifyOrder();
        }
        return $this->_order;
    }
    
    /**
     * Process order transaction created.
     */
    protected function _registerOrderCreated() {
    
    }
    
    /**
     * Process order transaction payment completed.
     */
    protected function _registerPaymentCompleted() {
        // Update the payment object and save the order
        $payment = $this->_order->getPayment();
        $payment->setCurrencyCode($this->getJson()->currency)
            ->setPreparedMessage($this->_createIpnComment('', true))
            ->setIsTransactionApproved(true)
            ->setIsTransactionClosed(true);
        $this->_order->setIsInProcess(true)->save();
        
        // Create the invoice for the order
        $invoice = $this->_order->prepareInvoice()
            ->setTransactionId($this->getJson()->sale_id)
            ->addComment(sprintf('Invoice created for order #%s', $this->_order->getIncrementId()))
            ->register()
            ->pay();
           
        // Save the invoice and the order 
        Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
        
        // Notify the customer by email
        if (!$this->_order->getEmailSent()) {
            $this->_order->queueNewOrderEmail()->addStatusHistoryComment(
                sprintf('Notified customer about invoice #%s.', $invoice->getIncrementId())
            )
            ->setIsCustomerNotified(true)
            ->save();
        }
    }
    
    /**
     * Process order transaction cancelled.
     */
    protected function _registerTransactionCancelled() {
        $this->_order->registerCancellation($this->_createIpnComment(''), false)->save();
    }
    
    /**
     * Process order transaction expired.
     */
    protected function _registerTransactionExpired() {
        $this->_registerTransactionCancelled();
    }
    
    /**
     * Check that the configured merchant ID and the merchant ID associated with the IPN request matches.
     */
    protected function _verifyOrder() {
        // Verify the merchant Id is intended to receive notification
        $txMerchantId = $this->_json->merchant_id;
        if (strtolower($this->_merchantId) != strtolower($txMerchantId)) {
            throw new Exception(
                sprintf(
                    'Requested %s and configured %s merchant emails do not match.', $this->_merchantId, $txMerchantId
                )
            );
        }
    }
    
    /**
     * Get the payment status based on the notification type.
     *
     * @return string
     */
    public function getPaymentStatus() {
        switch ($this->getJson()->notification_type) {
            // Order created
            case self::ORDER_CREATED:
                return 'Created';
            
            // Payment completed
            case self::PAYMENT_COMPLETED:
                return 'Completed';
            
            // Transaction cancelled
            case self::TRANSACTION_CANCELLED:
                return 'Cancelled';
            
            case self::TRANSACTION_EXPIRED:
                return 'Expired';
        }
        
        return '';
    }
    
    /**
     * Get the IPN JSON object.
     * 
     * @return object
     */
    public function getJson() {
        return $this->_json;
    }
    
    /**
     * Process the IPN request data from BitDrive.
     *
     * @param string $data
     */
    public function processIpnRequest($data) {
        // Add the IPN data to debug
        $this->_debugData = array('ipn' => $data);
        
        try {
            // Check hash algorithms to make sure sha256 is supported
            if (!in_array('sha256', hash_algos())) {
                throw new Exception('The PHP installation does not support the SHA 256 hash algorithm.');
            }
            
            // Get the JSON object from the data string
            $this->_json = json_decode($data);
            if (!$this->_json) {
                throw new IntricateWare_BitDrive_InvalidIpnDataException('The BitDrive IPN JSON data is invalid.');
            }
            
            // Check the parameters that we need
            foreach ($this->_requiredParams as $param) {
                if (!isset($this->getJson()->$param) || strlen(trim($this->getJson()->$param)) == 0) {
                    throw new IntricateWare_BitDrive_InvalidIpnDataException(sprintf('Missing %s IPN parameter.', $param));
                }
            }
            
            // Load the configured merchant ID and IPN secret
            $standard = Mage::getModel('bitdrive/standard');
            $this->_merchantId = $standard->getConfigValue('merchant_id');
            $ipnSecret = $standard->getConfigValue('ipn_secret');
            
            // Get and verify the order
            $this->_order = null;
            $this->_getOrder();
            
            // Verify the SHA 256 hash
            $hashString = strtoupper(hash('sha256',
                $this->getJson()->sale_id . $this->_merchantId . $this->getJson()->merchant_invoice . $ipnSecret));
            if ($hashString != $this->getJson()->hash) {
                throw new IntricateWare_BitDrive_IpnHashMismatchException(
                    'The notification message cannot be processed due to a hash mismatch.');
            }
            
            // Handle the notification type
            switch ($this->getJson()->notification_type) {
                // Order created
                case self::ORDER_CREATED:
                    $this->_registerOrderCreated();
                    break;
                
                // Payment completed
                case self::PAYMENT_COMPLETED:
                    $this->_registerPaymentCompleted();
                    break;
                
                // Transaction cancelled
                case self::TRANSACTION_CANCELLED:
                    $this->_registerTransactionCancelled();    
                    break;
                
                // Transaction expired
                case self::TRANSACTION_EXPIRED:
                    $this->_registerTransactionExpired();
                    break;
            }
        } catch (Exception $e) {
            $this->_debugData['exception'] = $e->getMessage();
            $this->_debug();
            throw $e;
        }
        
        $this->_debug();
    }
}

?>