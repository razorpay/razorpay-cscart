<?php 
use Tygh\Registry;
use Razorpay\Api\Api;
require_once Registry::get('config.dir.payments') .'razorpay/razorpay-sdk/Razorpay.php';


if ($_REQUEST['dispatch'] == 'razorpay.manage')
{
   if($_SERVER['REQUEST_METHOD'] === 'POST')
   {
      $keyId = $_REQUEST['keyid'];
      $keySecret = $_REQUEST['keysecret'];
      $webhookUrl = "http".(isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] === 'on' ? "s" : "") 
                     . "://$_SERVER[HTTP_HOST]"
                     ."/cscart/index.php?dispatch=payment_notification.rzp_webhook&payment=razorpay";
      $webhookSecret = generateSecret();
      $webhookExist = false;
      $enabled = true;
      
   
      $supportedWebhookEvents  = array(
         'payment.authorized'
      );
      $defaultWebhookEvents = array(
         'payment.authorized' => true,
         'order.paid'         => true
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

         // Update processor_params in database (to update webhook_secret)
         $processorParams = fetchProcessorParams();
         $processorParams['webhook_secret'] = $webhookSecret;
         updateDbProcessorParams($processorParams);
         die();
      }
   
}

function generateSecret(){
   $alphanumericString = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-=~!@#$%^&*()_+,./<>?;:[]{}|abcdefghijklmnopqrstuvwxyz';
   $secret = substr(str_shuffle($alphanumericString), 0, 20);

   return $secret;
}

function updateDbProcessorParams($processorParams){
   $processorId = db_get_row('SELECT * FROM ?:payment_processors WHERE processor LIKE ?l OR processor LIKE ?l', "razorpay", "Razorpay")['processor_id'];

   db_query('UPDATE ?:payments SET processor_params=?s WHERE processor_id = ?i', serialize($processorParams), $processorId);
}

function fetchProcessorParams(){
   $processorId = db_get_row('SELECT * FROM ?:payment_processors WHERE processor LIKE ?l OR processor LIKE ?l', "razorpay", "Razorpay")['processor_id'];
   $processorParams = db_get_array('SELECT * FROM ?:payments');

   $processorParamsRzp = "";
   foreach($processorParams as $key=>$row)
   {
       if ($row['processor_id'] === $processorId)
       {
           $processorParamsRzp = unserialize($row['processor_params']);
       }
   }
   return $processorParamsRzp;
}
?>
