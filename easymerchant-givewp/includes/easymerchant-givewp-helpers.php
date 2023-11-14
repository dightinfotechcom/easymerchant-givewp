<?php

function easymerchant_output_redirect_notice($formId, $args)
{
    return sprintf(

        '
					
					<fieldset class="no-fields">

						<p style="text-align: center;"><b>%1$s</b></p>

						<p style="text-align: center;">

							<b>%2$s</b> %3$s

						</p>

					</fieldset>

					',

        esc_html__('Make your donations quickly and securely with EasyMerchant', 'give'),

        esc_html__('How it works:', 'give'),

        esc_html__(
            'An Easymerchant window will open after you click the Donate Now button where you can securely make your donation. You will then be brought back to this page to view your receipt.',
            'give'
        )

    );
}

function easymerchant_givewp_custom_credit_card_form($form_id)
{
    ob_start();

    /**
     * Fires while rendering credit card info form, before the fields.
     *
     * @param  int  $form_id  The form ID.
     *
     * @since 1.0
     */
    do_action('give_before_cc_fields', $form_id);

    ?>
  <fieldset id="give_cc_fields-<?php
  echo $form_id; ?>" class="give-do-validate">
    <legend><?php
        echo apply_filters('give_credit_card_fieldset_heading', esc_html__('Credit Card Info', 'give')); ?></legend>
      <?php
      if (is_ssl()) : ?>
        <div id="give_secure_site_wrapper-<?php
        echo $form_id; ?>">
          <span class="give-icon padlock"></span>
          <span><?php
              _e('This is a secure SSL encrypted payment.', 'give'); ?></span>
        </div>
      <?php
      endif; ?>
    <p id="give-card-number-wrap-<?php
    echo $form_id; ?>" class="form-row form-row-two-thirds form-row-responsive">
      <label for="card_number-<?php
      echo $form_id; ?>" class="give-label">
          <?php
          _e('Card Number', 'give'); ?>
        <span class="give-required-indicator">*</span>
          <?php
          echo Give()->tooltips->render_help(
              __('The (typically) 16 digits on the front of your credit card.', 'give')
          ); ?>
        <span class="card-type"></span>
      </label>
      <input type="hidden" name="card_number_easy" value="" />
      <input type="tel" autocomplete="off" name="card_number" id="card_number-<?php
      echo $form_id; ?>" class="card-number give-input required" placeholder="<?php
      _e('Card Number', 'give'); ?>" required aria-required="true" onkeyup="card_number_hidden()" />
    </p>

    <p id="give-card-cvc-wrap-<?php
    echo $form_id; ?>" class="form-row form-row-one-third form-row-responsive">
      <label for="card_cvc-<?php
      echo $form_id; ?>" class="give-label">
          <?php
          _e('CVC', 'give'); ?>
        <span class="give-required-indicator">*</span>
          <?php
          echo Give()->tooltips->render_help(
              __('The 3 digit (back) or 4 digit (front) value on your card.', 'give')
          ); ?>
      </label>

      <input type="tel" size="4" autocomplete="off" name="card_cvc" id="card_cvc-<?php
      echo $form_id; ?>" class="card-cvc give-input required" placeholder="<?php
      _e('CVC', 'give'); ?>" required aria-required="true" />
    </p>

    <p id="give-card-name-wrap-<?php
    echo $form_id; ?>" class="form-row form-row-two-thirds form-row-responsive">
      <label for="card_name-<?php
      echo $form_id; ?>" class="give-label">
          <?php
          _e('Cardholder Name', 'give'); ?>
        <span class="give-required-indicator">*</span>
          <?php
          echo Give()->tooltips->render_help(__('The name of the credit card account holder.', 'give')); ?>
      </label>

      <input type="text" autocomplete="off" name="card_name" id="card_name-<?php
      echo $form_id; ?>" class="card-name give-input required" placeholder="<?php
      esc_attr_e('Cardholder Name', 'give'); ?>" required aria-required="true" />
    </p>
      <?php
      /**
       * Fires while rendering credit card info form, before expiration fields.
       *
       * @param  int  $form_id  The form ID.
       *
       * @since 1.0
       */
      do_action('give_before_cc_expiration');
      ?>
    <p class="card-expiration form-row form-row-one-third form-row-responsive">
      <label for="card_expiry-<?php
      echo $form_id; ?>" class="give-label">
          <?php
          _e('Expiration', 'give'); ?>
        <span class="give-required-indicator">*</span>
          <?php
          echo Give()->tooltips->render_help(
              __('The date your credit card expires, typically on the front of the card.', 'give')
          ); ?>
      </label>

      <input type="hidden" id="card_exp_month-<?php
      echo $form_id; ?>" name="card_exp_month" class="card-expiry-month" />
      <input type="hidden" id="card_exp_year-<?php
      echo $form_id; ?>" name="card_exp_year" class="card-expiry-year" />

      <input type="tel" autocomplete="off" name="card_expiry" id="card_expiry-<?php
      echo $form_id; ?>" class="card-expiry give-input required" placeholder="<?php
      esc_attr_e('MM / YYYY', 'give'); ?>" required aria-required="true" />
    </p>

  </fieldset>
  <script>
    function card_number_hidden() {
      let ccNumber = document.querySelector('.card-number').value;
      document.querySelector('input[name=\'card_number_easy\']').value = ccNumber;
    }
  </script>
    <?php


    return ob_get_clean();
}