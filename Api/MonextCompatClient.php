<?php

namespace Payline\Api;

use Payline\Api\MonextClient;
use Monolog\Logger;

class MonextCompatClient extends MonextClient
{

    public $logger;

    public static function configure(string $baseUrl, string $authorization): void
    {
        MonextClient::configure($baseUrl, $authorization);
    }

    public static function usedBy(string $version)
    {
        MonextClient::usedBy($version);
    }

    public function getEncryptionKey($array)
    {
        return parent::getEncryptionKey("test");
    }

    //Data added directly in order.items key from getWebPaymentRequest
    public function addOrderDetail(array $newOrderDetail)
    {
        return true;
    }

    //Data added directly in privateData key from getWebPaymentRequest
    public function addPrivateData(array $array)
    {
        return true;
    }

    public function doWebPayment(array $requestParams)
    {
        $payload = $requestParams;
        $result = $this->createSession($payload);
        if (isset($result["sessionId"])) {
            $result["token"] = $result["sessionId"];
        }
        return $result;
    }

    public function doCapture(array $array)
    {
        //array('transactionID' => $order->get_transaction_id(), 'payment'  => $paymentParams)
        return $this->captureTransaction($array['transactionID'], $array['payment']);
    }

    public function doRefund(array $array)
    {
        $payload = array(
            "amount" => $array['payment']['amount'],
            "comment" => $array['comment'],
        );
        return $this->refundTransaction($array['transactionID'], $payload);
    }

    public function getWebPaymentDetails(array $array)
    {
        $sessionId = $array['token'];
        $result = $this->getSession($sessionId);

        if (isset($result["order"])) {
            $result["order"]["ref"] = $result["order"]["reference"];
        }

        if (isset($result["transactions"])) {
            $result["transaction"] = end($result['transactions']);
        }

        return $result;
    }

    public function getTransactionDetails(array $array)
    {
        $transactionId = $array['transactionId'];
        $result = $this->getTransaction($transactionId);

        //        if(isset($result["order"])){
        //            $result["order"]["ref"] = $result["order"]["reference"] ;
        //        }
        //
        //        if(isset($result["transactions"])){
        //            $result["transaction"] = end($result['transactions']) ;
        //        }

        return $result;
    }

    public function manageWebWallet(array $array)
    {
        $result = $this->createMXWalletSession($array);

        if (isset($result["mxWalletSessionId"])) {
            $result["token"] = $result["mxWalletSessionId"];
        }

        return $result;
    }
}
