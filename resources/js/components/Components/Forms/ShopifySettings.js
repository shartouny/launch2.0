import React, {Component} from 'react';
import {Form, Input, Button, Typography, Switch, Icon} from 'antd/lib/index';

const { Paragraph } = Typography;

class ShopifySettingsForm extends Component {

  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {

    });
  };

  render() {
    const { getFieldDecorator } = this.props.form;
    return (
        <Form onSubmit={this.handleSubmit}  layout={'vertical'}>
          <Form.Item label="Shopify URL">
            {getFieldDecorator('Email', {
              rules: [{ required: true, message: 'Please input your Shopify Store URL!' }],
            })(
                <Input
                    placeholder="Shopify URL"
                />,
            )}
          </Form.Item>
          <Form.Item label="Website URL">
            {getFieldDecorator('Email', {
              rules: [{ required: true, message: 'Please input your Website URL!' }],
            })(
                <Input
                    placeholder="Website URL"
                />,
            )}
          </Form.Item>
          <Form.Item label="Send Notification">
            <Switch
                checkedChildren={<Icon type="check" />}
                unCheckedChildren={<Icon type="close" />}
                defaultChecked
            />
            <Paragraph>When an order error is detected, a message will be sent to your email address.</Paragraph>
          </Form.Item>
          <Form.Item label="Send Receipts">
            <Switch
                checkedChildren={<Icon type="check" />}
                unCheckedChildren={<Icon type="close" />}
                defaultChecked
            />
            <Paragraph>A detailed receipt will be sent to your email when a charge is processed.</Paragraph>
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit">
              Update Shopify
            </Button>
            <Button type="danger" htmlType="submit" className="ml-4">
              Delete
            </Button>
          </Form.Item>
        </Form>
    );
  }
};
export default Form.create({ name: 'shopify_settings' })(ShopifySettingsForm)
