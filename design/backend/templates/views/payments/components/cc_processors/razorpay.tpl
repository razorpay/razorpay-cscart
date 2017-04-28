{* $Id$ *}

<div class="form-field">
    <label for="key_id">{__("key_id")}:</label>
    <input type="text" name="payment_data[processor_params][key_id]" id="key_id" value="{$processor_params.key_id}" class="input-text" />
</div>

<div class="form-field">
    <label for="key_secret">{__("key_secret")}:</label>
    <input type="text" name="payment_data[processor_params][key_secret]" id="key_secret" value="{$processor_params.key_secret}" class="input-text" />
</div>

<div class="form-field">
    <label for="currency">{__("currency")}:</label>
    <select name="payment_data[processor_params][currency]" id="currency">
        <option value="INR" {if $processor_params.currency == "INR"}selected="selected"{/if}>{__("currency_code_inr")}</option>
    </select>
</div>
<div class="form-field">
    <label for="iframe_mode_{$payment_id}">{__("iframe_mode")}:</label>
    <select name="payment_data[processor_params][iframe_mode]" id="iframe_mode_{$payment_id}">
        <option value="Y" {if $processor_params.iframe_mode == "Y"}selected="selected"{/if}>{__("enabled")}</option>
        <option value="N" {if $processor_params.iframe_mode == "N"}selected="selected"{/if}>{__("disabled")}</option>
    </select>
</div>
