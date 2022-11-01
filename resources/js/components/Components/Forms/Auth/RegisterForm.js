import React, { Component } from "react";
import axios from "axios";
import { Redirect } from "react-router";
import { Form, Input, Button, Icon, Alert, Spin, Col, Row, Popover, message } from "antd";

import tokenService from "../../../../utils/tokenService";
import { displayErrors } from "../../../../utils/errorHandler";
import { Link } from "react-router-dom";
import { getQueryParameters } from "../../../../utils/parameters";
import {
  hasLowercaseCharacter,
  hasUpperCaseAlphabetical,
  hasNumericCharacter,
  hasEightCharacter
} from '../../../../utils/passwordValidation'
import '../../../../../css/InputStyle.css'

class RegistrationForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      confirmDirty: false,
      autoCompleteResult: [],
      authenticated: false,
      exists: false,
      isRegistering: false,
      isValidCharacter: false,
      isUpperCase: false,
      isLowerCase: false,
      isNumber: false,
      isPopoverVisible: false

    };
  }

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    const { isRegistering } = this.state;
    if (isRegistering) {
      return;
    }

    this.props.form.validateFieldsAndScroll((err, values) => {
      if (!err) {
        const query = getQueryParameters();
        let redirectTo = null;
        let isShopify = false;
        if(query && query.redirect){
          redirectTo = decodeURIComponent(query.redirect);
          isShopify = query.redirect.toLowerCase().includes('myshopify.com');
        }
        if (typeof this.props.getRecaptchaToken !== "undefined") {
          this.setState({ isRegistering: true }, () => {
            this.props.getRecaptchaToken().then(recaptchaToken => {
              axios.defaults.baseURL = "";
              axios
                .post("/api/register", { ...values, recaptchaToken, isShopify: isShopify })
                .then(res => {
                  if (res) {
                    tokenService.saveToken(res.data.api_token);
                    tokenService.saveIsVerified(
                      res.data.email_verified_at !== null
                    );
                    this.setState({ authenticated: true });
                  }
                })
                .catch(error => {
                  this.setState({ isRegistering: false });
                  const { response } = error;
                  if (response.status === 422) {
                    displayErrors(error);
                  }
                });
            });
          });
        } else {
          const {
            isUpperCase,
            isLowerCase,
            isNumber,
            isValidCharacter,
          } = this.state
          if (isUpperCase && isLowerCase && isNumber && isValidCharacter) {
            this.setState({ isRegistering: true }, () => {
              axios.defaults.baseURL = "";
              axios
                .post("/api/register", { ...values, isShopify: isShopify })
                .then(res => {
                  if (res) {
                    tokenService.saveToken(res.data.api_token);
                    tokenService.saveIsVerified(
                      res.data.email_verified_at !== null
                    );
                    this.setState({ authenticated: true });
                  }
                })
                .catch(error => {
                  this.setState({ isRegistering: false });
                  const { response } = error;
                  if (response.status === 422) {
                    displayErrors(error);
                  }
                });
            });
          } else {
            message.error('Invalid Password')
          }
        }
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
    if (value && value !== form.getFieldValue("password")) {
      callback("Confirmation password must match the password field");
    } else {
      callback();
    }
  };

  validateToNextPassword = (rule, value, callback) => {
    const { form } = this.props;
    if (value && this.state.confirmDirty) {
      form.validateFields(["confirm"], { force: true });
    }
    callback();
  };

  handlePasswordValidation =({target:{value}}) => {

    const validatity = {
      isUpperCase: hasUpperCaseAlphabetical(value),
      isLowerCase: hasLowercaseCharacter(value),
      isNumber: hasNumericCharacter(value),
      isValidCharacter: hasEightCharacter(value)
    }
    this.setState({...this.state,...validatity})
  }

  onChangeStoreName = ({target:{value}}) => {

  }

  render() {
    const { getFieldDecorator } = this.props.form;
    const { authenticated, exists, isRegistering } = this.state;

    if (authenticated) {
      // see if we have a redirect in the query string
      const query = getQueryParameters();
      if(query && query.redirect){
        return <Redirect to={decodeURIComponent(query.redirect)}/>;
      } else {
        return <Redirect to={'/catalog'}/>;
      }
    }

    const Test = ({isValidCharacter, isUpperCase, isLowerCase, isNumber})=>{
      return   <div style={{width: 300}}>
        <Row>
          <Col >
            <p style={{color: isValidCharacter ? '#4454DF' : '#c4c4c4'}} >Must be 8 character min</p>
          </Col>
        </Row>
        <Row>
          <Col>
            <p style={{color: isLowerCase ? '#4454DF' : '#c4c4c4'}}>At least one lowercase letter</p>
          </Col>
        </Row>
        <Row>
          <Col>
            <p style={{color: isUpperCase ? '#4454DF' : '#c4c4c4'}}>At least one uppercase letter</p>
          </Col>
        </Row>
        <Row>
          <Col>
            <p style={{color: isNumber ? '#4454DF' : '#c4c4c4'}}>At least one number</p>
          </Col>
        </Row>
      </div>
    };

    return (
      <div style={{padding: '0 18%'}}>
        <h1 style={{marginBottom: '10px', textAlign: 'center'}}>SIGN UP FOR A TEELAUNCH ACCOUNT TODAY!</h1>
        <Form
          onSubmit={this.handleSubmit}
          layout={"vertical"}
          className="login-form"
        >
          <Row>
            <Col sm={24} md={24} lg={12} style={{paddingRight: 10}}>
              <Form.Item label="First Name" hasFeedback>
                {getFieldDecorator("firstName", {
                  rules: [
                    {
                      required: true,
                      message: "Please input your First Name"
                    },
                    {
                      pattern: "([^-\\s])",
                      message:
                        "Please enter letters only"
                    }
                  ]
                })(<Input type="text" placeholder="First Name" />)}
              </Form.Item>
            </Col>
            <Col sm={24} md={24} lg={12}>
              <Form.Item label="Last Name" hasFeedback>
                {getFieldDecorator("lastName", {
                  rules: [
                    {
                      required: true,
                      message: "Please input your Last Name"
                    },
                    {
                      pattern: "([^-\\s])",
                      message:
                        "Please enter letters only"
                    }
                  ]
                })(<Input type="text" placeholder="Last Name" />)}
              </Form.Item>
            </Col>
          </Row>
        <Form.Item
          label="E-mail"
        >
          {getFieldDecorator("email", {
            rules: [
              {
                type: "email",
                message: "The input is not valid E-mail!"
              },
              {
                required: true,
                message: "Please input your E-mail!"
              }
            ]
          })(
            <Input
              type="email"
              placeholder="Email"
              onChange={this.onChange}
              autoComplete="username"
            />
          )}
        </Form.Item>
            <Form.Item label="Password" hasFeedback>
              <Popover placement="bottomLeft"
                       content={
                         <Test
                           isUpperCase={this.state.isUpperCase}
                           isLowerCase={this.state.isLowerCase}
                           isNumber={this.state.isNumber}
                           isValidCharacter={this.state.isValidCharacter}
                         />}
                       trigger="click"
                       visible={this.state.isPopoverVisible}
              >
              {getFieldDecorator("password", {
                rules: [
                  {
                    required: true,
                    message: "Please input your password!",
                  },
                  {
                    pattern: "^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,100})",
                    message:
                      " "
                  },
                  {
                    validator: this.validateToNextPassword
                  }
                ]
              })(
                <Input.Password
                  name="password"
                  autoComplete='off'
                  placeholder="New Password"
                  onChange={ this.handlePasswordValidation }
                  onBlur={() => this.setState({isPopoverVisible: false})}
                  onFocus={() => this.setState({isPopoverVisible: true})}
                />

              )}
              </Popover>
            </Form.Item>
        <Form.Item label="Confirm Password" hasFeedback>
          {getFieldDecorator("password_confirmation", {
            rules: [
              {
                required: true,
                message: "Please confirm your password!"
              },
              {
                validator: this.compareToFirstPassword
              }
            ]
          })(
            <Input.Password
              onBlur={this.handleConfirmBlur}
              autoComplete="new-password"
              placeholder="Confirm Password"
            />
          )}
        </Form.Item>
        <Form.Item label="Store Name" hasFeedback>
          {getFieldDecorator("store_name", {
            rules: [
              {
                required: true,
                message: "Please input your Store Name"
              },
              {
                pattern: "^(([^-\\s])(?=.{0,150}$)).*",
                message:
                  "Store name should be less then 160 character"
              },
            ]
          })(<Input type="text" placeholder="Store Name" onChange={this.onChangeStoreName} />)}
        </Form.Item>
          <Form.Item label="Phone Number" hasFeedback>
            {getFieldDecorator("phoneNumber", {
              rules: [
                {
                  required: false,
                  message: "Please input your Phone Number"
                }
              ]
            })(<Input type="number" placeholder="Phone Number" className={'no-spin'} />)}
          </Form.Item>
        <Form.Item>
          <Button
            type="primary"
            htmlType="submit"
            className="login-form-button"
            style={{ width: "100%" }}
            disabled={isRegistering}
          >
            <Spin spinning={isRegistering}>Create Account</Spin>
          </Button>
          <div style={{ marginTop: 8, textAlign: 'center' }}>
            <Link to={"/login" + window.location.search}>Login</Link>
          </div>
        </Form.Item>
      </Form>
      </div>
    );
  }
}

export default Form.create({ name: "register" })(RegistrationForm);
