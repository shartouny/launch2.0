import React, { Component, Fragment } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import { Form, Icon, Input, Button, Alert } from 'antd';
import { connect } from 'react-redux';
import { displayErrors } from '../../../../utils/errorHandler';
import Spin from "antd/es/spin";
import qs from 'qs';

/**
 *
 */
class PasswordResetForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      isRequestingReset: false,
      token: props.match.params.token,
      email: qs.parse(props.location.search, { ignoreQueryPrefix: true }).email,
      passwordReset: false
    };
  };

  handleSubmit = e => {
    e.preventDefault();
    const { isRequestingReset } = this.state;
    if (isRequestingReset) {
      return;
    }
    this.props.form.validateFieldsAndScroll((err, values) => {
      if (!err) {
        this.setState({ isRequestingReset: true }, () => {
          if (typeof this.props.getRecaptchaToken !== 'undefined') {
            this.props.getRecaptchaToken().then(recaptchaToken => {
              const { token, email } = this.state;
              axios.post('/api/password/reset', { ...values, token, email, recaptchaToken })
                .then(res => {
                  this.setState({ passwordReset: true });
                })
                .catch(error => {
                  displayErrors(error);
                }).finally(() => this.setState({ isRequestingReset: false }))
            })
          }else {
            const { token, email } = this.state;
            axios.post('/api/password/reset', { ...values, token, email })
              .then(res => {
                this.setState({ passwordReset: true });
              })
              .catch(error => {
                displayErrors(error);
              }).finally(() => this.setState({ isRequestingReset: false }))
          }
        });
      }
    });
  };
  /**
   *
   */
  onChange = () => {
    if (!this.state.extends) {
      return;
    }

    this.setState({ exists: false });
  };
  /**
   *
   * @param {{}} e
   */
  handleConfirmBlur = e => {
    const { value } = e.target;
    this.setState({ confirmDirty: this.state.confirmDirty || !!value });
  };

  compareToFirstPassword = (rule, value, callback) => {
    const { form } = this.props;
    if (value && value !== form.getFieldValue('password')) {
      callback("Confirmation password must match the password field");
    } else {
      callback();
    }
  };

  validateToNextPassword = (rule, value, callback) => {
    const { form } = this.props;
    if (value && this.state.confirmDirty) {
      form.validateFields(['confirm'], { force: true });
    }
    callback();
  };

  render() {
    const {
      passwordReset,
      isRequestingReset
    } = this.state;

    const { getFieldDecorator } = this.props.form;

    return (
      <Fragment>
        <Form onSubmit={this.handleSubmit} className="login-form" id={'components-form-normal-login'}>
          <Form.Item label="New Password" hasFeedback>
            {getFieldDecorator('password', {
              rules: [
                {
                  required: true,
                  message: 'Please input your password',
                },
                {
                  pattern: '^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,})',
                  message: "Password must be 8 characters or longer and contain at least one lowercase letter, one uppercase letter and a number",
                },
                {
                  validator: this.validateToNextPassword,
                },
              ],
            })(<Input.Password name="password"/>)}
          </Form.Item>
          <Form.Item label="Confirm New Password" hasFeedback>
            {getFieldDecorator('password_confirmation', {
              rules: [
                {
                  required: true,
                  message: 'Please confirm your password',
                },
                {
                  validator: this.compareToFirstPassword,
                },
              ],
            })(<Input.Password onBlur={this.handleConfirmBlur}/>)}
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" className="login-form-button">
              <Spin spinning={isRequestingReset}>Reset Password</Spin>
            </Button>
            or <Link to="/login"><span>Back to Login</span></Link>
          </Form.Item>
        </Form>
        {passwordReset && <Alert message="Password reset, you may now login using the new password" type="success"/>}
      </Fragment>
    );
  }
}

const PasswordReset = Form.create({ name: 'password_reset' })(PasswordResetForm);

export default connect()(PasswordReset);
