import React, { Component } from 'react';
import {
  Button,
  Form,
  Input,
} from 'antd/lib/index';

/**
 *
 */
class StripeRegisterForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.styles = {
      registerForm: {
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'flex-end',
      },
      emailInput: {
        flexGrow: 1,
        paddingRight: '10px',
      },
    };
  };
  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      onStripeRegister,
      form,
      email,
    } = this.props;
    const { getFieldDecorator } = form;

    return (
      <Form
        onSubmit={event => onStripeRegister(event, form)}
        layout={'vertical'}
        style={this.styles.registerForm}
      >
        <Form.Item
          label="Email:"
          style={this.styles.emailInput}
        >
          {getFieldDecorator('email', {
            initialValue: email,
            rules: [
              {
                type: 'email',
                message: 'The input is not valid E-mail!',
              },
              {
                required: false,
                message: 'Please input your E-mail!',
              },
            ],
          })(
            <Input
              type="text"
              placeholder="Email"
            />,
          )}
        </Form.Item>
        <Form.Item>
          <Button
            type="primary"
            htmlType="submit"
          >
            Register
          </Button>
        </Form.Item>
      </Form>
    )
  }
}
export default Form.create({ name: 'stripe' })(StripeRegisterForm);
