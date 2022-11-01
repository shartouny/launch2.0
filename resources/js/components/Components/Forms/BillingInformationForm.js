import React, { Component } from 'react';
import {
  Form,
  Input,
  Button,
  Row,
  Col,
  Typography,
  Switch,
  Icon, Select
} from 'antd/lib/index';

const { Option } = Select;
import { displayErrors } from '../../../utils/errorHandler';
import { Checkbox } from "antd";
import axios from "axios";

const { Title } = Typography;

/**
 *
 */
class BillingAddressForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.styles = {
      removeDefaultSpacing: {
        marginBottom: 0,
        paddingBottom: 0,
      },
    };

    this.state = {
      isSwitchActive: false,
      hasAcceptedTerms: false,
      countries: []
    };
  };

  componentDidMount() {
    this.getCountries();
  };

  getCountries = () => {
    axios.get(`countries`).then(res => {
      const { data } = res.data;
      this.setState({ countries: data });
    }).catch(error => displayErrors(error))
      .finally();
  };

  /**
   *
   */
  toggleSwitch = () => {
    const { isSwitchActive } = this.state;
    this.setState({ isSwitchActive: !isSwitchActive });
  };
  /**
   *
   * @param {boolean} isActive
   */
  onAccountBillingSwitch = (isActive) => {
    this.toggleSwitch();
    const { form } = this.props;
    this.props.onUseAccountInfo(isActive, form);
  };
  /**
   *
   * @param {{}} event
   */
  handleSubmit = event => {
    event.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        displayErrors('Received values of form: ', values);
      }
    });
  };

  onAcceptTermsChange = () => {
    this.setState({ hasAcceptedTerms: !this.state.hasAcceptedTerms });
  };

  /**
   *
   * @param {{}} event
   */
  onBillingAddressChange = event => {
    const { isSwitchActive } = this.state;

    /**
     * If a user types in the inputs after they have switched
     * to use account address, we should switch back because
     * it is no longer using the same address as account.
     */
    if (isSwitchActive) {
      this.toggleSwitch();
    }

    this.props.onBillingAddressChange(event.target.name, event.target.value);
  };

  onChangeCountry = (value) => {
    this.props.onBillingAddressChange('country', value);
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      form,
      onBillingSubmit,
      isLoading,
    } = this.props;
    const {
      isSwitchActive,
      countries
    } = this.state;
    const { getFieldDecorator } = form;

    return (
      <>
        <Form
          onSubmit={event => onBillingSubmit(event, form)}
          layout={'vertical'}
          style={{ marginTop: '10px' }}
        >
          <Title level={3}>Billing Address</Title>
          <Form.Item>
            <Switch
              checkedChildren={<Icon type="check"/>}
              unCheckedChildren={<Icon type="close"/>}
              onChange={this.onAccountBillingSwitch}
              checked={isSwitchActive}
            />
            <span style={{ paddingLeft: '10px' }}>Same As Account Address</span>
          </Form.Item>
          <Form.Item label="Name:">
            {getFieldDecorator('name', {
              rules: [
                {
                  required: true,
                  message: 'Please input your Name'
                },
              ],
            })(
              <Input
                type="text"
                name='name'
                placeholder="Name"
                onChange={this.onBillingAddressChange}
              />,
            )}
          </Form.Item>
          <Form.Item label="Street 1">
            {getFieldDecorator('street1', {
              rules: [
                {
                  required: true,
                  message: 'Please input your Street Address'
                },
              ],
            })(
              <Input
                type="text"
                name='street1'
                placeholder="Street"
                onChange={this.onBillingAddressChange}
              />,
            )}
          </Form.Item>
          <Form.Item label="Street 2">
            {getFieldDecorator('street2', {
              rules: [
                {
                  required: false,
                  message: 'Please input your Street Address'
                },
              ],
            })(
              <Input
                type="text"
                name='street2'
                placeholder="Apt/Ste#"
                onChange={this.onBillingAddressChange}
              />,
            )}
          </Form.Item>
          <Form.Item label="City">
            {getFieldDecorator('city', {
              rules: [
                {
                  required: true,
                  message: 'Please input your City'
                },
              ],
            })(
              <Input
                type="text"
                name='city'
                placeholder="City"
                onChange={this.onBillingAddressChange}
              />,
            )}
          </Form.Item>

          <Form.Item>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item label="State" style={this.styles.removeDefaultSpacing}>
                  {getFieldDecorator('state', {
                    rules: [
                      {
                        required: false,
                        message: 'Please input your State'
                      },
                    ],
                  })(
                    <Input
                      type="text"
                      name='state'
                      placeholder="State"
                      onChange={this.onBillingAddressChange}
                    />,
                  )}
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item label="Postal Code" style={this.styles.removeDefaultSpacing}>
                  {getFieldDecorator('postalCode', {
                    rules: [
                      {
                        required: false,
                        message: 'Please input your Postal Code'
                      },
                    ],
                  })(
                    <Input
                      type="text"
                      name='postalCode'
                      placeholder="Postal Code"
                      onChange={this.onBillingAddressChange}
                    />,
                  )}
                </Form.Item>
              </Col>
            </Row>
          </Form.Item>


          <Row gutter={16}>
            <Col span={24}>
              <Form.Item label="Country">
                {getFieldDecorator('country', {
                  rules: [{ required: true, message: 'Please input your Country' }]
                })(
                  <Select name='country'
                          placeholder="Select a Country"
                          onChange={this.onChangeCountry}
                          showSearch
                          optionFilterProp="children"
                          filterOption={(input, option) =>
                            option.props.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                          }>
                    {countries.map(country => <Option key={country.code} value={country.code}>{country.name}</Option>)}
                  </Select>,
                )}
              </Form.Item>
            </Col>
          </Row>


          <Form.Item>
            {getFieldDecorator('acceptTerms', {
              rules: [
                {
                  required: true,
                  message: 'Please accept billing terms'
                },
              ],
            })(
              <Checkbox name='acceptTerms'
                        checked={this.state.hasAcceptedTerms}
                        onChange={this.onAcceptTermsChange}>
                I authorize teelaunch to send instructions to the financial institution that issued my card to take
                payments from my card account whenever orders containing teelaunch products are processed for
                fulfillment
                by teelaunch.
              </Checkbox>,
            )}
          </Form.Item>

          <Form.Item>
            <Button
              type="primary"
              htmlType="submit"
              loading={isLoading}
            >
              Add Payment
            </Button>
          </Form.Item>
        </Form>
      </>
    );
  }
}

export default Form.create({ name: 'billing_address' })(BillingAddressForm);
