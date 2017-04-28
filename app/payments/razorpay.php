<?php
use Tygh\Registry;
use Razorpay\Api\Api;

include_once ('razorpay/razorpay_common.inc');
require_once ('razorpay-sdk/Razorpay.php');
require_once('RazorpayPayment.php');

if ( !defined('AREA') ) { die('Access denied'); }

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

    $url = fn_url("payment_notification.return?payment=razorpay", AREA, 'current');

    $data = $razorpayPayment->getOrderData($order_id, $order_info, $processor_data);

    $keyId = $processor_data['processor_params']['key_id'];

    $keySecret = $processor_data['processor_params']['key_secret'];

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
            'cs_order_id' => $order_id
        ),
        'order_id' => $razorpayOrderId,
    );

    if (!$fields['amount'])
    {
        echo __('text_unsupported_currency');
        exit;
    }

    //checks for iframe mode. In iframe mode payment flow goes through another payment button
    if ((defined('IFRAME_MODE') === true) and (empty($_GET['clicked']) === true))
    {
        echo $razorpayPayment->getButton();
    }
    else
    {
        echo $razorpayPayment->generateHtmlForm($url, json_encode($fields));
    }
exit;
}

?>