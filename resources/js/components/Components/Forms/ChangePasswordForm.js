import React, {Component} from 'react';
import {Row, Col, Typography, Form, Input, Button, message, Spin} from 'antd/lib/index';
import Recaptcha from "./Auth/Recaptcha";
import axios from "axios";
import tokenService from "../../../utils/tokenService";
import {displayErrors} from "../../../utils/errorHandler";
import {axiosConfig} from "../../../utils/axios";

const { Title } = Typography;

class ChangePasswordForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      confirmDirty: false,
      isChangePassword: false,
    }
  };

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.handleChangePassword(values);
      }
    });
  };

  handleChangePassword = (data) => {
      this.setState({ isChangePassword: true }, () => {
        axios
          .post("/account/change-password", {
            currentPassword: data.currentPassword,
            password: data.password,
            password_confirmation: data.password_confirmation
          })
          .then(res => {
            if (res) {
              message.success('Password Changed');
              tokenService.saveToken(res.data.api_token);
              axiosConfig(axios, this.props.history);
              this.props.form.resetFields();
              this.setState({ isChangePassword: false });
            }
          })
          .catch(error => {
            this.setState({ isChangePassword: false });
            this.props.form.resetFields();
            const { response } = error;
            if (response.status === 422) {
              displayErrors(error);
            }
          })
          .catch(error => {
            displayErrors(error);
            this.props.form.resetFields();
          });
      })
  }

  validateToNextPassword = (rule, value, callback) => {
    const { form } = this.props;
    if (value && this.state.confirmDirty) {
      form.validateFields(["confirm"], { force: true });
    }
    callback();
  };

  compareToFirstPassword = (rule, value, callback) => {
    const { form } = this.props;
    if (value && value !== form.getFieldValue("password")) {
      callback("Confirmation password must match the password field");
    } else {
      callback();
    }
  };

  handleConfirmBlur = e => {
    const { value } = e.target;
    this.setState({ confirmDirty: this.state.confirmDirty || !!value });
  };

  render() {
    const { getFieldDecorator } = this.props.form;
    return(
      <Form onSubmit={this.handleSubmit} layout={'vertical'}>
        {/*Current Password*/}
        <Row>
          <Col>
            <Form.Item label='Current Password'>
              {getFieldDecorator('currentPassword', {
                rules: [{
                  required: true,
                  message: 'Please input your Current Password'
                }],

              })(
                <Input.Password placeholder={'Current Password'}/>
              )}
            </Form.Item>
          </Col>
        </Row>
        {/*New Password*/}
        <Row>
          <Col>
            <Form.Item label="New Password" hasFeedback>
              {getFieldDecorator("password", {
                rules: [
                  {
                    required: true,
                    message: "Please input your new password!"
                  },
                  {
                    pattern: "^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,})",
                    message:
                      "Password must be 8 characters or longer and contain at least one lowercase letter, one uppercase letter and a number"
                  },
                  {
                    validator: this.validateToNextPassword
                  }
                ]
              })(
                <Input.Password
                  name="password"
                  autoComplete="new-password"
                  placeholder="New Password"
                />
              )}
            </Form.Item>
          </Col>
        </Row>
        {/*Confirm Password*/}
        <Row>
          <Col>
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
          </Col>
        </Row>
        {/*Save Button*/}
        <Form.Item>
          <Button type="primary" htmlType="submit">
            <Spin spinning={this.state.isChangePassword}>Change Password</Spin>
          </Button>
        </Form.Item>
      </Form>
    )
  }
}

export default Form.create({ name: 'account_information' })(ChangePasswordForm);
