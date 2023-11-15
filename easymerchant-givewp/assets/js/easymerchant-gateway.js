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
    secureData: {
      publishable_key: "",
      charge_id: "",
    },
    async response() {
        return new Promise((resolve, reject) => {
            const interval = setInterval(() => {
                if (this.secureData.charge_id !== "") {
                    clearInterval(interval);
                    resolve({charge_id: this.secureData.charge_id});
                }

                if (!this.secureData.publishable_key) {
                    clearInterval(interval);
                    reject(window.wp.i18n.__("EasyMerchantGatewayApi publishable_key is required."));
                }

                if (this.error) {
                    clearInterval(interval);
                    reject(JSON.stringify(this.error));
                }
            }, 5000);
        });
    },
    init(publishable_key) {
      this.secureData.publishable_key = publishable_key;

      easyUIConnect.easyMerchantOnInit();
    },
    submit(values) {
      try {
        easyMerchant.bindPaymentDetails(
          {
            publishable_key: easyMerchantGatewayApi.secureData.publishable_key,
            amount: values.amount,
            email: values.email,
            description: "givewp donation",
          },
          function (response) {
            if (response.charge_id !== "") {
              easyMerchantGatewayApi.secureData.charge_id = response.charge_id;
            }
          }
        );
      } catch (error) {
        easyMerchantGatewayApi.error = error;
      }
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
          {},
          window.wp.i18n.__("Make your donations quickly and securely with EasyMerchant. How it works: An Easymerchant window will open after you click the Donate Now button where you can securely make your donation. You will then be brought back to this page to view your receipt.", "easymerchant-givewp"),
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
    async beforeCreatePayment(values) {
      easyMerchantGatewayApi.submit(values);
      const { charge_id, error } = await easyMerchantGatewayApi.response();

      if (error) {
        throw new Error(error);
      }

      return {
        "easymerchant-hosted-checkout": true,
        "easymerchant-charge-id": charge_id,
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
