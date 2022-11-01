import React, { Component } from 'react';
import axios from 'axios';
import {
  Row,
  Col,
  Typography,
  Tabs,
  Spin,
} from 'antd';
import { CreditCardOutlined } from '@ant-design/icons';
import { Elements } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js/pure';

import StripeRegisterForm from '../../Components/Forms/StripeRegisterForm';
import StripeCardForm from '../../Components/Forms/StripeCardForm';
import PayPalComponent from '../../Components/Forms/PayPalForm';
import PayoneerComponent from '../../Components/Forms/PayoneerForm';

import paypalLogo from '../../Assets/paypal_icon.jpg';
import payoneerLogo from '../../Assets/payoneer_logo.png';

import { displayErrors } from '../../../utils/errorHandler';
import localStorage from '../../../utils/localStorage';

import AccountPaymentHistory from './AccountPaymentHistory';

const { Title } = Typography;
const { TabPane } = Tabs;

/**
 *
 */
export default class AccountSettings extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      activePaymentTab: '0',
      stripeSecretKey: '',
      isLoadingStripe: false,
      email: '',
      paymentHistory: [],
      accountAddress: {},
      activePaymentMethod: {},
      isLoadingPayments: false,
      shouldRenderAdd: false,
      renderPaymentHistory: false,
    };
  };
  /**
   *
   */
  componentDidMount() {
    const paymentHistoryPromise = axios.get('/account/payment-history?page=1');
    const paymentMethodsActivePromise = axios.get('/account/payment-methods/active');
    const accountInfoPromise = axios.get('/account');
    this.setState({isLoadingPayments: true});

    /**
     * This will run in parallel no need for separate requests.
     */
    Promise.all([
      paymentHistoryPromise,
      paymentMethodsActivePromise,
      accountInfoPromise,
    ])
      .then(res => {
        const [
          paymentHistory,
          activePayment,
          accountInfo,
        ] = res;

        this.setState({
          email: this.getAccountEmail(accountInfo),
          accountAddress: this.getAccountAddress(accountInfo),
          paymentHistory: this.extractDataObject(paymentHistory),
          activePaymentMethod: this.extractDataObject(activePayment),
        });
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({isLoadingPayments: false}));
  };

  /**
   *
   * @returns {boolean}
   */
  isLocalEnvironment = () => process.env.MIX_APP_ENV.toLowerCase() === "local";

  /**
   *
   * @returns {boolean}
   */
  isStageEnvironment = () => window.location.origin === "https://stage-app-2.teelaunch.com";

  /**
   *
   * @returns {{}}
   */
  getPaymentMethods = () => {
    let methods = {
      stripe: {
        name: (
          <>
            <CreditCardOutlined />
            Card
          </>
        ),
        id: '0',
        method: this.renderStripeElement(),
      },
      paypal: {
        name: (
          <span>
            <img
              src={paypalLogo}
              style={{width: '15px', height: '15px', marginRight: '5px'}}
            />
            PayPal
          </span>
        ),
        id: '1',
        component: <PayPalComponent renderPaymentHistory={() => this.setState({renderPaymentHistory: true})}/>,
      }
    };

    if(window.location.hash.includes('payoneer')){
      methods['payoneer'] = {
        id: '2',
        name: <img src={payoneerLogo} style={{height: '22px'}} />,
        component: <PayoneerComponent renderPaymentHistory={() => this.setState({renderPaymentHistory: true})} />
      };
    }

    return methods;
  };
  /**
   *
   * @param {{}} data
   */
  extractDataObject = ({ data }) => {
    return data.data;
  };
  /**
   *
   * @param {{}} data
   * @returns {string}
   */
  getAccountEmail = ({ data }) => {
    const { user } = data.data;

    if (!user.email) {
      return '';
    }

    return user.email;
  };

  /**
   *
   * @param {{}} data
   */
  getAccountAddress = ({ data }) => {
    const { data:address } = data;

    if (
      !address.shippingLabel
      || !address.shippingLabel.billingAddress
    ) {
      return '';
    }

    const { billingAddress } = address.shippingLabel;
    return billingAddress;
  };
  /**
   *
   * @param {{}} event
   * @param {{}} form
   */
  onStripeRegister = (event, form) => {
    event.preventDefault();

    form.validateFields((err, value) => {
      if (err) {
        return displayErrors('There was a problem filling out all fields.');
      }
      const { email } = value;
      this.setState({isLoadingStripe: true, email});

      axios.post('/stripe/authorize', null, {
        headers: {
          'Content-Type': 'application/json',
        },
        params: {
          email,
        },
      })
        .then(res => {
          if (!res.data) {
            displayErrors();
            return;
          }
          const { data } = res.data;
          this.setState({stripeSecretKey: data.clientSecret});
        })
        .catch(error => displayErrors(error))
        .finally(() => this.setState({isLoadingStripe: false}))
    });
  };
  /**
   *
   * @returns {JSX.Element}
   */
  renderStripeElement = () => {
    const {
      stripeSecretKey,
      email,
      accountAddress,
    } = this.state;

    if (!stripeSecretKey) {
      return (
        <StripeRegisterForm
          onStripeRegister={this.onStripeRegister}
          email={email}
        />
      );
    }

    localStorage.save('client_stripe_secret', stripeSecretKey);
    localStorage.save('client_stripe_email', email);

    const stripePromise = loadStripe(process.env.MIX_STRIPE_API_KEY);

    return (
      <Elements stripe={stripePromise}>
        <StripeCardForm
          accountAddress={accountAddress}
          renderPaymentHistory={() => this.setState({renderPaymentHistory: true})}
        >
        </StripeCardForm>
      </Elements>
    );
  };
  /**
   *
   * @param {{}} event
   * @param {{}} form
   */
  onBillingSubmit = (event, form) => {
    event.preventDefault();
    form.validateFields((err, values) => {
      if (err) {
        displayErrors('There was a problem filling out all fields.');
      }
    });
  };
  /**
   *
   */
  onAddPayment = () => {
    this.setState(prevState => ({
      ...prevState,
      shouldRenderAdd: true,
      renderPaymentHistory: false,
      activePaymentMethod: {
        ...prevState.activePaymentMethod,
        isActive: 0,
      },
      stripeSecretKey: null
    }));
  };
  /**
   *
   * @param {string} key
   */
  onPaymentTabClick = key => {
    this.setState({activePaymentTab: key});
  };
  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      activePaymentMethod,
      isLoadingStripe,
      activePaymentTab,
      paymentHistory,
      isLoadingPayments,
      shouldRenderAdd,
      renderPaymentHistory,
    } = this.state;
    const payments = this.getPaymentMethods();

    if (isLoadingPayments) return <Spin />;

    if (renderPaymentHistory) {
      return (
        <AccountPaymentHistory
          history={this.props.history}
          paymentHistory={paymentHistory}
          activePaymentMethod={activePaymentMethod}
          onAddPayment={this.onAddPayment}
          componentRendered={true}
        />
      );
    }

    /**
     * If I have an active payment, or any payment history
     * load the account payment history section, otherwise
     * we should load the add new payment section.
     */
    if (
      (paymentHistory.length
      || activePaymentMethod.isActive)
      && !shouldRenderAdd
    ) {
      return (
        <AccountPaymentHistory
          history={this.props.history}
          paymentHistory={paymentHistory}
          activePaymentMethod={activePaymentMethod}
          onAddPayment={this.onAddPayment}
          componentRendered={false}
        />
      );
    }

    return (
      <>
        <Row>
          <Col span={8} offset={8}>
            <Title level={2} className='text-center'>
              Add Billing Method
            </Title>
            <Tabs
              defaultActiveKey="0"
              activeKey={String(activePaymentTab)}
              onChange={this.onPaymentTabClick}
            >
            {Object.keys(payments).map(payment => (
              <TabPane
                tab={payments[payment].name}
                key={payments[payment].id}
              >
                {/*TODO Other payment methods can piggyback this*/}
                {!isLoadingStripe ? (
                  payments[payment].component
                    ? payments[payment].component
                    : payments[payment].method
                ) : <Spin />}
              </TabPane>
            ))}
            </Tabs>
          </Col>
        </Row>
      </>
    );
  }
}
