import React, { Component } from 'react';
import axios from 'axios';
import {
  ElementsConsumer,
  CardElement,
} from '@stripe/react-stripe-js';

import StripeCardSection from './StripeCardSection';
import BillingAddressForm from './BillingInformationForm';

import { displayErrors } from '../../../utils/errorHandler';
import localStorage from '../../../utils/localStorage';

/**
 *
 */
class CardSetupForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    const { accountAddress } = props;
    const {
      firstName = '',
      lastName = '',
      address1 = '',
      address2 = '',
      city = '',
      state = '',
      zip = '',
      country = ''
    } = accountAddress;

    this.SUCCESS_TIMEOUT = 2000;

    this.state = {
      isSuccess: false,
      isLoading: false,
      /**
       * We need two sets of data to "remember"
       * the previous data we had before the switch.
       */
      address: {
        name: '',
        street1: '',
        street2: '',
        city: '',
        state: '',
        postalCode: '',
        country: ''
      },
      accountAddress: {
        name: firstName + ' ' + lastName,
        street1: address1,
        street2: address2,
        city,
        state: state,
        postalCode: zip,
        country: country
      },
      previousAddress: {},
    };
  };
  /**
   *
   * @param {{}} form
   * @param {boolean} isActive
   */
  onUseAccountInfo = (isActive, form) => {
    const {
      accountAddress,
      previousAddress,
    } = this.state;

    if (isActive) {
      this.setState(prevState => {
        return {
          ...prevState,
          address: {
            ...accountAddress,
          },
          previousAddress: prevState.address,
        }
      }, () => this.setBillingFields(form));
      return;
    }

    this.setState(prevState => {
      return {
        ...prevState,
        address: {
          ...previousAddress,
        }
      }
    }, () => this.setBillingFields(form));
  };
  /**
   *
   * @param {{}} form
   */
  setBillingFields = form => {
    const { address } = this.state;

    form.setFields({
      name: {
        value: address.name,
      },
      street1: {
        value: address.street1,
      },
      street2: {
        value: address.street2,
      },
      city: {
        value: address.city,
      },
      state: {
        value: address.state,
      },
      postalCode: {
        value: address.postalCode,
      },
      country: {
        value: address.country,
      },
    });
  };
  /**
   *
   * @param name
   * @param value
   */
  onBillingAddressChange = (name, value) => {
    this.setState(prevState => ({
      address: {
        ...prevState.address,
        [name]: value,
      },
    }));
  };
  /**
   *
   * @param {{}} event
   * @param {{}} form
   * @returns {Promise<void>}
   */
  onBillingSubmit = (event, form) => {
    event.preventDefault();
    const {
      address,
    } = this.state;
    const {
      stripe,
      elements,
    } = this.props;

    form.validateFields(err => {
      if (err) {
        return err;
      }
      return false;
    })
      .then(res => this.onStripeSubmit(stripe, elements, address))
      .catch((error) => displayErrors(error));
  };
  /**
   *
   * @param {Promise} stripe
   * @param {{}} elements
   * @param {{}} address
   * @returns {Promise<void>}
   */
  onStripeSubmit = async (stripe, elements, address) => {
    const {
      name,
      street1,
      street2,
      city,
      state,
      postalCode,
      country
    } = address;

    const clientSecret = localStorage.get('client_stripe_secret').toString();
    const clientEmail = localStorage.get('client_stripe_email').toString();

    if (!stripe || !elements) {
      return;
    }

    this.setState({isLoading: true});
    const result = await stripe.confirmCardSetup(clientSecret, {
      payment_method: {
        card: elements.getElement(CardElement),
        billing_details: {
          email: clientEmail,
          name,
          address: {
            city,
            line1: street1,
            line2: street2,
            state,
            postal_code: postalCode,
            country: country
          }
        },
      }
    });

    /**
     * Validate billing form and card, will receive two errors if both fail.
     */
    if (result.error) {
      displayErrors('Confirm card details and try again');
      this.setState({isLoading: false});
      return;
    }

    /**
     * We encountered no errors, lets get the payment_method key and save
     * into our database for use later.
     */
    this.submitCustomerInfo(result);
  };
  /**
   *
   * @param {{}} setupIntent
   */
  submitCustomerInfo = ({ setupIntent }) => {
    const { payment_method, id } = setupIntent;

    axios.post('/stripe/save-payment-method', null, {
      headers: {
        'Content-Type': 'application/json',
      },
      params: {
        id,
        payment_method
      },
    })
      .then(() => {
        this.setState({isSuccess: true}, () => {
          setTimeout(
            () => this.props.renderPaymentHistory()
            , this.SUCCESS_TIMEOUT
          );
        });
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({isLoading: false});
        localStorage.delete('client_stripe_secret');
        localStorage.delete('client_stripe_email');
      });
  };
  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      address,
      isLoading,
      isSuccess,
    } = this.state;

    /**
     * Render success message
     */
    if (isSuccess) return (
      <p style={{padding: '20px', textAlign: 'center'}}>
        Payment method <span style={{color: 'green'}}>successfully</span> captured!
      </p>
    );

    return (
      <>
        <StripeCardSection />
        <BillingAddressForm
          isLoading={isLoading}
          onBillingSubmit={this.onBillingSubmit}
          onBillingAddressChange={this.onBillingAddressChange}
          onUseAccountInfo={this.onUseAccountInfo}
          accountBilling={address}
        />
      </>
    );
  }
}

/**
 *
 * @returns {JSX.Element}
 * @constructor
 */
export default function InjectedCardSetupForm(props) {
  const {
    accountAddress,
    renderPaymentHistory
  } = props;

  return (
    <ElementsConsumer
      accountAddress={accountAddress}
      renderPaymentHistory={renderPaymentHistory}
    >
      {({stripe, elements}) => (
        <CardSetupForm
          stripe={stripe}
          elements={elements}
          accountAddress={accountAddress}
          renderPaymentHistory={renderPaymentHistory}
        />
      )}
    </ElementsConsumer>
  );
}
