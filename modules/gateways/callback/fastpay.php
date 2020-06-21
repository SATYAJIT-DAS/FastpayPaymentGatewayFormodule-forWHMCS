<?php
/**
* WHMCS Sample Payment Callback File
*
* This sample file demonstrates how a payment gateway callback should be
* handled within WHMCS.
*
* It demonstrates verifying that the payment gateway module is active,
* validating an Invoice ID, checking for the existence of a Transaction ID,
* Logging the Transaction for debugging and Adding Payment to an Invoice.
*
* For more information, please refer to the online documentation.
*
* @see https://developers.whmcs.com/payment-gateways/callbacks/
*
* @copyright Copyright (c) WHMCS Limited 2017
* @license http://www.whmcs.com/license/ WHMCS Eula
*/

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');
$returnUrl = $params['returnurl'];

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

$accountId =$gatewayParams['accountID'];
$secretKey = $gatewayParams['secretKey'];
$testMode = $gatewayParams['testMode'];
$systemURL = $gatewayParams['systemurl'];
// Die if module is not active.
if (!$gatewayParams['type']) {
  die("Module Not Activated");
}

// Retrieve data returned in payment gateway callback
// Varies per payment gateway
$status = $_REQUEST["status"];
$invoiceId = $_REQUEST["order_id"];
$transactionId = $_REQUEST["transaction_id"];
$paymentAmount = $_REQUEST["bill_amount"];
$customerAccNo= $_REQUEST["customer_account_no"];
$receivedAt = $_REQUEST["received_at"];

// $transactionStatus = $success ? 'Success' : 'Failure';
if ($status == "Success") {
  $transactionStatus = 'Success';
  $success = true;
}elseif ($status == "Failed") {
  $transactionStatus = 'Failure';
}else {
  $transactionStatus = 'Cancelled';
}
/**
* Validate callback authenticity.
*
* Most payment gateways provide a method of verifying that a callback
* originated from them. In the case of our example here, this is achieved by
* way of a shared secret which is used to build and compare a hash.
*/
// $secretKey = $gatewayParams['secretKey'];
// if ($hash != md5($invoiceId . $transactionId . $paymentAmount . $secretKey)) {
//     $transactionStatus = 'Hash Verification Failure';
//     $success = false;
// }

$post_data = array();
$post_data['merchant_mobile_no']=$accountId;
$post_data['store_password']=$secretKey;
$post_data['order_id']=$invoiceId;

if($testMode){
  $requested_url = "https://dev.fast-pay.cash/merchant/payment/validation";
}else{
  $requested_url = "https://secure.fast-pay.cash/merchant/payment/validation";
}


$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, $requested_url );
curl_setopt($handle, CURLOPT_TIMEOUT, 10);
curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($handle, CURLOPT_POST, 1 );
curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($handle);

$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if($code == 200 && !( curl_errno($handle)))
{

  # TO CONVERT AS ARRAY
  # $result = json_decode($result, true);

  # TO CONVERT AS OBJECT
  $result = json_decode($result);

  # TRANSACTION INFO
  $messages = $result->messages;
  $code = $result->code; #if $code is not 200 then something is wrong with your request.
  $data = $result->data;

  if ($code ==200) {
    $success = true;
  }elseif($code != 200){
    $transactionStatus = 'Hash Verification Failure';
    $success = false;
  }


} else {

  echo "Failed to connect with FastPay";
}



/**
* Validate Callback Invoice ID.
*
* Checks invoice ID is a valid invoice number. Note it will count an
* invoice in any status as valid.
*
* Performs a die upon encountering an invalid Invoice ID.
*
* Returns a normalised invoice ID.
*
* @param int $invoiceId Invoice ID
* @param string $gatewayName Gateway Name
*/
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

/**
* Check Callback Transaction ID.
*
* Performs a check for any existing transactions with the same given
* transaction number.
*
* Performs a die upon encountering a duplicate.
*
* @param string $transactionId Unique Transaction ID
*/
checkCbTransID($transactionId);


/**
* Log Transaction.
*
* Add an entry to the Gateway Log for debugging purposes.
*
* The debug data can be a string or an array. In the case of an
* array it will be
*
* @param string $gatewayName        Display label
* @param string|array $debugData    Data to log
* @param string $transactionStatus  Status
*/
logTransaction($gatewayParams['name'], $_POST, $transactionStatus);

if ($success) {

  /**
  * Add Invoice Payment.
  *
  * Applies a payment transaction entry to the given invoice ID.
  *
  * @param int $invoiceId         Invoice ID
  * @param string $transactionId  Transaction ID
  * @param float $paymentAmount   Amount paid (defaults to full balance)
  * @param float $paymentFee      Payment fee (optional)
  * @param string $gatewayModule  Gateway module name
  */
  addInvoicePayment(
    $invoiceId,
    $transactionId,
    $paymentAmount,
    $paymentFee,
    $gatewayModuleName
  );
  header('Location:'.$systemURL.'viewinvoice.php?id='.$invoiceId.'&paymentsuccess=true');
  exit;
}else{
  header('Location:'.$systemURL.'viewinvoice.php?id='.$invoiceId.'&paymentsuccess=true');
  exit;
}
