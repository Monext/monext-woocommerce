import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getPaymentMethodData } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import WidgetPayline from '../components/WidgetPayline';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';

const settings = getPaymentMethodData( PAYMENT_METHOD_NAME, {} );
const defaultLabel = __(
    'Monext CPT',
    'payline'
);
const label = decodeEntities( settings?.title || '' ) || defaultLabel;
const icons = [settings.icon];

/**
 * Content component
 */
const Content = (props) => {

    const paymentDescription = decodeEntities(settings?.description || '');

    return (
        <>
            <RawHTML>{ paymentDescription }</RawHTML>
            {settings.widget_integration !== 'redirection' && (
                <WidgetPayline
                    settings={settings}
                    checkoutContext={props}
                />
            )}
        </>
    );
};

/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
    const { PaymentMethodLabel, PaymentMethodIcons } = props.components;
    return [
        <PaymentMethodLabel text={ label } />,
        <PaymentMethodIcons icons={ icons } />
    ];
};

/**
 * Payline CPT (payline-cpt) payment method config object.
 */
const paylineCptPaymentMethod = {
    name: PAYMENT_METHOD_NAME,
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => settings?.canMakePayment,
    ariaLabel: label,
    supports: {
        features: settings?.supports ?? [],
    },
};

registerPaymentMethod( paylineCptPaymentMethod );
