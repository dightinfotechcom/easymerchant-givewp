/**
 * Start with a Self-Executing Anonymous Function (IIFE) to avoid polluting and conflicting with the global namespace (encapsulation).
 * @see https://developer.mozilla.org/en-US/docs/Glossary/IIFE
 *
 * This won't be necessary if you're using a build system like webpack.
 */
(() => {
  /**
   * Example of a gateway api.
   */
  const easyMerchantGatewayApi = {
    publishable_key: "",
    secureData: "",
    init(publishable_key) {
      this.publishable_key = publishable_key;

      easyUIConnect.easyMerchantOnInit();
    },
    async submit(values) {
      if (!this.publishable_key) {
        return {
          error: "EasyMerchantGatewayApi publishable_key is required.",
        };
      }
      // if (this.secureData.length === 0) {
      //   return {
      //     error: "EasyMerchantGatewayApi data is required.",
      //   };
      // }

      try {
        easyMerchant.bindPaymentDetails(
          {
            publishable_key: this.publishable_key,
            amount: values.amount,
            email: values.email,
            description: "givewp donation",
          },
          function (response) {
            if (response.status === 200 && response.charge_id != "") {
              return {
                transactionId: response.charge_id,
              };
            }
          }
        );
      } catch (error) {
        console.log(error);

        return { error };
      }

      return { error: window.wp.i18n.__("Unable to process payment.", 'easymerchant-givewp') };
    },
  };

  /**
   * Example of rendering gateway fields (without jsx).
   *
   * This renders a simple div with a label and input.
   *
   * @see https://react.dev/reference/react/createElement
   */
  function EasyMerchantGatewayFields() {
    return window.wp.element.createElement(
      "div",
      {},
        window.wp.element.createElement(
          "p",
          {
            className: "easymerchant-gateway-fields",
          },
          window.wp.i18n.__("Continue to donate", "easymerchant-givewp")
        )
    );
  }

  /**
   * Example of a front-end gateway object.
   */
  const EasyMerchantGateway = {
    id: "easymerchant-gateway",
    initialize() {
      const { publishable_key } = this.settings;

      easyMerchantGatewayApi.init(publishable_key);
    },
    // donor clicks donate button and form values are passed to this function
    async beforeCreatePayment(values) {
      const { transactionId, error: submitError } =
        await easyMerchantGatewayApi.submit(values);

      if (submitError) {
        throw new Error(submitError);
      }

      return {
        "easymerchant-charge-id": transactionId,
      };
    },
    Fields() {
      return window.wp.element.createElement(EasyMerchantGatewayFields);
    },
  };

  /**
   * The final step is to register the front-end gateway with GiveWP.
   */
  window.givewp.gateways.register(EasyMerchantGateway);
})();
