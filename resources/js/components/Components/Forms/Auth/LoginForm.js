import React, { Component, Fragment } from 'react';
import axios from 'axios';
import { Redirect } from 'react-router';
import { Link } from 'react-router-dom';
import { Form, Icon, Input, Button, Alert, Spin } from 'antd';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { login } from '../../../Actions';

import tokenService from '../../../../utils/tokenService';
import { displayErrors } from '../../../../utils/errorHandler';
import { getQueryParameters } from "../../../../utils/parameters";

/**
 *
 */
class NormalLoginForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    const { dispatch } = props;
    //this.boundActions = bindActionCreators(login, dispatch);
    this.state = {
      incorrectLogin: false,
      authenticated: false,
      authorized: true,
      isLoggingIn: false
    };
  };

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();

    const { isLoggingIn } = this.state;

    if (isLoggingIn) {
      return;
    }

    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.setState({ isLoggingIn: true }, () => {
          if (typeof this.props.getRecaptchaToken !== 'undefined') {
            this.props.getRecaptchaToken().then(recaptchaToken => {
              axios.defaults.baseURL = '';
              axios
                .post('/api/login', { ...values, shop: this.getCookie('shop'), recaptchaToken })
                .then(response => {
                  if (response.status === 200) {
                    tokenService.saveToken(response.data.api_token);
                    tokenService.saveIsVerified(response.data.email_verified_at !== null);
                    this.setState({ authenticated: true });
                  }
                })
                .catch(error => {
                  this.setState({ isLoggingIn: false });

                  const { response } = error;

                  if (response.status === 422) {
                    this.setState({ incorrectLogin: true });
                  }

                  if (response.status === 401) {
                    this.setState({ authorized: false });
                  }

                  displayErrors(error);
                });
            }).finally();
          } else {
            axios.defaults.baseURL = '';
            axios
              .post('/api/login', { ...values, shop: this.getCookie('shop') })
              .then(response => {
                if (response.status === 200) {
                  tokenService.saveToken(response.data.api_token);
                  tokenService.saveIsVerified(response.data.email_verified_at !== null);
                  this.setState({ authenticated: true });
                }
              })
              .catch(error => {
                this.setState({ isLoggingIn: false });

                const { response } = error;

                if (response.status === 422) {
                  this.setState({ incorrectLogin: true });
                }

                if (response.status === 401) {
                  this.setState({ authorized: false });
                }

                displayErrors(error);
              });
          }

        });
      }
    });
  };

  getCookie = (cname) => {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) === 0) {
        return c.substring(name.length, c.length);
      }
    }
    return "";
  };

  render() {
    const {
      authorized,
      authenticated,
      incorrectLogin,
      isLoggingIn
    } = this.state;

    const { getFieldDecorator } = this.props.form;

    if (authenticated) {
      // see if we have a redirect in the query string
      const query = getQueryParameters();
      if(query && query.redirect){
        return <Redirect to={decodeURIComponent(query.redirect)}/>;
      } else {
        return <Redirect to={'/catalog'}/>;
      }
    }

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
                autoComplete="username"
              />,
            )}
          </Form.Item>
          <Form.Item>
            {getFieldDecorator('password', {
              rules: [{ required: true, message: 'Please input your password' }],
            })(
              <Input
                prefix={<Icon type="lock" style={{ color: 'rgba(0,0,0,.25)' }}/>}
                type="password"
                placeholder="Password"
                name="password"
                autoComplete="password"
              />,
            )}
          </Form.Item>
          <Form.Item>
            <Link to="/password/forgot"><span className="login-form-forgot">Forgot password</span></Link>
            <Button type="primary" htmlType="submit" className="login-form-button">
              <Spin spinning={isLoggingIn}>Log in</Spin>
            </Button>
            <div style={{ marginTop: 8, textAlign: 'center' }}>
              <Link to={"/register" + window.location.search}>Create Account</Link>
            </div>
          </Form.Item>
        </Form>
        {incorrectLogin &&
        <Alert message="Verify that your Email and Password are correct and try again" type="error"/>}
        {!authorized && <Alert message="Please logout and try again." type="error"/>}
      </Fragment>
    );
  }
}

const Login = Form.create({ name: 'normal_login' })(NormalLoginForm);

export default connect()(Login);
