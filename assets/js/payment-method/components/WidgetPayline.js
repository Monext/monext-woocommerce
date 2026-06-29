import { useEffect, useRef } from 'react';
import { useValidateCheckout } from '@woocommerce/blocks-checkout';


const addCustomCss = (cssContent, attributes, container) => {
    const style = document.createElement("style");
    style.textContent = cssContent;
    Object.keys(attributes).forEach((key) => {
        style.setAttribute(key, attributes[key]);
    });
    container.appendChild(style);
}

const WidgetPayline = ( {settings, checkoutContext} ) => {

    const previousToken = useRef(null);
    const validateCheckout = useValidateCheckout();


    //--> Chargement des CSS et JS nécessaires pour le widget Payline
    useEffect( () => {
        try {
            if ( checkoutContext.activePaymentMethod === "payline_cpt" && Payline?.Api ) {
                Payline.Api.reset();
            }
        } catch (error) {
            console.warn("Error loading Payline widget assets:", error);
        }

        const placeOrderButton = document.querySelector(".wc-block-components-checkout-place-order-button");
        const widgetPaylineContainerConnected = document.querySelector('#PaylineWidget[data-user-connected="true"]');
        if ( placeOrderButton ) {
            placeOrderButton.style.display = "none";
        }

        return () => {
            document.querySelectorAll("[data-added-by='payline']").forEach((element) => {
                element.remove();
            });

            if ( placeOrderButton ) {
                placeOrderButton.style.display = "";
            }
        };
    }, [] );

    useEffect(() => {
        window.paylineBlockValidateCheckout = validateCheckout;

        return () => {
            delete window.paylineBlockValidateCheckout;
        };
    }, [validateCheckout]);

    useEffect(() => {
        const checkoutToken = checkoutContext.cartData.extensions?.monext_payline?.widget_token;
        const paylineWidgetContainer = document.getElementById('PaylineWidget');
        if (checkoutToken && (previousToken.current !== undefined && previousToken.current !== checkoutToken)) {
            paylineWidgetContainer.setAttribute('data-token', checkoutToken);
            if (Payline?.Api) {
                Payline.Api.reset();
            }
        }
        if(checkoutToken !== undefined) {
            previousToken.current = checkoutToken;
        }
    }, [checkoutContext])

    return (
        <div dangerouslySetInnerHTML={{__html: settings.payline_widget_div}} ></div>
    );
}


export default WidgetPayline;