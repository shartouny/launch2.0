import React, { Component } from 'react';
import shopifyLogo from "../../Assets/shopify_logo.png";
import amazonLogo from "../../Assets/rutter-favicon-amazon.svg";
import ebayLogo from "../../Assets/rutter-favicon-ebay.svg";
import bigcommerceLogo from "../../Assets/rutter-favicon-bigcommerce.svg";
import magentoLogo from "../../Assets/rutter-favicon-magento.svg";
import squareLogo from "../../Assets/rutter-favicon-square.svg";
import squareSpaceLogo from "../../Assets/rutter-favicon-squarespace.svg";
import wixLogo from "../../Assets/rutter-favicon-wix.svg";
import wooCommerceLogo from "../../Assets/rutter-favicon-woocommerce.svg";

/**
 *
 */
export default class PlatformLogo extends Component {
  constructor(props) {
    super(props);
    this.state = {
      platform: this.props.platform || {},
      platformType: this.props.platformType || null,
    };
  };

  render() {
    const { platform, platformType } = this.state;
    if(!platform.platformType){
      platform.platformType = platformType;
    }

    if(platform.name === 'Rutter'){
      switch (platform.platformType){
        case 'Shopify':
          platform.logo = shopifyLogo;
          break;
        case 'Amazon':
          platform.logo = amazonLogo;
          break;
        case 'Ebay':
          platform.logo = ebayLogo;
          break;
        case 'Bigcommerce':
          platform.logo = bigcommerceLogo;
          break;
        case 'Magento':
          platform.logo = magentoLogo;
          break;
        case 'Prestashop':
          platform.logo = magentoLogo;
          break;
        case 'Square':
          platform.logo = squareLogo;
          break;
        case 'Squarespace':
          platform.logo = squareSpaceLogo;
          break;
        case 'Wix':
          platform.logo = wixLogo;
          break;
        case 'Woocommerce':
          platform.logo = wooCommerceLogo;
          break;
      }
      platform.name = platform.platformType;
    }

    return platform && platform.logo ?
      <img src={platform.logo} alt={platform.name} className="platform-logo"/> : platform.name + ': '
  }
}
