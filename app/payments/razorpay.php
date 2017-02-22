<?php
use Tygh\Registry;
use Razorpay\Api\Api;
include_once ('razorpay/razorpay_common.inc');
require_once ('razorpay-sdk/Razorpay.php');

if ( !defined('AREA') ) { die('Access denied'); }

// Return from payment
if (defined('PAYMENT_NOTIFICATION')) {
    if ($mode == 'return' && !empty($_REQUEST['razorpay_signature'])) {
        if (isset($view) === false)
        {
            $view = Registry::get('view');
        }

        $view->assign('order_action', __('placing_order'));
        $view->display('views/orders/components/placing_order.tpl');
        fn_flush();

        $razorpay_signature = $_REQUEST['razorpay_signature'];
        $razorpay_payment_id = $_REQUEST['razorpay_payment_id'];
        $orderId = fn_rzp_place_order($_SESSION['order_id']);

        if(!empty($razorpay_signature) and !empty($razorpay_payment_id)){
            if (fn_check_payment_script('razorpay.php', $orderId, $processor_data)) {
                $key_id = $processor_data['processor_params']['key_id'];
                $key_secret = $processor_data['processor_params']['key_secret'];
                $razorpay_order_id = $_SESSION['razorpay_order_id'];
                $api = new Api($key_id, $key_secret);
                $payment = $api->payment->fetch($razorpay_payment_id);
                $signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret);

                if (hash_equals($signature , $razorpay_signature)){
                    $success = true;
                }
                else {
                    $error = 'RAZORPAY_ERROR: Invalid Response';
                    $success = false;
                    $error = "PAYMENT_ERROR: Payment failed";
                }

                if($success === true){
                    $pp_response['order_status'] = 'P';
                    $pp_response['reason_text'] = fn_get_lang_var('text_rzp_success');
                    $pp_response['transaction_id'] = @$order;
                    $pp_response['client_id'] = $razorpay_payment_id;

                    fn_finish_payment($orderId, $pp_response);
                    fn_order_placement_routines('route', $orderId);
                }
                else {
                    $pp_response['order_status'] = 'O';
                    $pp_response['reason_text'] = fn_get_lang_var('text_rzp_pending').$error;
                    $pp_response['transaction_id'] = @$order;
                    $pp_response['client_id'] = $razorpay_payment_id;

                    fn_finish_payment($orderId, $pp_response);
                    fn_set_notification('E', __('error'), __('text_rzp_failed_order').$orderId);
                    fn_order_placement_routines('checkout_redirect');
                }

            }
        }
        else {
            fn_set_notification('E', __('error'), __('text_rzp_failed_order').$orderId);
            fn_order_placement_routines('checkout_redirect');
        }
    }
    exit;
}
else {
    $url = fn_url("payment_notification.return?payment=razorpay", AREA, 'current');
    $checkout_url = "https://checkout.razorpay.com/v1/checkout.js";
    $api = new Api($processor_data['processor_params']['key_id'], $processor_data['processor_params']['key_secret']);
    $data = array(
                'receipt' => $order_id,
                'amount' => fn_rzp_adjust_amount($order_info['total'], $processor_data['processor_params']['currency'])*100,
                'currency' => $processor_data['processor_params']['currency'],
    );
    $data['payment_capture'] = 1;
    $razorpay_order = $api->order->create($data);
    $razorpayOrderId = $razorpay_order['id'];
    $_SESSION['razorpay_order_id'] = $razorpayOrderId;
    $_SESSION['order_id'] = $order_id;
    $fields = array(
        'key' => $processor_data['processor_params']['key_id'],
        'amount' => fn_rzp_adjust_amount($order_info['total'], $processor_data['processor_params']['currency'])*100,
        'currency' => $processor_data['processor_params']['currency'],
        'description' => "Order# ".$order_id,
        'name' => Registry::get('settings.Company.company_name'),
        'customer_name' => $order_info['b_firstname']." ".$order_info['b_lastname'],
        'customer_email' => $order_info['email'],
        'customer_phone' => $order_info['phone'],
        'cs_order_id'  => $order_id,
        'order_id' => $razorpayOrderId,
    );


    $html = '<form name="razorpay-form" id="razorpay-form" action="'.$url.'" target="_parent" method="POST">
                <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" />
                <input type="hidden" name="razorpay_signature" id="razorpay_signature"/>
            </form>';
    
    $js = '<script>';

    $js .= "var razorpay_options = {
                'key': '".$fields['key']."',
                'amount': '".$fields['amount']."',
                'name': '".$fields['name']."',
                'description': 'Order# ".$fields['order_id']."',
                'currency': '".$fields['currency']."',
                'handler': function (transaction) {
                    document.getElementById('razorpay_payment_id').value = transaction.razorpay_payment_id;
                    document.getElementById('razorpay_signature').value = transaction.razorpay_signature;
                    document.getElementById('razorpay-form').submit();
                },
                'prefill': {
                    'name': '".$fields['customer_name']."',
                    'email': '".$fields['customer_email']."',
                    'contact': '".$fields['customer_phone']."'
                },
                notes: {
                    'cs_order_id': '".$fields['order_id']."'
                },
                'order_id': '".$fields['order_id']."',
            };
            
            function razorpaySubmit(){                  
                var rzp1 = new Razorpay(razorpay_options);
                rzp1.open();
                rzp1.modal.options.backdropClose = false;
            }    
            
            var rzp_interval = setInterval(function(){
                if (typeof window[\"Razorpay\"] != \"undefined\")
                {
                    setTimeout(function(){ razorpaySubmit(); }, 500);
                    clearInterval(rzp_interval);
                }
            }, 500);
            ";

    $js .= '</script>';

    if (!$fields['amount']) {
        echo __('text_unsupported_currency');
        exit;
    }

echo <<<EOT
    <script src="{$checkout_url}"></script>
    {$html}
    {$js}
</body>
</html>
EOT;
exit;
}

?>