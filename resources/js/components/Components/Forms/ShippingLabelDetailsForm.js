import React, {Component} from 'react';
import { Form, Input, Button, Row, Col, Typography, Select } from 'antd/lib/index';
import axios from "axios";
import { displayErrors } from "../../../utils/errorHandler";

const { Title } = Typography;

class ShippingLabelForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  /**
   *
   * @param e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.props.handleShippingLabel(values);
      }
    });
  };

  render() {
    const {
      shippingLabel,
      countries,
      company
    } = this.props;
    const { getFieldDecorator } = this.props.form;

    return (
      <Form onSubmit={this.handleSubmit}  layout={'vertical'}>

        <Row>
          <Col span={24}>
            <Form.Item label="Company Name">
              {getFieldDecorator('company', {
                rules: [{
                  required: true,
                  message: 'Please input your Company Name'
                }],
                initialValue: shippingLabel.company || company
              })(
                <Input
                  type="text"
                  placeholder="Company Name"
                  name="company"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="Address 1">
              {getFieldDecorator('address1', {
                rules: [{
                  required: true,
                  message: 'Please input your Street Address'
                }],
                initialValue: shippingLabel.address1
              })(
                <Input
                  type="text"
                  placeholder="Address"
                  name="address1"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="Address 2">
              {getFieldDecorator('address2', {
                rules: [{
                  required: false,
                  message: 'Please input your Street Address'
                }],
                initialValue: shippingLabel.address2
              })(
                <Input
                  type="text"
                  placeholder="Apt/Ste#"
                  name="address2"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="City">
              {getFieldDecorator('city', {
                rules: [{
                  required: true,
                  message: 'Please input your City'
                }],
                initialValue: shippingLabel.city
              })(
                <Input
                  type="text"
                  placeholder="City"
                  name="city"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item label="State">
                {getFieldDecorator('state', {
                  rules: [{
                    required: false,
                    message: 'Please input your State'
                  }],
                  initialValue: shippingLabel.state
                })(
                  <Input
                    type="text"
                    placeholder="State"
                    name="state"
                  />,
                )}
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item label="Zip Code">
                {getFieldDecorator('zip', {
                  rules: [{
                    required: true,
                    message: 'Please input your Postal Code'
                  }],
                  initialValue: shippingLabel.zip
                })(
                  <Input
                    type="text"
                    placeholder="Zip Code"
                    name="zip"
                  />,
                )}
              </Form.Item>
            </Col>
          </Row>


        <Row gutter={16}>
          <Col span={24}>
            <Form.Item label="Country">
              {getFieldDecorator('country', {
                rules: [{ required: true, message: 'Please input your Country' }],
                initialValue: shippingLabel.country
              })(
                <Select name="country"
                        placeholder="Select a Country"
                        showSearch
                        optionFilterProp="children"
                        filterOption={(input, option) =>
                          option.props.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                        }>
                  {countries.map(country => <Option key={country.code} value={country.code}>{country.name}</Option>)}
                </Select>,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Form.Item>
          <Button type="primary" htmlType="submit">
            Save Shipping Label
          </Button>
        </Form.Item>
      </Form>
    );
  }
}
export default Form.create({ name: 'shipping_label' })(ShippingLabelForm);
