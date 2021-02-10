<?php
use Razorpay\Api\Api;

class RazorpayPayment
{
    //Define version of plugin
    const VERSION = '1.3.1';

    public function getSessionValue($key)
    {
        return $_SESSION[$key];
    }

    public function setSessionValues($sessionValues)
    {
        foreach($sessionValues as $key => $value)
        {
            $_SESSION[$key] = $value;
        }
    }

    public function getOrderData($orderId, $orderInfo, $processorData)
    {
        $data = array(
            'receipt'         => $orderId,
            'amount'          => fn_rzp_adjust_amount($orderInfo['total'], $processorData['processor_params']['currency'])*100,
            'currency'        => $processorData['processor_params']['currency'],
            'payment_capture' => 1
        );

        return $data;
    }

    public function generateHtmlForm($url, $fields)
    {
        if($fields['is_hosted'])
        {
            $html = '
                <!DOCTYPE html>
                <body>
                    <form action="'. $fields['api_url'] .'checkout/embedded" method="post" name="embedded_checkout_form" id="embedded_checkout_form">
                        <input type="hidden" name="key_id" value="'. $fields['key'] .'">
                        <input type="hidden" name="order_id" value="'. $fields['order_id'] .'">
                        <input type="hidden" name="image" value="">
                        <input type="hidden" name="name" value="'. $fields['name'] .'">
                        <input type="hidden" name="description" value="'. $fields['description'] .'">

                        <input type="hidden" name="prefill[name]" value="'. $fields['prefill']['name'] .'">
                        <input type="hidden" name="prefill[contact]" value="'. $fields['prefill']['contact'] .'">
                        <input type="hidden" name="prefill[email]" value="'. $fields['prefill']['email'] .'">

                        <input type="hidden" name="notes[opencart_order_id]" value="'. $fields['notes']['cs_order_id'] .'">

                        <input type="hidden" name="_[integration]" value="cscart">
                        <input type="hidden" name="_[integration_version]" value="'. $fields['_']['integration_version'] .'">
                        <input type="hidden" name="_[integration_parent_version]" value="'. $fields['_']['integration_parent_version'] .'">

                        <input type="hidden" name="callback_url" value="'. $fields['callback_url'] .'">
                        <input type="hidden" name="cancel_url" value="'. $fields['cancel_url'] .'">
                    </form>
                    <script type="text/javascript">
                        document.getElementById("embedded_checkout_form").submit();
                    </script>
                  </body>
                </html>
                ';
        }
        else
        {
            $json = json_encode($fields);

            $html = '
                    <!DOCTYPE html>
                    <body>
                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                        var data = ' . $json . ';
                        data.handler = function (transaction) {
                            document.getElementById("razorpay_payment_id").value = transaction.razorpay_payment_id;
                            document.getElementById("razorpay_signature").value = transaction.razorpay_signature;
                            document.getElementById("razorpay-form").submit();
                        };

                        data.modal = {};

                        data.modal.ondismiss = function() {
                            document.getElementById("razorpay-form").submit();
                        }
                        function razorpaySubmit(){
                            var rzp1 = new Razorpay(data);
                            rzp1.open();
                            rzp1.modal.options.backdropClose = false;
                        }
                        window.onload = function() {
                            razorpaySubmit();
                        };
                    </script>
                    <form name="razorpay-form" id="razorpay-form" action="' . $url . '" target="_parent" method="POST">
                        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" />
                        <input type="hidden" name="razorpay_signature" id="razorpay_signature"/>
                    </form>
                    </body>
                    </html>
                    ';
        }

        return $html;
    }

    public function processRazorpayResponse()
    {
        $razorpaySignature = null;
        if (isset($_POST['razorpay_signature']) === true)
        {
            $razorpaySignature = $_POST['razorpay_signature'];
        }

        $razorpayPaymentId = null;
        if (isset($_POST['razorpay_payment_id']) === true)
        {
            $razorpayPaymentId = $_POST['razorpay_payment_id'];
        }

        $razorpayOrderId = $_SESSION['razorpay_order_id'];

        $merchantOrderId = $_SESSION['merchant_order_id'];

        if ((empty($razorpaySignature) === false) and (empty($razorpayPaymentId) === false))
        {
            if (fn_check_payment_script('razorpay.php', $merchantOrderId, $processorData))
            {
                $keyId = $processorData['processor_params']['key_id'];

                $keySecret = $processorData['processor_params']['key_secret'];

                $orderInfo = fn_get_order_info($merchantOrderId);

                $amount = fn_rzp_adjust_amount($orderInfo['total'],
                    $processorData['processor_params']['currency'])*100;

                $attributes = array (
                    'razorpay_signature'  => $razorpaySignature,
                    'razorpay_order_id'   => $razorpayOrderId,
                    'razorpay_payment_id' => $razorpayPaymentId
                );

                $api = new Api($keyId, $keySecret);

                try
                {
                    $api->utility->verifyPaymentSignature($attributes);
                    $success = true;
                }
                catch (\Exception $e)
                {
                    $success = false;
                    $error = "PAYMENT_ERROR: Payment failed";
                }
            }
            else
            {
                $error = 'RAZORPAY_ERROR: Invalid Response';

                $success = false;
            }

            if ($success === true)
            {
                $pp_response['order_status'] = 'P';
                $pp_response['reason_text'] = fn_get_lang_var('text_rzp_success');
                $pp_response['transaction_id'] = $merchantOrderId;
                $pp_response['client_id'] = $razorpayPaymentId;

                fn_finish_payment($merchantOrderId, $pp_response);

                fn_order_placement_routines('route', $merchantOrderId);                
            }
            else
            {
                $this->handleFailedPayment($error, $razorpayPaymentId, $merchantOrderId);
            }
        }
        else if (isset($_POST['error']) === true)
        {
            $error = $_POST['error'];
            $message = 'An error occured. Description : ' . $error['description'] . '. Code : ' . $error['code'];
            if (isset($error['field']) === true)
            {
                $message .= 'Field : ' . $error['field'];
            }

            $this->handleFailedPayment($message, $razorpayPaymentId, $merchantOrderId);
        }
        else
        {
            fn_set_notification('E', __('error'), __('text_rzp_failed_order').$merchantOrderId);
            fn_order_placement_routines('checkout_redirect');
        }
    }


    protected function handleFailedPayment($errorMessage, $razorpayPaymentId, $merchantOrderId)
    {
        $pp_response['order_status'] = 'O';
        $pp_response['reason_text'] = fn_get_lang_var('text_rzp_pending').$errorMessage;
        $pp_response['transaction_id'] = $merchantOrderId;
        $pp_response['client_id'] = $razorpayPaymentId;

        fn_finish_payment($merchantOrderId, $pp_response);
        fn_set_notification('E', __('error'), __('text_rzp_failed_order').$merchantOrderId);
        fn_order_placement_routines('checkout_redirect');
    }

    public function getButton()
    {
       $url = fn_url("index.php?dispatch=checkout.process_payment&clicked=true", AREA, 'current');

       $html = '<!DOCTYPE html>
                <body>
                <a href="' . $url . '">
                    <button id="mybutton" type="button"
                        style="background-color:#ff5319;height:22px:width:150px;border: none;
                        color: white;font-size: 16px;padding: 6px 7px;">SUBMIT MY ORDER
                    </button>
                </a>
                </body>
                </html>';

        return $html;
    }
}

?>
