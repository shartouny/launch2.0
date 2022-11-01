import React, { Component } from 'react';
import { Form, Input, Button, Row, Col, Typography } from 'antd/lib/index';
import { Select } from "antd";

const { Option } = Select;

const { Title } = Typography;

import axios from 'axios';
import { displayErrors } from "../../../utils/errorHandler";

class AddressForm extends Component {

  constructor(props) {
    super(props);
    this.state = {
      countries: [],
      address: props.address,
      onSubmit: () => {
      }
    }
  }

  componentDidMount() {
    this.getCountries();
  };

  getCountries = () => {
    axios.get(`countries`).then(res => {
      const { data } = res.data;
      this.setState({ countries: data });
    }).catch(error => displayErrors(error))
      .finally();
  };

  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.state.onSubmit(values);
      }
    });
  };

  render() {
    const { countries, address } = this.state;
    const { getFieldDecorator } = this.props.form;
    return (
      <Form onSubmit={this.handleSubmit} layout={'vertical'}>
        <Form.Item label="Street 1">
          {getFieldDecorator('street1', {
            rules: [{ required: true, message: 'Please input your Street Address' }],
            initialValue: address?.address1 || null
          })(
            <Input
              type="text"
              placeholder="Street"
            />,
          )}
        </Form.Item>
        <Form.Item label="Street 2">
          {getFieldDecorator('street2', {
            rules: [{ required: false, message: 'Please input your Street Address' }],
            initialValue: address?.address2 || null
          })(
            <Input
              type="text"
              placeholder="Apt/Ste#"
            />,
          )}
        </Form.Item>
        <Form.Item label="City">
          {getFieldDecorator('city', {
            rules: [{ required: true, message: 'Please input your City' }],
            initialValue: address?.city || null
          })(
            <Input
              type="text"
              placeholder="City"
            />,
          )}
        </Form.Item>

          <Row gutter={16}>
            <Col span={12}>
              <Form.Item label="State">
                {getFieldDecorator('state', {
                  rules: [{ required: false, message: 'Please input your State' }],
                  initialValue: address?.state || null
                })(
                  <Input
                    type="text"
                    placeholder="State"
                  />,
                )}
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item label="Postal Code">
                {getFieldDecorator('postal-code', {
                  rules: [{ required: false, message: 'Please input your Postal Code' }],
                  initialValue: address?.zip || null
                })(
                  <Input
                    type="text"
                    placeholder="Postal Code"
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
                  initialValue: address?.country || null
                })(
                  <Select name="country"
                          placeholder="Select a Country"
                          showSearch
                          onSearch={this.onChange}
                          optionFilterProp="children"
                          filterOption={(input, option) =>
                            option.props.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                          }>
                    {countries.map(country => <Option value={country.code}>{country.name}</Option>)}
                  </Select>,
                )}
              </Form.Item>
            </Col>
          </Row>

        <Form.Item>
          <Button type="primary" htmlType="submit">
            Save
          </Button>
        </Form.Item>
      </Form>
    );
  }
}

export default Form.create({ name: 'address_label' })(AddressForm);
