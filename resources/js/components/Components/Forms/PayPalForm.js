import React, { Component } from 'react';
import axios from 'axios';
import DropIn from 'braintree-web-drop-in-react';
import {
  Typography,
  Spin, Button,
} from 'antd';

import { displayErrors } from '../../../utils/errorHandler';

const { Title } = Typography;

/**
 *
 */
export default class PayPalComponent extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      clientToken: '',
      isSuccess: false,
      displaySubmitButton: false,
      paymentMethodClicked: false
    };

    this.instance = null;

    this.styles = {
      paymentContainer: {
        display: 'flex',
        justifyContent: 'center',
        flexDirection: 'column',
        padding: '30px',
      },
    };
  };

  /**
   *
   */
  componentDidMount() {
    axios.post('/paypal/authorize', null, {
      headers: {
        'Content-Type': 'application/json',
      },
    })
      .then(response => {
        const { data } = response;

        this.setState({ clientToken: data.token });
      })
      .catch(error => displayErrors(error))
      .finally(() => {

      });
  };

  /**
   *
   * @returns {Promise<void>}
   */
  onPaymentClicked = async () => {
    const { paymentMethodClicked } = this.state;
    if(!paymentMethodClicked) {
      try {
        const paymentInfo = await this.instance.requestPaymentMethod();
        const { nonce, type, details } = paymentInfo;

        this.setState({ paymentMethodClicked: true });

        axios.post('/paypal/save-payment-method', null, {
          headers: {
            'Content-Type': 'application/json',
          },
          params: {
            nonce,
            type,
            ...details,
          },
        })
          .then(() => {
            this.setState({ isSuccess: true }, () => {
              setTimeout(
                () => this.props.renderPaymentHistory()
                , this.SUCCESS_TIMEOUT
              );
            });
          })
          .catch(error => displayErrors(error)).finally(() => this.setState({ paymentMethodClicked: false }));
      } catch (e) {
        displayErrors('Payment method unsuccessful');
      }
    }
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      clientToken,
      isSuccess,
      displaySubmitButton,
      paymentMethodClicked
    } = this.state;

    if (isSuccess) return (
      <p style={{ padding: '20px', textAlign: 'center' }}>
        Payment method <span style={{ color: 'green' }}>successfully</span> captured!
      </p>
    );

    return (
      <>
        <Title level={3}>Payment With PayPal</Title>
        <div style={this.styles.paymentContainer}>
          {clientToken ? (
            <>
              <DropIn
                onInstance={instance => {
                  this.instance = instance;
                  if(this.instance.isPaymentMethodRequestable()){
                    this.setState({displaySubmitButton: true});
                  }
                  this.instance.on('paymentMethodRequestable', () => this.setState({displaySubmitButton: true}));
                  this.instance.on('noPaymentMethodRequestable', () => this.setState({displaySubmitButton: false}));
                }}
                options={{
                  authorization: clientToken,
                  paymentOptionPriority: ['paypal'],
                  paypal: {
                    flow: 'vault',
                  },
                }}
              />
              {displaySubmitButton && <Button onClick={this.onPaymentClicked}><Spin spinning={paymentMethodClicked}>Continue With Paypal</Spin></Button>}
            </>
          ) : <Spin/>}
        </div>
      </>
    );
  }
}
