import React, { Component } from 'react';
import { Link } from 'react-router-dom';
import {
  Col,
  Row,
  Typography,
  Button,
  Modal,
  Card,
  Avatar,
  Dropdown,
  Menu,
  Icon,
  Input,
} from 'antd';
import { message } from 'antd';

import shopifyLogo from '../../Assets/shopify_logo.png';
import shopifyIcon from '../../Assets/shopify_icon.png';
import etsyLogo from '../../Assets/etsy_logo.svg';
import PlatformStore from './PlatformStore';
import qs from 'qs';

const { Title } = Typography;

export default class InstallShopifyPage extends Component {
  constructor(props) {
    super(props);

    this.state = {
      shopifyInstallStoreUrl: '',
    };

    this.shopifyShopInputRef = React.createRef();
  }

  componentDidMount() {
    setTimeout(() => {
      if (this.shopifyShopInputRef.current) {
        this.shopifyShopInputRef.current.focus({
          cursor: 'all',
        });
      }
    }, 100);
  }

  handleShopifyInstall = () => {
    let { shopifyInstallStoreUrl } = this.state;
    // clean the url for shopify
    shopifyInstallStoreUrl = shopifyInstallStoreUrl.trim();
    // remove http or https
    shopifyInstallStoreUrl = shopifyInstallStoreUrl
      .toLowerCase()
      .replace(/^https?:\/\//, '')
      .replace(/\//, '');
    if (shopifyInstallStoreUrl.includes('myshopify.com')) {
      window.location.href = `/shopify/api/request-install?shop=${shopifyInstallStoreUrl}`;
    } else {
      // not valid
      message.error(`Invalid Shopify store URL`);
    }
  };

  render() {
    const { shopifyInstallStoreUrl } = this.state;
    return (
      <div>
        <Col md={{ span: 12, offset: 6 }}>
          <Card>
            <Title level={2}>Install Shopify</Title>
            Store URL:
            <Input
              size="large"
              placeholder="my-store.myshopify.com"
              value={shopifyInstallStoreUrl}
              ref={this.shopifyShopInputRef}
              onChange={event => {
                this.setState({
                  shopifyInstallStoreUrl: event.target.value,
                });
              }}
            />
            <div style={{ height: '25px' }}></div>
            <Button
              key={2}
              type="primary"
              onClick={() => {
                this.handleShopifyInstall();
              }}>
              Confirm
            </Button>
          </Card>
        </Col>
      </div>
    );
  }
}
