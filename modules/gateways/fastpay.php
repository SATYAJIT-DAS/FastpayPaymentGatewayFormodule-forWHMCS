<?php
/**
* WHMCS Sample Payment Gateway Module
*
* Payment Gateway modules allow you to integrate payment solutions with the
* WHMCS platform.
*
* This sample file demonstrates how a payment gateway module for WHMCS should
* be structured and all supported functionality it can contain.
*
* Within the module itself, all functions must be prefixed with the module
* filename, followed by an underscore, and then the function name. For this
* example file, the filename is "gatewaymodule" and therefore all functions
* begin "fastpay_".
*
* If your module or third party API does not support a given function, you
* should not define that function within your module. Only the _config
* function is required.
*
* For more information, please refer to the online documentation.
*
* @see https://developers.whmcs.com/payment-gateways/
*
* @copyright Copyright (c) WHMCS Limited 2017
* @license http://www.whmcs.com/license/ WHMCS Eula
*/

if (!defined("WHMCS")) {
  die("This file cannot be accessed directly");
}

/**
* Define module related meta data.
*
* Values returned here are used to determine module related capabilities and
* settings.
*
* @see https://developers.whmcs.com/payment-gateways/meta-data-params/
*
* @return array
*/
function fastpay_MetaData()
{
  return array(
    'DisplayName' => 'Fast Pay Gateway Module',
    'APIVersion' => '1.1', // Use API Version 1.1
    'DisableLocalCreditCardInput' => true,
    'TokenisedStorage' => false,
  );
}

/**
* Define gateway configuration options.
*
* The fields you define here determine the configuration options that are
* presented to administrator users when activating and configuring your
* payment gateway module for use.
*
* Supported field types include:
* * text
* * password
* * yesno
* * dropdown
* * radio
* * textarea
*
* Examples of each field type and their possible configuration parameters are
* provided in the sample function below.
*
* @return array
*/
function fastpay_config()
{
  return array(
    // the friendly display name for a payment gateway should be
    // defined here for backwards compatibility
    'FriendlyName' => array(
      'Type' => 'System',
      'Value' => 'Fastpay Gateway Module',
    ),
    // a text field type allows for single line text input
    'accountID' => array(
      'FriendlyName' => 'Account ID',
      'Type' => 'text',
      'Size' => '25',
      'Default' => '+964',
      'Description' => 'Enter your account ID here',
    ),
    // a password field type allows for masked text input
    'secretKey' => array(
      'FriendlyName' => 'Secret Key',
      'Type' => 'password',
      'Size' => '25',
      'Default' => '',
      'Description' => 'Enter secret key here',
    ),
    // the yesno field type displays a single checkbox option
    'testMode' => array(
      'FriendlyName' => 'Test Mode',
      'Type' => 'yesno',
      'Description' => 'Tick to enable test mode',
    ),

  );
}

/**
* Payment link.
*
* Required by third party payment gateway modules only.
*
* Defines the HTML output displayed on an invoice. Typically consists of an
* HTML form that will take the user to the payment gateway endpoint.
*
* @param array $params Payment Gateway Module Parameters
*
* @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
*
* @return string
*/
function fastpay_link($params)
{
  // Gateway Configuration Parameters
  $accountId = $params['accountID'];
  $secretKey = $params['secretKey'];
  $testMode = $params['testMode'];
  // $dropdownField = $params['dropdownField'];
  // $radioField = $params['radioField'];
  // $textareaField = $params['textareaField'];

  // Invoice Parameters
  $invoiceId = $params['invoiceid'];
  $description = $params["description"];
  $amount = $params['amount'];
  $currencyCode = $params['currency'];

  // Client Parameters
  $firstname = $params['clientdetails']['firstname'];
  $lastname = $params['clientdetails']['lastname'];
  $email = $params['clientdetails']['email'];
  $address1 = $params['clientdetails']['address1'];
  $address2 = $params['clientdetails']['address2'];
  $city = $params['clientdetails']['city'];
  $state = $params['clientdetails']['state'];
  $postcode = $params['clientdetails']['postcode'];
  $country = $params['clientdetails']['country'];
  $phone = $params['clientdetails']['phonenumber'];

  // System Parameters
  $companyName = $params['companyname'];
  $systemUrl = $params['systemurl'];
  $returnUrl = $params['returnurl'];
  $langPayNow = $params['langpaynow'];
  $moduleDisplayName = $params['name'];
  $moduleName = $params['paymentmethod'];
  $whmcsVersion = $params['whmcsVersion'];


  /* PHP */
  $post_data = array();
  $post_data['merchant_mobile_no'] = $accountId;
  $post_data['store_password'] = $secretKey;
  $post_data['order_id'] = $invoiceId;
  $post_data['bill_amount'] = $amount;
  $post_data['success_url'] = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
  $post_data['fail_url'] = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
  $post_data['cancel_url'] = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';


  // SENT REQUEST TO FASTPAY
  if($testMode){
    $direct_api_url = "https://dev.fast-pay.cash/merchant/generate-payment-token";
  }else{
    $direct_api_url = "https://secure.fast-pay.cash/merchant/generate-payment-token";
  }


  $handle = curl_init();
  curl_setopt($handle, CURLOPT_URL, $direct_api_url );
  curl_setopt($handle, CURLOPT_TIMEOUT, 10);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($handle, CURLOPT_POST, 1 );
  curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);


  $content = curl_exec($handle );

  $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

  if($code == 200 && !( curl_errno($handle))) {
    curl_close( $handle);
    $response = $content;
  } else {
    curl_close( $handle);
    echo "FAILED TO CONNECT WITH FastPay  API";
    exit;
  }

  // PARSE THE JSON RESPONSE
  $decodedResponse = json_decode($response, true );

  if($decodedResponse["code"] == 200)
  {
    $token = $decodedResponse["token"];
    if($testMode)
    {
      $aproval_url = "https://dev.fast-pay.cash/merchant/payment?token=$token";
    }
    else
    {
      $aproval_url = "https://secure.fast-pay.cash/merchant/payment?token=$token";
    }
    // redirect user to fastpay

    // header($aproval_url);
    // return $aproval_url;

    $htmlOutput = '<form method="post" action="' . $aproval_url . '">';
    $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
  }

}
