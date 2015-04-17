# bitdrive-magento-plugin
## BitDrive payment method plugin for Magento

Accept bitcoin payments on your Magento shopping cart using BitDrive Standard Checkout. Includes support for the BitDrive Instant Payment Notification (IPN) messages.

### Minimum Requirements
* PHP 5.2+
* Magento 1.5+

### Quick Installation
1. Extract the files in the archive to the Magento root path.
2. Log in to **Magento Admin** and navigate to **System > Configuration > Payment Methods**.
3. Expand the **BitDrive Standard Checkout** tab.
4. Set **Enabled** to Yes.
5. Select an option for **New order status**.
6. Specify your **Merchant ID**.
7. If you have IPN enabled, specify your BitDrive **IPN Secret**.
8. Click the **Save config** button.

For documentation on BitDrive merchant services, go to https://www.bitdrive.io/help/merchant-services/6-introduction
