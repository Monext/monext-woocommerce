<?php

namespace Payline\Api;

/**
 * Specific interface for Monext API.
 */
interface ApiInterface
{
    const ENVIRONMENTS = ['HOMO', 'PROD'];
    /**
     * Payline release corresponding to this version of the package
     * @see https://docs.payline.com/display/DT/API+version+history
     */
    const SDK_RELEASE = 'PHP SDK 4.76';

    /**
     * development environment flag
     */
    const ENV_DEV = "DEV";

    /**
     * integration environment flag
     */
    const ENV_INT = "INT";

    /**
     * homologation environment flag
     */
    const ENV_HOMO = "HOMO";

    /**
     * homologation environment flag - uses certificate-based authentication
     */
    const ENV_HOMO_CC = "HOMO_CC";

    /**
     * production environment flag
     */
    const ENV_PROD = "PROD";

    /**
     * production environment flag - uses certificate-based authentication
     */
    const ENV_PROD_CC = "PROD_CC";

    /**
     * name of Payline DirectPaymentAPI
     */
    const DIRECT_API = 'DirectPaymentAPI';

    /**
     * name of Payline ExtendedAPI
     */
    const EXTENDED_API = 'ExtendedAPI';

    /**
     * name of Payline WebPaymentAPI
     */
    const WEB_API = 'WebPaymentAPI';

    /**
     * SOAP name of authorization object
     */
    const SOAP_AUTHORIZATION = 'authorization';

    /**
     * SOAP name of card object
     */
    const SOAP_CARD = 'card';

    /**
     * SOAP name of orderDetail object
     */
    const SOAP_ORDERDETAIL = 'orderDetail';

    /**
     * SOAP name of paymentData object
     */
    const SOAP_PAYMENT_DATA = 'paymentData';

    /**
     * SOAP name of privateData object
     */
    const SOAP_PRIVATE_DATA = 'privateData';

    /**
     * SOAP name of address object
     */
    const SOAP_ADDRESS = 'address';

    /**
     * SOAP name of cheque object
     */
    const SOAP_CHEQUE = 'cheque';

    /**
     * SOAP name of creditor object
     */
    const SOAP_CREDITOR = 'creditor';

    /**
     * SOAP name of billingRecordForUpdate object
     */
    const SOAP_BILLING_RECORD_FOR_UPDATE = 'billingRecordForUpdate';

    /**
     * SOAP name of recurring object
     */
    const SOAP_RECURRING = 'recurring';

    /**
     * SOAP name of merchantAuthentication object
     */
    const SOAP_MERCHANT_AUTHENTICATION = 'merchantAuthentication';

    /**
     * SOAP name of wallet object
     */
    const SOAP_WALLET = 'wallet';

    /**
     * directory services endpoint in production environment
     */
    const HOMO_SERVICES_ENDPOINT = 'https://homologation-payment.payline.com/services/servicesendpoints/SOAP';

    /**
     * directory services endpoint in development environment
     */
    const PROD_SERVICES_ENDPOINT = 'https://payment.payline.com/services/servicesendpoints/SOAP';

    /**
     * web services endpoint in development environment
     */
    const DEV_ENDPOINT = 'https://ws.dev.payline.com/V4/services/';

    /**
     * web services endpoint in integration environment
     */
    const INT_ENDPOINT = 'https://ws.int.payline.com/V4/services/';

    /**
     * standard web services endpoint in homologation environment
     */
    const HOMO_ENDPOINT = 'https://homologation.payline.com/V4/services/';

    /**
     *  certificate-based authentication web services endpoint in homologation environment
     */
    const HOMO_CC_ENDPOINT = 'https://homologation-cc.payline.com/V4/services/';

    /**
     * standard web services endpoint in production environment
     */
    const PROD_ENDPOINT = 'https://services.payline.com/V4/services/';

    /**
     * certificate-based authentication web services endpoint in production environment
     */
    const PROD_CC_ENDPOINT = 'https://services-cc.payline.com/V4/services/';

    /**
     * URL of getToken servlet, used by AJAX API, in development environment
     */
    const DEV_GET_TOKEN_SERVLET = "https://webpayment.dev.payline.com/webpayment/getToken";

    /**
     * URL of getToken servlet, used by AJAX API, in integration environment
     */
    const INT_GET_TOKEN_SERVLET = "https://webpayment.int.payline.com/webpayment/getToken";

    /**
     * URL of getToken servlet, used by AJAX API, in homologation environment
     */
    const HOMO_GET_TOKEN_SERVLET = "https://homologation-webpayment.payline.com/webpayment/getToken";

    /**
     * URL of getToken servlet, used by AJAX API, in production environment
     */
    const PROD_GET_TOKEN_SERVLET = "https://webpayment.payline.com/webpayment/getToken";

    /**
     * Widget JavaScript in development environment
     */
    const DEV_WDGT_JS = "https://webpayment.dev.payline.com/payline-widget/scripts/widget-min.js";

    /**
     * Widget JavaScript in homologation environment
     */
    const HOMO_WDGT_JS = "https://homologation-payment.payline.com/scripts/widget-min.js";

    /**
     * Widget JavaScript in production environment
     */
    const PROD_WDGT_JS = "https://payment.payline.com/scripts/widget-min.js";

    /**
     * Widget css in development environment
     */
    const DEV_WDGT_CSS = "https://webpayment.dev.payline.com/payline-widget/styles/widget-min.css";

    /**
     * Widget css in homologation environment
     */
    const HOMO_WDGT_CSS = "https://homologation-payment.payline.com/styles/widget-min.css";

    /**
     * Widget css in production environment
     */
    const PROD_WDGT_CSS = "https://payment.payline.com/styles/widget-min.css";

    /**
     * homologation administration center URL
     */
    const HOMO_CA = 'https://homologation-admin.payline.com';

    /**
     * administration center URL
     */
    const PROD_CA = 'https://admin.payline.com';

    /**
     * error code/shortMessage returned when Payline can't be reached
     */
    const ERR_CODE = 'XXXXX';

    const ERR_SHORT_MESSAGE = 'ERROR';

    const SESSIONS_ENDPOINT = '/v1/checkout/payments/sessions';
    const SECURITY_ENCRYPTION_ENDPOINT = '/v1/security/encryption/keys';
    const TRANSACTIONS_ENDPOINT = '/v1/checkout/transactions';
    const TRANSACTIONS_CAPTURE_ENDPOINT = '/v1/checkout/transactions/%s/captures';
    const TRANSACTIONS_CANCEL_ENDPOINT = '/v1/checkout/transactions/%s/cancels';
    const TRANSACTIONS_REFUND_ENDPOINT = '/v1/checkout/transactions/%s/refunds';
    const MERCHANTS_POS_ENDPOINT = '/v1/merchants/%s/pointsofsale';
    const MERCHANTS_PAYMENT_METHOD_ENDPOINT = '/v1/merchants/%s/pointsofsale/%s/paymentMethods';
    const MXWALLET_SESSIONS_ENDPOINT = '/v1/checkout/mxwallets/sessions';

}