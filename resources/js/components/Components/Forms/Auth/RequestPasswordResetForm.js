import React, { Component, Fragment } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import { Form, Icon, Input, Button, Alert } from 'antd';
import { connect } from 'react-redux';
import { displayErrors } from '../../../../utils/errorHandler';
import Spin from "antd/es/spin";

/**
 *
 */
class RequestPasswordResetForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      isUpdatingPassword: false
    }
  };

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    const { isUpdatingPassword } = this.state;
    if (isUpdatingPassword) {
      return;
    }
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.setState({ isUpdatingPassword: true }, () => {
          if (typeof this.props.getRecaptchaToken !== 'undefined') {
            this.props.getRecaptchaToken().then(recaptchaToken => {
              axios.defaults.baseURL = '';
              axios
                .post('/api/password/forgot', { ...values, recaptchaToken })
                .then(response => {
                  this.setState({ resetRequested: true }, this.props.history.push('/login'));
                })
                .catch(error => {
                  displayErrors(error);
                }).finally(() => this.setState({ isUpdatingPassword: false }))
            });
          } else {
            axios.defaults.baseURL = '';
            axios
              .post('/api/password/forgot', { ...values })
              .then(response => {
                this.setState({ resetRequested: true }, this.props.history.push('/login'));
              })
              .catch(error => {
                displayErrors(error);
              }).finally(() => this.setState({ isUpdatingPassword: false }))
          }
        });
      }
    });
  };

  render() {
    const {
      resetRequested,
      isUpdatingPassword
    } = this.state;

    const { getFieldDecorator } = this.props.form;

    return (
      <Fragment>
        <Form onSubmit={this.handleSubmit} className="login-form" id={'components-form-normal-login'}>
          <Form.Item>
            {getFieldDecorator('email', {
              rules: [{ required: true, message: 'Please input your email' }],
            })(
              <Input
                prefix={<Icon type="user" style={{ color: 'rgba(0,0,0,.25)' }}/>}
                placeholder="Email"
                name="email"
              />,
            )}
          </Form.Item>
          <Form.Item>
            <Button type="primary" htmlType="submit" className="login-form-button">
              <Spin spinning={isUpdatingPassword}>Request Password Reset</Spin>
            </Button>
            or <Link to="/login"><span>Back to Login</span></Link>
          </Form.Item>
        </Form>
        {resetRequested && <Alert message="Password reset link has been sent to your email" type="success"/>}
      </Fragment>
    );
  }
}

const RequestPasswordReset = Form.create({ name: 'request_password_reset' })(RequestPasswordResetForm);

export default connect()(RequestPasswordReset);
