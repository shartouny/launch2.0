import { useRutterLink } from 'react-rutter-link';
import {Card, Col} from "antd";
import React from "react";
import axios from "axios";

import bigcommerceLogo from "../../Assets/rutter-bigcommerce.svg";
import prestashopLogo from "../../Assets/rutter-prestashop.svg";
import amazonLogo from "../../Assets/rutter-amazon.svg";
import magentoLogo from "../../Assets/rutter-magento.svg";
import shopifyLogo from "../../Assets/rutter-shopify.svg";
import squareSpaceLogo from "../../Assets/rutter-squarespace.svg";
import squareLogo from "../../Assets/rutter-square.svg";
import wixLogo from "../../Assets/rutter-wix.svg";
import ebayLogo from "../../Assets/rutter-ebay.svg";
import wooCommerceLogo from "../../Assets/rutter-woocommerce.svg";
import {displayErrors} from "../../../utils/errorHandler";

const RutterIntegration = () => {

  const rutterConfig = {
    publicKey: process.env.MIX_RUTTER_PROD_PUBLIC_KEY,
    onSuccess: onSuccess
  }

  const { open } = useRutterLink(rutterConfig);
  const showMagento = window.location.hash.toLowerCase().includes('magento') || window.location.hash.toLowerCase().includes('all');
  const showWix = window.location.hash.toLowerCase().includes('wix') || window.location.hash.toLowerCase().includes('all');
  const showSquarespace = window.location.hash.toLowerCase().includes('squarespace') || window.location.hash.toLowerCase().includes('all');
  const showAmazon = window.location.hash.toLowerCase().includes('amazon') || window.location.hash.toLowerCase().includes('all');
  const showEbay = window.location.hash.toLowerCase().includes('ebay') || window.location.hash.toLowerCase().includes('all');
  const showBigcommerce = window.location.hash.toLowerCase().includes('bigcommerce') || window.location.hash.toLowerCase().includes('all');
  const showSquare = (window.location.hash.toLowerCase().includes('square') && window.location.hash.toLowerCase().length === 7) || window.location.hash.toLowerCase().includes('all');
  const showPrestashop = window.location.hash.toLowerCase().includes('prestashop') || window.location.hash.toLowerCase().includes('all');

  function onSuccess(publicToken) {
    axios.defaults.baseURL = '';
    axios.get('/rutter/app/install', {
      params: {
        public_token: publicToken
      }
    })
      .then(response => {
        window.location.reload(false);
      })
      .catch(error => displayErrors(error));
  }

  return (
    <>
      {/*/!*Shopify*!/*/}
      {/*<Col sm={12} md={8} lg={6}>*/}
      {/*  <Card*/}
      {/*    hoverable={true}*/}
      {/*    style={{ display: "flex", height: "100%" }}*/}
      {/*    bodyStyle={{*/}
      {/*      display: "flex",*/}
      {/*      justifyContent: "center",*/}
      {/*      alignItems: "center"*/}
      {/*    }}*/}
      {/*    onClick={() => open({*/}
      {/*      platform: 'SHOPIFY'*/}
      {/*    })}*/}
      {/*  >*/}
      {/*    <img className="img-responsive" src={shopifyLogo} />*/}
      {/*  </Card>*/}
      {/*</Col>*/}

      {/*BigCommerce*/}
      {
        showBigcommerce &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{ display: "flex", height: "100%" }}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'BIGCOMMERCE'
            })}
          >
            <img className="img-responsive" src={bigcommerceLogo} />
          </Card>
        </Col>
      }


      {/*Prestashop*/}
      {
        showPrestashop &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'PRESTASHOP'
            })}
          >
            <img className="img-responsive" src={prestashopLogo}/>
          </Card>
        </Col>
      }

      {/*Amazon*/}
      {
        showAmazon &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'AMAZON'
            })}
          >
            <img className="img-responsive" src={amazonLogo}/>
          </Card>
        </Col>
      }

      {/*Magento*/}
      {
        showMagento &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'MAGENTO'
            })}
          >
            <img className="img-responsive" src={magentoLogo}/>
          </Card>
        </Col>
      }
      {/*Wix*/}
      {
        showWix &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'WIX'
            })}
          >
            <img className="img-responsive" src={wixLogo}/>
          </Card>
        </Col>
      }

      {/*Squarespace*/}
      {
        showSquarespace &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'SQUARESPACE'
            })}
          >
            <img className="img-responsive" src={squareSpaceLogo}/>
          </Card>
        </Col>
      }

      {/*Square*/}
      {
        showSquare &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'SQUARE'
            })}
          >
            <img className="img-responsive" src={squareLogo}/>
          </Card>
        </Col>
      }

      {/*Ebay*/}
      {
        showEbay &&
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'EBAY'
            })}
          >
            <img className="img-responsive" src={ebayLogo}/>
          </Card>
        </Col>
      }

      {/*WooCommerce*/}
        <Col sm={12} md={8} lg={6}>
          <Card
            hoverable={true}
            style={{display: "flex", height: "100%"}}
            bodyStyle={{
              display: "flex",
              justifyContent: "center",
              alignItems: "center"
            }}
            onClick={() => open({
              platform: 'WOOCOMMERCE'
            })}
          >
            <img className="img-responsive" src={wooCommerceLogo}/>
          </Card>
        </Col>
    </>
  )
}

export default RutterIntegration;
