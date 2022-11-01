import React, {Component} from 'react';
import { Row, Col, Typography } from 'antd';
import ShippingLabelForm from '../../Components/Forms/ShippingLabelDetailsForm';
import PackingSlipForm from '../../Components/Forms/PackingSlipDetailsForm';
import ShopifySettingsForm from '../../Components/Forms/ShopifySettings';

const { Title } = Typography;

export default class ShopifyIntegrationSettings extends Component {
  render() {
    return (
        <div>
          <Row gutter={ {xs: 8, md: 24} }>
            <Col xs={24} md={12}>
              <Title level={2}>Account Information</Title>
              <ShopifySettingsForm/>
            </Col>
            <Col xs={24} md={12}>
              <ShippingLabelForm />
              <PackingSlipForm/>
            </Col>
          </Row>
        </div>
    );
  }
}