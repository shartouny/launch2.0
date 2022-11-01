import React, { Component } from 'react';
import {
  Form,
  Input,
  Button,
  Row,
  Col,
  Switch,
} from 'antd/lib/index';


class BillingSettingsForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.props.handleDailyMaxCharge(values);
      }
    });
  };

  render() {
    const {
      dailyMaxChargeValue,
      dailyMaxChargeEnabled,
      dailyMaxChargeStatusChange,
    } = this.props;

    const { getFieldDecorator } = this.props.form;

    return (
      <Form onSubmit={this.handleSubmit} layout={'vertical'}>


        <Row style={{ marginBottom: '20px'}}>
          <Col>
            <div style={{ display: 'flex', alignItems: 'center' }}>
              <div style={{ fontSize: '22px', paddingLeft: '10px', paddingRight: '10px', width:'16.5%' }}>
                Daily Charge Limit
              </div>
              <Form.Item label="" valuepropname="checked" style={{paddingBottom: '0', height: '2px' }}>
                {getFieldDecorator('dailyMaxChargeEnabled', {
                  rules: [{
                    required: false,
                  }],
                  valuePropName: "checked",
                  onChange: dailyMaxChargeStatusChange,
                  initialValue: dailyMaxChargeEnabled
                })(
                  <Switch
                    size='small'
                    style={{ minWidth: '40px' }}
                  />
                  ,
                )}
              </Form.Item>
            </div>
            <div style={{ fontSize: '14px', paddingLeft: '10px' }}>
              Disable or limit daily charge to a maximum based on your preference.
            </div>
          </Col>
        </Row>

        <Row>
          <Col span={24}>
            <Form.Item label="Daily Max Charge Limit" style={{
              'display': dailyMaxChargeEnabled ? 'block' : "none"
            }}>
                {getFieldDecorator('dailyMaxChargeValue', {
                  rules: [{
                    required: dailyMaxChargeEnabled,
                    message: 'Please input your daily max charge limit (leave 0 to stop processing orders)'
                  }],
                  initialValue: dailyMaxChargeValue
                })(
                  <Input
                    type="number"
                    placeholder="Daily Max Charge Limit"
                    min={0}
                    disabled={!dailyMaxChargeEnabled}
                  />,
                )}
              </Form.Item>
          </Col>
        </Row>

        <Form.Item>
          <Button type="primary" htmlType="submit">
            Save Billing Settings
          </Button>
        </Form.Item>

      </Form>
    );
  }
}

export default Form.create({ name: 'billing_settings' })(BillingSettingsForm);
