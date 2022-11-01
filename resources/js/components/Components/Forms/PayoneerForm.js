import React, { Component } from 'react';
import {
  Button,
} from 'antd/lib/index';
import {
  Typography,
} from 'antd';
import axios from 'axios';
import { displayErrors } from '../../../utils/errorHandler';

const { Title } = Typography;

/**
 *
 */
export default class PayoneerComponent extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      base64AccountId: ''
    }
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
   * @param {string} state 
   */
  getPayoneerLoginURL = (state) => {
    return `${process.env.MIX_PAYONEER_LOGIN_URL}
      &client_id=${process.env.MIX_PAYONEER_CLIENT_ID}
      &redirect_uri=${process.env.MIX_PAYONEER_REDIRECT_URI}
      &state=${state}`
  }

  encodeAccountId = () => {
    const {accountId} = this.props;

    this.setState({
      base64AccountId: btoa(accountId)
    });
  }

  preserveAccountId = () => {
    axios.get(`payoneer/initiate-auth`)
      .then(({data}) => window.location.href = this.getPayoneerLoginURL(data.state))
      .catch(e => {
        displayErrors("Something went wrong while preserving account id")
        console.log(e);
      });
  }

  /**
   *
   * @returns {JSX.Element}
   */

  componentDidMount() {
    this.encodeAccountId();
  }

  render() {
    return (
      <>
        <Title level={3}>Payment With Payoneer</Title>
        <div style={this.styles.paymentContainer}>
          <Button onClick={this.preserveAccountId}>
            Login to Payoneer
          </Button>
          <p style={{alignSelf: 'center'}}>You will be redirected to Payoneer</p>
        </div>
      </>
    );
  }
}
