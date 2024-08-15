const settings = window.wc.wcSettings.getSetting("kpay_data", {});
const label = settings.title;
const htmlToElem = (html) => window.wp.element.RawHTML({ children: html });
const Block_Gateway = {
  name: "kpay",
  label: label,
  content: htmlToElem(settings.description),
  edit: htmlToElem(settings.description),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
