(function () {
    'use strict';

    var settings = window.wc.wcSettings.getSetting('store_hub_stripe_data', {});
    var createElement = window.wp.element.createElement;
    var decode = window.wp.htmlEntities.decodeEntities;
    var title = decode(settings.title || 'Credit / Debit Card');
    var description = decode(settings.description || 'Pay securely with Stripe.');

    var label = createElement('span', null, title);
    var content = createElement('div', null, description);

    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'store_hub_stripe',
        label: label,
        content: content,
        edit: content,
        canMakePayment: function () {
            return true;
        },
        ariaLabel: title,
        supports: {
            features: settings.supports || ['products']
        }
    });
}());
