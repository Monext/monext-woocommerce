## Changelog

### 1.5.5 - 2025/07/22
* Fix round price
* Fix taxrate error when total order at 0, fix #13
* Fix infinite loading on log screen, fix #14

#### Environment (Development, QA validation)
* WordPress version: 6.8.1
* WooCommerce version: 9.8.5

### 1.5.4 - 2025/06/12
  * Update API version from 26 to 34
  * Display notification URL in backoffice
  * Allow error messages personalization
  * Automatic return to WP on payment ticket displayed 
  * Fix error on dynamic declaration of $gateway
  * Fix redirection on cancellation
  * Fix second payment after a first one with error
  * Fix status error update

#### Environment (Development, QA validation)
 * WordPress version: 6.8.1
 * WooCommerce version: 9.8.5
    
### 1.5.3 - 2025/03/12
  * Add Floa payment compatibility
  * Fix unwanted cancelled order on notification, Fix #3
  * Add display.rule.param in configuration to use in Smartdisplay
  * Adjustment bias error, Fix #5

### 1.5.2 - 2024/12/05
  * Add Klarna payment compatibility
  * Add log visibility in backoffice
  * Fix billingDay for NX payment

### 1.5.1 - 2024/10/24
  * Refund order on cancel status

### 1.5.0 - 2024/05/20
  * Compatibility with the Cart and Checkout blocks

### 1.4.9 - 2024/05/06
  * Reuse DWP token
  * Avoid cancelling order on notification

### 1.4.8 - 2024/04/19
  * Wallet activation
  * Fix warning on properies declaration

### 1.4.7 - 2023/06/12
  * Compliance with Wordpress 6.3.2
  * Compliance with Woocommerce 8.2.1 and compatibility with HPOS
  * Add customer id in buyer field customer_id
  * Compliance with Payline PHP SDK 4.76

### 1.4.6 - 2023/06/12
  * Compliance with Wordpress 6.2.2
  * Compliance with Woocommerce 7.2.2
  * Compliance with Payline PHP SDK 4.74

### 1.4.5 - 2022/10/07
  * Compliance with Payline PHP SDK 4.71
  * Check on-hold status
  * Compatibility with COFIDIS
  
### 1.4.4 - 2022/05/24
  * Compliance with Payline PHP SDK 4.69
  * Set API version to 26

### 1.4.3 - 2021/06/30
  * Compliance with Payline PHP SDK 4.66
  * Fix start_date on recurring payment

### 1.4.2 - 2021/05/24
  * Compliance with Payline PHP SDK 4.64.1
  * Fix warning on PHP Deprecated:  WC_Shortcode_Checkout->output
  * Set API version to 21
  * Filter auto update to avoid install BitPay
  * Add param payline_cancel in cancel URL
  
### 1.4.1 - 2021/03/11
  * Add configuration to choose the status for Payline payed order
  * Fix substring special utf8 character
  * Fix on critical using monolog on SDK error 

### 1.4 - 2021/01/29
  * Add widget integration
  * Add NX and REC payment methods
  * Add Refund online feature
  * Improve user experience
  * Compliance with WooCommerce 4.9.2
  * Compliance with Payline PHP SDK 4.59
  
### 1.3.7 - 2020/12/01
  * Feature - WooCommerce 3.x compatibility (not compatible anymore with WooCommerce versions below 2.6)
  * Transaction id compatibility, Translation files
  * Fix on token get data (versus paylinetoken)

### 1.3.6 - 2018/01/02  
  * Fix - Truncate buyer data before send it to Payline.

### 1.3.5 - 2017/04/04
  * Feature - send buyer info mandatoty for Cetelem 3x / 4x

### 1.3.4 - 2017/02/27  
  * Fix - languages files

### 1.3.3 - 2016/08/26  
  * Feature - order/token association. Prevents conflicts between payment sessions.

### 1.3.2 - 2016/08/04  
  * Fix - Truncate order details product name to 50 characters before send it to Payline.

### 1.3.1 - 2015/12/09  
  * Feature - compliance with Payline PHP library v4.43

### 1.3 - 2015/02/27  
  * Feature - compliance with wc 2.3 and over
