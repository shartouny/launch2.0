import React, { Component } from 'react';
import {
  Form,
  Input,
  Button,
  Typography,
  Row,
  Col,
  Select
} from 'antd/lib/index';

const { Option } = Select;
import axios from "axios";
import { displayErrors } from "../../../utils/errorHandler";

const { Title } = Typography;

class AccountInformationForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.props.handleAccountInfo(values);
      }
    });
  };


  render() {
    const {
      accountInfo,
      userInfo,
      countries,
      company,
      vat,
      firstName,
      lastName,
      phoneNumber
    } = this.props;
    const { getFieldDecorator } = this.props.form;

    return (
      <Form onSubmit={this.handleSubmit} layout={'vertical'}>

        <Row>
          <Col span={24}>
            <Form.Item label="Company Name">
              {getFieldDecorator('company', {
                rules: [{
                  required: true,
                  message: 'Please input your Company Name'
                }],
                initialValue: accountInfo.company || company
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
            <Form.Item label="VAT ID">
              {getFieldDecorator('vat', {
                rules: [{
                  required: false,
                  message: 'Please input your VAT ID'
                }],
                initialValue: accountInfo.vat || vat
              })(
                <Input
                  type="text"
                  placeholder="VAT ID"
                  name="vat"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="First Name">
              {getFieldDecorator('firstName', {
                rules: [{
                  required: true,
                  message: 'Please input your First Name'
                }],
                initialValue: userInfo.firstName || firstName
              })(
                <Input
                  type="text"
                  placeholder="First Name"
                  name="firsName"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="Last Name">
              {getFieldDecorator('lastName', {
                rules: [{
                  required: true,
                  message: 'Please input your Last Name'
                }],
                initialValue: userInfo.lastName || lastName
              })(
                <Input
                  type="text"
                  placeholder="Last Name"
                  name="lastName"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="Phone Number">
              {getFieldDecorator('phoneNumber', {
                rules: [{
                  required: false,
                  message: 'Please input your Phone Number'
                }],
                initialValue: userInfo.phoneNumber || phoneNumber
              })(
                <Input
                  type="text"
                  placeholder="Phone Number"
                  name="phoneNumber"
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
                initialValue: accountInfo.address1
              })(
                <Input
                  type="text"
                  placeholder="Address"
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
                initialValue: accountInfo.address2
              })(
                <Input
                  type="text"
                  placeholder="Apt/Ste#"
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
                initialValue: accountInfo.city
              })(
                <Input
                  type="text"
                  placeholder="City"
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
                initialValue: accountInfo.state
              })(
                <Input
                  type="text"
                  placeholder="State"
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
                initialValue: accountInfo.zip
              })(
                <Input
                  type="text"
                  placeholder="Zip Code"
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
                initialValue: accountInfo.country
              })(
                <Select name="country"
                        placeholder="Select a Country"
                        autoComplete="stopDamnAutocomplete"
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
            Save Account Info
          </Button>
        </Form.Item>

      </Form>
    );
  }
}

export default Form.create({ name: 'account_information' })(AccountInformationForm);
