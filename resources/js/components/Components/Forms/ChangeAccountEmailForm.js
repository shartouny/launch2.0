import React, {Component} from 'react';
import {Row, Col, Typography, Form, Input, Button, message, Spin} from 'antd/lib/index';
import axios from "axios";
import {displayErrors} from "../../../utils/errorHandler";
import tokenService from "../../../utils/tokenService";
import {axiosConfig} from "../../../utils/axios";

class ChangeAccountEmailForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      confirmDirty: false,
      isChangeEmail: false,
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
        this.handleChangeEmail(values);
      }
    });
  };

  handleChangeEmail = (data) => {
      this.setState({ isChangeEmail: true }, () => {
        axios
          .post("/account/change-email", {
            current_email: data.current_email,
            email: data.email,
            email_confirmation: data.email_confirmation
          })
          .then(res => {
            message.success('Email Changed');
            tokenService.deleteToken();
            this.props.form.resetFields();
            this.setState({
              isChangeEmail: false
            });

            window.location.href = '/login';

          })
          .catch(error => {
            this.setState({ isChangeEmail: false });
            displayErrors(error);
          })
      })
  }

  validateToNextEmail = (rule, value, callback) => {
    const { form } = this.props;
    if (value && this.state.confirmDirty) {
      form.validateFields(["confirm"], { force: true });
    }
    callback();
  };

  compareToFirstEmail = (rule, value, callback) => {
    const { form } = this.props;
    if (value && value !== form.getFieldValue("email")) {
      callback("Confirmation email must match the email field");
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
        {/*Current Email*/}
        <Row>
          <Col>
            <Form.Item label='Current Email'>
              {getFieldDecorator('current_email', {
                rules: [{
                  type: "email",
                  required: true,
                  message: 'Please input your Current Email'
                }],

              })(
                <Input
                  type="email"
                  placeholder={'Current Email'}
                />
              )}
            </Form.Item>
          </Col>
        </Row>
        {/*New Email*/}
        <Row>
          <Col>
            <Form.Item label="New Email" hasFeedback>
              {getFieldDecorator("email", {
                rules: [
                  {
                    type: "email",
                    required: true,
                    message: "Please input your new email!"
                  },
                  {
                    message:
                      "Please enter a valid email address"
                  },
                  {
                    validator: this.validateToNextEmail
                  }
                ]
              })(
                <Input
                  type="email"
                  name="email"
                  autoComplete="new-email"
                  placeholder="New Email"
                />
              )}
            </Form.Item>
          </Col>
        </Row>
        {/*Confirm Email*/}
        <Row>
          <Col>
            <Form.Item label="Confirm Email" hasFeedback>
              {getFieldDecorator("email_confirmation", {
                rules: [
                  {
                    type: "email",
                    required: true,
                    message: "Please confirm your email!"
                  },
                  {
                    validator: this.compareToFirstEmail
                  }
                ]
              })(
                <Input
                  type="email"
                  onBlur={this.handleConfirmBlur}
                  autoComplete="new-email"
                  placeholder="Confirm Email"
                />
              )}
            </Form.Item>
          </Col>
        </Row>
        {/*Save Button*/}
        <Form.Item>
          <Button type="primary" htmlType="submit">
            <Spin spinning={this.state.isChangeEmail}>Change Email</Spin>
          </Button>
        </Form.Item>
      </Form>
    )
  }
}

export default Form.create({ name: 'account_email_update' })(ChangeAccountEmailForm);
