import React, {Component} from 'react';
import { Row, Col, Typography } from 'antd';
import AddressForm from "../../Components/Forms/AddressForm";
import AddProductModal from "../../Components/ProductModal/ProductSelecterModal"

const { Title } = Typography;

export default class SampleOrder extends Component {

  render() {
    return (
        <div>
          <Row gutter={ {xs: 8, md: 24, lg: 32} }>
            <Col xs={24} md={16} >
              <Title level={1}>Sample Order</Title>
              <Title level={2} style={{display: "inline"}}>Order Item</Title>
              <AddProductModal/>
            </Col>
            <Col xs={24} md={8}>
              <Title level={2}>Shipping</Title>
              <AddressForm />
            </Col>
          </Row>
        </div>
    );
  }
}