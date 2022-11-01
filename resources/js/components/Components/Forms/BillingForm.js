import React, {Component} from 'react';
import {Form, Input, Button, Row, Col} from 'antd/lib/index';

class BillingForm extends Component {

  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {

    });
  };

  render() {
    const { getFieldDecorator } = this.props.form;
    return (
        <Form onSubmit={this.handleSubmit}  layout={'vertical'}>
          <Form.Item label="Name on Card">
            {getFieldDecorator('name', {
              rules: [{ required: false, message: 'Please input your name!' }],
            })(
                <Input
                    type="text"
                />,
            )}
          </Form.Item>
          <Form.Item label="Card Number">
            {getFieldDecorator('number', {
              rules: [{ required: true, message: 'Please input your Card NUmber!' }],
            })(
                <Input
                    type="text"
                />,
            )}
          </Form.Item>
          <Form.Item>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item label="Expiration">
                  {getFieldDecorator('exp', {
                    rules: [{ required: true, message: 'Please input your Expiration!' }],
                  })(
                      <Input
                          type="text"

                      />,
                  )}
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item label="CVV">
                  {getFieldDecorator('code', {
                    rules: [{ required: true, message: 'Please input your CCV Code!' }],
                  })(
                      <Input
                          type="text"

                      />,
                  )}
                </Form.Item>
              </Col>
            </Row>
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit">
              Save
            </Button>
          </Form.Item>
        </Form>
    );
  }
};
export default Form.create({ name: 'billing_form' })(BillingForm);
