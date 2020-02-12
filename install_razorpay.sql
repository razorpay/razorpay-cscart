REPLACE INTO cscart_payment_processors (`processor`,`processor_script`,`processor_template`,`admin_template`,`callback`,`type`) VALUES ('Razorpay','razorpay.php', 'views/orders/components/payments/cc_outside.tpl','razorpay/razorpay.tpl', 'Y', 'P');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','rzp_key_id','Key Id');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','rzp_key_secret','Key Secret');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','rzp_enabled_webhook','Enable Webhook');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','rzp_webhook_url','Webhook Url');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','rzp_webhook_secret','Webhook Secret');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_rzp_failed_order','No response from Razorpay has been received. Please contact the store staff and tell them the order ID:');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_rzp_pending','No response from Razorpay. Please check the payment using Client ID on razorpay dashboard. ');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_rzp_success','Payment Sucessful. You can check the payment using Client ID on razorpay dashboard. ');
REPLACE INTO cscart_language_values (`lang_code`,`name`,`value`) VALUES ('EN','text_rzp_success','Payment Sucessful. You can check the payment using Client ID on razorpay dashboard. ');
