import { Form, Row, Col, Input, Select, Button } from 'antd';
import React, {Component} from 'react';
import axios from 'axios';

class EditAddressForm extends Component {
  constructor(props) {
    super(props)
    this.state = {
      countries: []
    }
  }

  componentDidMount() {
    this.getCountries();
    console.log(this.props.address);
  }

  getCountries = () => {
    axios.get(`countries`).then(res => {
      const { data } = res.data;
      this.setState({ countries: data });
    }).catch(error => displayErrors(error))
  }

  onSubmitForm = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.props.editAddress(values);
        this.props.onEdit(this.props.address.id, values);
      }
    })
  }

  render() {
    const { getFieldDecorator } = this.props.form;
    const { countries } = this.state;
    const { address } = this.props;

    return (
      <Form onSubmit={e => this.onSubmitForm(e)}
        layout="vertical"
        style={{marginTop: '15px'}}>
        <Form.Item label="Full Name" key="1">
          {getFieldDecorator('fullName', {
            rules: [{ required: true, message: 'Please Input your full name' }],
            initialValue: `${address.firstName} ${address.lastName}`
          })(
            <Input
              type="text"
              placeholder="Full Name"
            />,
          )}
        </Form.Item>
        <Form.Item label="Address 1" key="2">
          {getFieldDecorator('address1', {
            rules: [{ required: true, message: 'Please input your Street Address' }],
            initialValue: address.address1
          })(
            <Input
              type="text"
              placeholder="Address 1"
            />,
          )}
        </Form.Item>
        <Form.Item label="Address 2" key="3">
          {getFieldDecorator('address2', {
            rules: [{ required: false, message: 'Please input your Street Address' }],
            initialValue: address.address2
          })(
            <Input
              type="text"
              placeholder="Address 2"
            />,
          )}
        </Form.Item>
        <Form.Item label="City" key="4">
          {getFieldDecorator('city', {
            rules: [{ required: true, message: 'Please input your City' }],
            initialValue: address.city
          })(
            <Input
              type="text"
              placeholder="City"
            />,
          )}
        </Form.Item>
        <Row gutter={16}>
          <Col span={12}>
            <Form.Item label="State" key="5">
              {getFieldDecorator('state', {
                rules: [{ required: false, message: 'Please input your State' }],
                initialValue: address.state
              })(
                <Input
                  type="text"
                  placeholder="State"
                />,
              )}
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item label="Zip" key="6">
              {getFieldDecorator('zip', {
                rules: [{ required: false, message: 'Please input your Postal Code' }],
                initialValue: address.zip
              })(
                <Input
                  type="text"
                  placeholder="Zip"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>
        <Form.Item label="Phone" key="7">
          {getFieldDecorator('phone', {
            initialValue: address.phone ?? ''
          })(
            <Input
              type="text"
              placeholder="Phone"
            />,
          )}
        </Form.Item>
        <Row gutter={16}>
          <Col span={24}>
            <Form.Item label="Country" key="8">
              {getFieldDecorator('country', {
                rules: [{ required: true, message: 'Please input your Country' }],
                initialValue: address.country
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
        <Form.Item key="9">
          <Button type="primary" htmlType="submit">
            Save
          </Button>
        </Form.Item>
      </Form>
    )
  }
}

export default Form.create('edit_address_form')(EditAddressForm);
