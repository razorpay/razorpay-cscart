<?php

require_once __DIR__ . '/razorpay-sdk/Razorpay.php';
require_once __DIR__ . '/razorpay_common.inc';

use Razorpay\Api\Api;
use Razorpay\Api\Errors;
use Tygh\Settings;

class RZP_Webhook
{
    /**
     * Instance of the razorpay payments class
     * @var Razorpay
     */
    protected $razorpay;

    /**
     * API client instance to communicate with Razorpay API
     * @var Razorpay\Api\Api
     */
    protected $api;

    protected $paymentCurrency;


    /**
     * Event constants
     */
    const PAYMENT_AUTHORIZED    = 'payment.authorized';
    const PAYMENT_FAILED        = 'payment.failed';
    const ORDER_PAID            = 'order.paid';

    function __construct()
    {
        //$this->razorpay = new Razorpay(false);
        //$this->api = $this->razorpay->getRazorpayApiInstance();
    }

    /**
     * Process a Razorpay Webhook. We exit in the following cases:
     * - Successful processed
     * - Exception while fetching the payment
     *
     * It passes on the webhook in the following cases:
     * - invoice_id set in payment.authorized
     * - Invalid JSON
     * - Signature mismatch
     * - Secret isn't setup
     * - Event not recognized
     */
    public function process()
    {
        $post = file_get_contents('php://input');

        $data = json_decode($post, true);

        if (json_last_error() !== 0)
        {
            return;
        }

        //validate Order Id presence 
        if(isset($data['payload']['payment']['entity']['notes']['cs_order_id']) and 
            empty($data['payload']['payment']['entity']['notes']['cs_order_id']) === false)
        {
            $orderId = $data['payload']['payment']['entity']['notes']['cs_order_id'];
            //get the payment ID of this orderID
            $orderData = fn_get_order_info($orderId);
            if(is_array($orderData))
            {
                $rzpSettings = $orderData['payment_method']['processor_params'];
                $enabled = $rzpSettings['enabled_webhook'];

                $this->api = new Api($rzpSettings['key_id'], $rzpSettings['key_secret']);
                $this->paymentCurrency = $rzpSettings['currency'];
            }

        }
        else
        {
            //Set the validation error in response
            header('Status: 400 Not a valid Order data', true, 400);    
            exit;
        }

        if (($enabled === 'on') and
            (empty($data['event']) === false))
        {            
            if (isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) === true)
            {
               $razorpayWebhookSecret = $rzpSettings['webhook_secret'];

                if (empty($razorpayWebhookSecret) === false)
                {
                    try
                    {
                        $this->api->utility->verifyWebhookSignature($post,
                                                                $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'],
                                                                $razorpayWebhookSecret);
                    }
                    catch (Errors\SignatureVerificationError $e)
                    {
                        //Set the validation error in response
                        header('Status: 400 Signature Verification failed', true, 400);
                        exit;
                    }

                    switch ($data['event'])
                    {
                        case self::PAYMENT_AUTHORIZED:
                            return $this->paymentAuthorized($data);

                        case self::PAYMENT_FAILED:
                            return $this->paymentFailed($data);

                        case self::ORDER_PAID:
                            return $this->orderPaid($data, $orderData);

                        default:
                            return;
                    }

                }
            }
            
        }
    }

    /**
     * Does nothing for the main payments flow currently
     * @param array $data Webook Data
     */
    protected function paymentFailed(array $data)
    {
        return;
    }

    /**
     * Does nothing for the main payments flow currently
     * @param array $data Webook Data
     */
    protected function paymentAuthorized(array $data)
    {
       return;
    }

    /**
     * Handling order.paid event    
     * @param array $data Webook Data
     */
    protected function orderPaid(array $data, array $orderData)
    {
        $orderId = $data['payload']['payment']['entity']['notes']['cs_order_id'];

        $orderAmount = (int) number_format((fn_rzp_adjust_amount($orderData['total'], $this->paymentCurrency) * 100), 0, '.', '');

        if(($orderData['status'] === 'N') and 
            ($orderAmount === $data['payload']['payment']['entity']['amount']))
        {   
            $pp_response['order_status'] = 'P';
            $pp_response['reason_text'] = fn_get_lang_var('text_rzp_success');
            $pp_response['transaction_id'] = $orderId;
            $pp_response['client_id'] = $data['payload']['payment']['entity']['id'];

            fn_define('ORDER_MANAGEMENT', true);
            fn_finish_payment($orderId, $pp_response);
            
            exit;
        }
        
        // Graceful exit since payment is now processed.
        exit;
    }
}
