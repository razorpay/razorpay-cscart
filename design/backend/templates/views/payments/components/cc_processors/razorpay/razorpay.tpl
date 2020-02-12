{* $Id$ *}

{include file="views/payments/components/cc_processors/razorpay/razorpay_currency.tpl"}
{assign var="currencies" value=""|fn_get_currencies}
{assign var="webhook_url" value="payment_notification.rzp_webhook?payment=razorpay"|fn_url:"C":"current"}

<div class="form-field">
    <label for="key_id">{__("rzp_key_id")}:</label>
    <input type="text" name="payment_data[processor_params][key_id]" id="key_id" value="{$processor_params.key_id}" class="input-text" />
</div>

<div class="form-field">
    <label for="key_secret">{__("rzp_key_secret")}:</label>
    <input type="text" name="payment_data[processor_params][key_secret]" id="key_secret" value="{$processor_params.key_secret}" class="input-text" />
</div>

<div class="form-field">
    <label  for="currency">{__("currency")}:</label>
        <select name="payment_data[processor_params][currency]" id="currency">
            {foreach from=$razorpay_currencies key="key" item="currency"}
                <option value="{$key}" {if !isset($currencies.$key)} disabled="disabled"{/if} {if $processor_params.currency == $key} selected="selected"{/if}>{__({$currency})}{$currencies.$key}</option>
            {/foreach}
        </select>
</div>

<div class="form-field">
    <label for="enabled_webhook">{__("rzp_enabled_webhook")}:</label>
    <select name="payment_data[processor_params][enabled_webhook]" id="enabled_webhook">
        <option value="off" {if $processor_params.enabled_webhook == "off"}selected="selected"{/if}>{__("no")}</option>
        <option value="on" {if $processor_params.enabled_webhook == "on"}selected="selected"{/if}>{__("yes")}</option>
     </select>
     <div style="font-weight: bold; font-style: italic;">If set to Yes, please set the webhook secret below as well</div>
</div>
<div class="form-field">
    <label for="webhook_url">{__("rzp_webhook_url")}:</label>
    <input type="text" readonly name="webhook_url" id="webhook_url" value="{$webhook_url}" class="input-text" style="font-weight: bold;"/>
    <span class='copy-to-clipboard' style='background-color: #337ab7; color: white; border: none;cursor: pointer;margin:4px; padding: 2px 4px; text-decoration: none;'>Copy</span>
    <div style="font-weight: bold; font-style: italic;">Set the above URL in webhooks setting in Razorpay dashboard. Reference <a href="https://razorpay.com/docs/webhooks/">webhooks</a></div>
    <script type='text/javascript'>
            $(function() {
                $('.copy-to-clipboard').click(function() {
                    var copyText = document.getElementById('webhook_url');
                    copyText.focus();
                    copyText.select();
                    document.execCommand('Copy');
                    $('.copy-to-clipboard').text('Copied to clipboard.');
                });
            });
    </script>
<div>

<div class="form-field">
    <label for="webhook_secret">{__("rzp_webhook_secret")}:</label>
    <input type="text" name="payment_data[processor_params][webhook_secret]" id="webhook_secret" value="{$processor_params.webhook_secret}" class="input-text" />
    <div style="font-weight: bold; font-style: italic;">This field has to match with the same secret, set in <a href="https://dashboard.razorpay.com/#/app/webhooks">https://dashboard.razorpay.com/#/app/webhooks</a></div>
</div>

<div class="form-field">
    <label for="iframe_mode_{$payment_id}">{__("iframe_mode")}:</label>
    <select name="payment_data[processor_params][iframe_mode]" id="iframe_mode_{$payment_id}">
        <option value="Y" {if $processor_params.iframe_mode == "Y"}selected="selected"{/if}>{__("enabled")}</option>
        <option value="N" {if $processor_params.iframe_mode == "N"}selected="selected"{/if}>{__("disabled")}</option>
    </select>
</div>
