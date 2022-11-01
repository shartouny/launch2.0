import React, { Component } from 'react';
import { CardElement } from '@stripe/react-stripe-js';
import '../../../../css/StripeStyles.css';

export default class CardSection extends Component {
  render() {
    return (
      <div style={{padding: '0px 0px 15px'}}>
        <label style={{fontSize: '14px', padding: '0 0 8px', lineHeight: 1.5}}>
          Card details
          <CardElement />
        </label>
      </div>
    );
  };
}
