(()=>{"use strict";const e=window.React,t=window.wc.wcBlocksRegistry,n=window.wp.i18n,i=window.wc.wcSettings,a=window.wp.htmlEntities;var l;const o=(0,i.getPaymentMethodData)("payline",{}),c=(0,n.__)("Payline CPT","payline"),s=(0,a.decodeEntities)(o?.title||"")||c,r=()=>(0,a.decodeEntities)(o.description||""),w={name:"payline",label:(0,e.createElement)((t=>{const{PaymentMethodLabel:n}=t.components;return(0,e.createElement)(n,{text:s})}),null),content:(0,e.createElement)(r,null),edit:(0,e.createElement)(r,null),canMakePayment:()=>!0,ariaLabel:s,supports:{features:null!==(l=o?.supports)&&void 0!==l?l:[]}};(0,t.registerPaymentMethod)(w)})();