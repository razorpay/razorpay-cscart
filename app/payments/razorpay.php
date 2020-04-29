<?php
use Tygh\Registry;
use Razorpay\Api\Api;

include_once ('razorpay/razorpay_common.inc');
include_once ('razorpay/razorpay-webhook.php');
require_once ('razorpay/razorpay-sdk/Razorpay.php');
require_once ('razorpay/RazorpayPayment.php');

if ( !defined('AREA') ) { die('Access denied'); }


// Webhook flow s2s
if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    if ($mode == 'rzp_webhook')
    {
        $razorpayWebhook = new RZP_Webhook();
        $razorpayWebhook->process();
        exit;
    }
}

// Return from payment
if (defined('PAYMENT_NOTIFICATION'))
{
    if ($mode == 'return') {
        if (isset($view) === false)
        {
            $view = Registry::get('view');
        }

        $view->assign('order_action', __('placing_order'));
        $view->display('views/orders/components/placing_order.tpl');
        fn_flush();

        $razorpayPayment = new RazorpayPayment();

        $razorpayPayment->processRazorpayResponse();
        exit;
    }
}
else
{
    $razorpayPayment = new RazorpayPayment();

    //checks for iframe mode. In iframe mode payment flow goes through another payment button
    if ((defined('IFRAME_MODE') === true) and (empty($_GET['clicked']) === true))
    {
        echo $razorpayPayment->getButton();
    }
    else
    {
        $url = fn_url("payment_notification.return?payment=razorpay", AREA, 'current');

        $data = $razorpayPayment->getOrderData($order_id, $order_info, $processor_data);

        $keyId = $processor_data['processor_params']['key_id'];

        $keySecret = $processor_data['processor_params']['key_secret'];

        $isIframeEnabled = $processor_data['processor_params']['iframe_mode'];

        $api = new Api($keyId, $keySecret);

        $razorpayOrderId = null;

        try
        {
            $razorpayOrder = $api->order->create($data);
            $razorpayOrderId = $razorpayOrder['id'];
        }
        catch (\Exception $e)
        {
            echo 'CS Cart Error : ' . $e->getMessage();
        }

        if($isIframeEnabled === 'Y'){
            $order_id = fn_rzp_place_order($order_id);
        }

        $sessionValues = array(
            'razorpay_order_id' => $razorpayOrderId,
            'merchant_order_id' => $order_id
        );

        $razorpayPayment->setSessionValues($sessionValues);

        $fields = array(
            'key'         => $keyId,
            'amount'      => fn_rzp_adjust_amount($order_info['total'], $processor_data['processor_params']['currency'])*100,
            'currency'    => $processor_data['processor_params']['currency'],
            'description' => "Order# ".$order_id,
            'name'        => Registry::get('settings.Company.company_name'),
            'prefill'     => array(
                'name'    => $order_info['b_firstname'] . " " . $order_info['b_lastname'],
                'email'   => $order_info['email'],
                'contact' => $order_info['phone']
            ),
            'notes'       => array(
                'cs_order_id' => $order_id,
            ),
            'order_id' => $razorpayOrderId,
            'callback_url' => $url,
            '_' => array(
              'integration' => 'cscart',
              'integration_version' => RazorpayPayment::VERSION,
              'integration_parent_version' => PRODUCT_VERSION
            )
        );

        if (!$fields['amount'])
        {
            echo __('text_unsupported_currency');
            exit;
        }
        if((defined('IFRAME_MODE') === false))
        {
            $merchantPreferences    = getMerchantPreferences($api);
            $fields['api_url']      = $api->getBaseUrl();
            $fields['is_hosted']    = $merchantPreferences['is_hosted'];
            $fields['image']        = $merchantPreferences['image'];
            $fields['callback_url'] = $url;
            $fields['cancel_url']   = fn_url('checkout.checkout');;
        }

        echo $razorpayPayment->generateHtmlForm($url, $fields);
    }

    exit;
}

function getMerchantPreferences($api)
{
    try
    {
        $response = Requests::get($api->getBaseUrl() . 'preferences?key_id=' . $api->getKey());
    }
    catch (Exception $e)
    {
        echo 'CS Cart Error : ' . $e->getMessage();
    }

    $preferences = [];
    $preferences['is_hosted'] = false;

    if($response->status_code === 200)
    {
        $jsonResponse = json_decode($response->body, true);

        $preferences['image'] = $jsonResponse['options']['image'];
        if(empty($jsonResponse['options']['redirect']) === false)
        {
            $preferences['is_hosted'] = $jsonResponse['options']['redirect'];
        }
    }

    return $preferences;
}

?>
