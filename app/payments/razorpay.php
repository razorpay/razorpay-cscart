<?php
use Tygh\Registry;

include_once ('razorpay/razorpay_common.inc');

if ( !defined('AREA') ) { die('Access denied'); }

// Return from payment
if (defined('PAYMENT_NOTIFICATION')) {
    if ($mode == 'return' && !empty($_REQUEST['merchant_order_id'])) {
        if (isset($view) === false)
        {
            $view = Registry::get('view');
        }

        $view->assign('order_action', __('placing_order'));
        $view->display('views/orders/components/placing_order.tpl');
        fn_flush();

        $merchant_order_id = fn_rzp_place_order($_REQUEST['merchant_order_id']);
        $razorpay_payment_id = $_REQUEST['razorpay_payment_id'];

        if(!empty($merchant_order_id) and !empty($razorpay_payment_id)){
            if (fn_check_payment_script('razorpay.php', $merchant_order_id, $processor_data)) {
                $key_id = $processor_data['processor_params']['key_id'];
                $key_secret = $processor_data['processor_params']['key_secret'];
                $order_info = fn_get_order_info($merchant_order_id);
                $amount = fn_rzp_adjust_amount($order_info['total'], $processor_data['processor_params']['currency'])*100;

                $pp_response = array();
                $success = false;
                $error = "";

                try {
                    $url = 'https://api.razorpay.com/v1/payments/'.$razorpay_payment_id.'/capture';
                    $fields_string="amount=$amount";

                    //cURL Request
                    $ch = curl_init();

                    //set the url, number of POST vars, POST data
                    curl_setopt($ch,CURLOPT_URL, $url);
                    curl_setopt($ch,CURLOPT_USERPWD, $key_id . ":" . $key_secret);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_POST, 1);
                    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);

                    //execute post
                    $result = curl_exec($ch);
                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


                    if($result === false) {
                        $success = false;
                        $error = 'Curl error: ' . curl_error($ch);
                    }
                    else {
                        $response_array = json_decode($result, true);
                        //Check success response
                        if($http_status === 200 and isset($response_array['error']) === false){
                            $success = true;    
                        }
                        else {
                            $success = false;

                            if(!empty($response_array['error']['code'])) {
                                $error = $response_array['error']['code'].":".$response_array['error']['description'];
                            }
                            else {
                                $error = "RAZORPAY_ERROR:Invalid Response <br/>".$result;
                            }
                        }
                    }
                        
                    //close connection
                    curl_close($ch);
                }
                catch (Exception $e) {
                    $success = false;
                    $error ="CSCART_ERROR:Request to Razorpay Failed";
                }

                if($success === true){
                    $pp_response['order_status'] = 'P';
                    $pp_response['reason_text'] = fn_get_lang_var('text_rzp_success');
                    $pp_response['transaction_id'] = @$order;
                    $pp_response['client_id'] = $razorpay_payment_id;

                    fn_finish_payment($merchant_order_id, $pp_response);
                    fn_order_placement_routines('route', $merchant_order_id);
                }
                else {
                    $pp_response['order_status'] = 'O';
                    $pp_response['reason_text'] = fn_get_lang_var('text_rzp_pending').$error;
                    $pp_response['transaction_id'] = @$order;
                    $pp_response['client_id'] = $razorpay_payment_id;

                    fn_finish_payment($merchant_order_id, $pp_response);
                    fn_set_notification('E', __('error'), __('text_rzp_failed_order').$merchant_order_id);
                    fn_order_placement_routines('checkout_redirect');
                }

            }
        }
        else {
            fn_set_notification('E', __('error'), __('text_rzp_failed_order').$_REQUEST['merchant_order_id']);
            fn_order_placement_routines('checkout_redirect');
        }
    }
    exit;
}
else {
    $url = fn_url("payment_notification.return?payment=razorpay", AREA, 'current');
    $checkout_url = "https://checkout.razorpay.com/v1/checkout.js";

    $fields = array(
        'key' => $processor_data['processor_params']['key_id'],
        'amount' => fn_rzp_adjust_amount($order_info['total'], $processor_data['processor_params']['currency'])*100,
        'currency' => $processor_data['processor_params']['currency'],
        'description' => "Order# ".$order_id,
        'name' => Registry::get('settings.Company.company_name'),
        'customer_name' => $order_info['b_firstname']." ".$order_info['b_lastname'],
        'customer_email' => $order_info['email'],
        'customer_phone' => $order_info['phone'],
        'order_id' => $order_id
    );

    $html = '<form name="razorpay-form" id="razorpay-form" action="'.$url.'" target="_parent" method="POST">
                <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" />
                <input type="hidden" name="merchant_order_id" id="order_id" value="'.$fields['order_id'].'"/>
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
                netbanking: true
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