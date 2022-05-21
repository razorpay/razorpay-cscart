<?php 
use Tygh\Registry;
use Razorpay\Api\Api;
require_once Registry::get('config.dir.payments') .'razorpay/razorpay-sdk/Razorpay.php';


if ($_REQUEST['dispatch'] == 'razorpay.manage')
{ 
   $keyId = $_REQUEST['keyid'];
   $keySecret = $_REQUEST['keysecret'];
   $webhookUrl = 'https://369f-2405-201-c022-134-7153-2b0b-6813-7f7b.ngrok.io/cscart/index.php?dispatch=payment_notification.rzp_webhook&payment=razorpay'; 
   $webhookSecret = $_REQUEST['secret'];
   $webhookExist = false;
   $enabled = true;
   
  
   $supportedWebhookEvents  = array(
      'payment.authorized',
      
  );
  $defaultWebhookEvents = array(
      'payment.authorized' => true,
      'payment.failed' => true
  );
  $domain = parse_url($webhookUrl, PHP_URL_HOST);
  $domain_ip = gethostbyname($domain);
  
  if (!filter_var($domain_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
  {
      die;
  }
   $api = new Api($keyId, $keySecret);
   $skip = 0;
   $count = 10;
      do {
         $webhook = $api->request->request("GET", "webhooks?count=".$count."&skip=".$skip);
         $skip += 10;
         if ($webhook['count'] > 0)
         {
            foreach ($webhook['items'] as $key => $value)
            {  
               if ($value['url'] === $webhookUrl)
               { 
                     foreach ($value['events'] as $evntkey => $evntval)
                     {
                        if (($evntval == 1) and  
                           (in_array($evntkey, $supportedWebhookEvents) === true))
                        {
                           $defaultWebhookEvents[$evntkey] =  true;
                        }
                     }
                     $webhookExist  = true;
                     $webhookId     = $value['id'];
               }    
            }
         }  
   } while ( $webhook['count'] >= 10);
   

   $data = [
      'url'    => $webhookUrl,
      'active' => $enabled,
      'events' => $defaultWebhookEvents,
      'secret' => $webhookSecret,
   ];

      if ($webhookExist)
      {
         $api->request->request('PUT', "webhooks/".$webhookId, $data);
      }
      else
      {
         $api->request->request('POST', "webhooks/", $data);
      }
  die();
}


?>