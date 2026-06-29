const customizeWidget = paylineData.customizeWidget === 'yes';
const ctaLabel = paylineData.ctaButton;
const textUnderCta = paylineData.textUnderCta;
let beforePaymentcanPass = false;
let lastClickedElement = null;

function isBlockCheckout() {
    return (
        typeof window.wc?.blocksCheckout !== 'undefined' ||
        document.querySelector('.wp-block-woocommerce-checkout') !== null
    );
}

/*function getTermsElement() {
    return document.getElementById('terms-and-conditions') || document.getElementById('terms');
}*/

function displayLoader() {
    jQuery('#PaylineWidget').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    });
}

function hideLoader() {
    jQuery('#PaylineWidget').unblock();
}

window.eventDidshowstate = function (e) {
    if ( e.state && e.state === "PAYMENT_METHODS_LIST" && customizeWidget ) {
        if (ctaLabel != "") {
            jQuery(".PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn").html(ctaLabel.replace("{{amount}}", Payline.Api.getContextInfo("PaylineFormattedAmount")));
        }

        if (textUnderCta) {
            jQuery(".PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn").after(jQuery("<p>").html(textUnderCta).addClass("pl-text-under-cta"))
        }
    }

    jQuery('#PaylineWidget').on('click', function (e) {
        lastClickedElement = e.target;
    });

    if (isBlockCheckout() === false) {
        jQuery(document.body).on('payment_method_selected', togglePlaceOrderButton);
    }
}

function validateBeforePayment() {
    beforePaymentcanPass = false;
    displayLoader();
    
    if ( isBlockCheckout() ) {
        const validation = window.paylineBlockValidateCheckout;
        if ( typeof validation === 'function' ) {
            Promise.resolve(validation()).then((isValid) => {
                hideLoader();

                if ( isValid.hasError === false ) {
                    beforePaymentcanPass = true;
                    lastClickedElement.click();
                }
            });
        }
    } else {
        const formData = jQuery('form[name="checkout"]').serialize() + '&nonce=' + encodeURIComponent(paylineData.payline_checkout_validator_nonce);
        jQuery.ajax({
            url: '/?wc-ajax=payline_checkout_validator',
            method: 'POST',
            data: formData,
            success(response) {
                if (response.success) {
                    beforePaymentcanPass = true;
                    lastClickedElement.click();
                } else {
                    jQuery('#place_order').click();
                }
            },
            error(xhr, status, error) {
                console.warn('Erreur lors de la requête AJAX :', status, error);
            },
            complete() {
                lastClickedElement = null;
                hideLoader();
            }
        });
    }
}

window.beforePayement = function () {
    if (beforePaymentcanPass === true) {
        return true;
    }

    //--> BeforePayment est lancé avant qu'on ai le temps de savoir qui est le trigger.
    window.setTimeout(() => {
        validateBeforePayment();
    }, 0);

    return false;
}

hideReceivedContext = function() {
    jQuery(".storefront-breadcrumb").hide();
    jQuery(".order_details").hide();
    jQuery("h1.entry-title").html("'. __('Payment', 'payline') .'")
    jQuery("#site-header-cart").hide();
};

eventFinalstatehasbeenreached= function (e) {
    if ( e.state === "PAYMENT_SUCCESS" ) {
        //--> Redirect to success page
        //--> Ticket is hidden by CSS
        //--> Wait for DOM update to simulate a click on the ticket confirmation button
        window.setTimeout(() => {
            const ticketConfirmationButton = document.getElementById("pl-ticket-default-ticket_btn");
            if ( ticketConfirmationButton ) {
                ticketConfirmationButton.click();
            }
        }, 0);
    } else if (["PAYMENT_CANCELED", "PAYMENT_FAILURE", "TOKEN_EXPIRED"].includes(e.state)) {
        const resetTokenUrl = new URL(Payline.Api.getCancelAndReturnUrls().cancelUrl);
        const searchParams = new URLSearchParams(resetTokenUrl.search);
        searchParams.set('url_type', 'resetToken');
        window.location.href = `${resetTokenUrl.protocol}//${resetTokenUrl.hostname}${resetTokenUrl.pathname}?${searchParams.toString()}`;
    }
};

// To delete if \WC_Abstract_Payline::generate_payline_form
cancelPaylinePayment = function ()
{
    Payline.Api.endToken(); // end the token s life
    window.location.href = Payline.Api.getCancelAndReturnUrls().cancelUrl; // redirect the user to cancelUrl
}
function togglePlaceOrderButton () {
    if ( paylineData.widget_integration === 'redirection' ) {
        return;
    }

    const currentPaymentMethod = jQuery('form.checkout').find( 'input[name="payment_method"]:checked' ).val();
    const placeOrderButton = jQuery('#place_order');
    if ( currentPaymentMethod === 'payline_cpt' ) {
        placeOrderButton.hide();
    } else {
        placeOrderButton.show();
    }
}

// Refresh the Payline widget when the cart is updated
jQuery(document).ready(function($) {
    jQuery( document.body ).on( 'updated_cart_totals updated_checkout', function() {
        if ( Payline?.Api ) {
            togglePlaceOrderButton();
            Payline.Api.reset();
        }
    });
});