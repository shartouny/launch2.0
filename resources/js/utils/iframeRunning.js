export function isShopifyApp() {
  const isShopifyApp = sessionStorage.getItem('shopify_app');

  if(!isShopifyApp) {
    return false;
  }

  return true;
}



