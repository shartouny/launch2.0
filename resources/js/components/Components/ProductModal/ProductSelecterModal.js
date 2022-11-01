import React, {Component} from 'react';
import { Modal, Button, Row, Col, Typography, Select, InputNumber } from 'antd';

const { Title, Text } = Typography;
const { Option } = Select;


export default class AddProductModal extends Component {
  state = { visible: false };

  showModal = () => {
    this.setState({
      visible: true,
    });
  };

  handleOk = e => {
    this.setState({
      visible: false,
    });
  };

  handleCancel = e => {
    this.setState({
      visible: false,
    });
  };

  render() {
    function handleChange(value) {

    }
    return (
        <div style={{display: "inline"}}>
          <Button type="primary" onClick={this.showModal} style={{float: "right"}}>
            Add Product
          </Button>
          <Modal
              title="Choose Product"
              visible={this.state.visible}
              onOk={this.handleOk}
              onCancel={this.handleCancel}
              width="80%"
          >
            <Row>
              <Col span={6}>
                <Row>
                  <Col span={8}>
                    <img className="img-responsive" src="//cdn.shopify.com/s/files/1/1151/0394/products/fEbDrApbYeh5zJ3ThrShcq7Hbgh5W7HdcY8tB3JFebayDGwgck_k19gpohczzf8_2048x2048.png?v=1523547003" />
                  </Col>
                  <Col span={16}>
                    <Title level={3}>Product Title</Title>
                    <Title level={4}>$00.00</Title>
                  </Col>
                </Row>
              </Col>
              <Col span={6}>
                <img className="img-responsive" src="//cdn.shopify.com/s/files/1/1151/0394/products/fEbDrApbYeh5zJ3ThrShcq7Hbgh5W7HdcY8tB3JFebayDGwgck_k19gpohczzf8_2048x2048.png?v=1523547003" />
              </Col>
              <Col span={11} offset={1}>
                <Title>Product Title</Title>
                <Title level={3}>$00.00</Title>
                <div  className="my-2">
                <Text strong={true}>Size:</Text>
                  <Select defaultValue="small" onChange={handleChange} style={{ width: '100%'}}>
                    <Option value="small">Small</Option>
                    <Option value="medium">Medium</Option>
                    <Option value="large">Large</Option>
                  </Select>
                </div>
                <div  className="my-2">
                <Text strong={true}>Color:</Text>
                  <Select defaultValue="red" onChange={handleChange} style={{ width: '100%'}}>
                    <Option value="red">Red</Option>
                    <Option value="blue">Blue</Option>
                    <Option value="black">Black</Option>
                  </Select>
                </div>
                <div  className="my-2">
                  <Text strong={true}>Quantity:</Text>
                  <InputNumber min={1} max={10} defaultValue={1} onChange={handleChange} style={{ width: '100%'}} />
                </div>
              </Col>
            </Row>
          </Modal>
        </div>
    );
  }
}
