import React, { Component, Fragment } from 'react';
import { Row, Col } from 'antd/lib/index';
import axios from 'axios';

import { Button, Spin } from "antd";
import qs from "qs";
import { message } from "antd/es";
import HeaderAuth from "../../Layout/Header/HeaderAuth";
import { axiosConfig } from "../../../utils/axios";
import { displayErrors } from "../../../utils/errorHandler";
import tokenService from '../../../utils/tokenService';

/**
 *
 */
export default class RequestEmailVerification extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      isRequestingVerification: false
    };

    // check to see if email is verified
    axiosConfig(axios, props.history);
    axios.get('/verify/check').then((res) => {
      if(res.data && res.data.is_verified){
        tokenService.saveIsVerified(true);
        this.props.history.push('/billing');
        message.success('Your email has been verified');
      }
    })
  }

  componentDidMount() {
    const facebookPixelScript = '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,\'script\',\'https://connect.facebook.net/en_US/fbevents.js\');fbq(\'init\', \'132137645794080\');fbq(\'track\', \'PageView\');';
    const script = document.createElement("script");
    script.textContent = facebookPixelScript;
    document.getElementsByTagName('div')[document.getElementsByTagName('div').length-1].appendChild(script);

    const noScript = document.createElement("noscript");
    const noScriptImage = document.createElement("img");
    noScriptImage.height = 1;
    noScriptImage.width = 1;
    noScriptImage.style = 'display:none';
    noScriptImage.src = 'https://www.facebook.com/tr?id=132137645794080&ev=PageView&noscript=1';
    noScript.appendChild(noScriptImage);

    document.getElementsByTagName('div')[document.getElementsByTagName('div').length-1].appendChild(noScript);
  }

  requestVerification = () => {
    this.setState({ isRequestingVerification: true }, () => {
      axios.post(`verify/resend`).then(res => {
        message.success('Verification link sent to your email');
      }).catch(error => {
        displayErrors('Failed to send verification email');
      }).finally(() => this.setState({ isRequestingVerification: false }));
    });
  };

  /**
   *
   * @returns {*}
   */
  render() {
    const { isRequestingVerification } = this.state;

    return (
      <Fragment>
        <HeaderAuth {...this.props} />
        <div style={{ paddingTop: '30px' }}>
          <Row>
            <Col xs={{ span: 22, offset: 1 }}
                 sm={{ span: 16, offset: 4 }}
                 md={{ span: 10, offset: 7 }}
                 lg={{ span: 12, offset: 6 }}
                 xl={{ span: 8, offset: 8 }}>
              <h1>Verify Your Email</h1>
              <p>You must verify your email address in order to use the <b>teelaunch</b>.</p>
              <p>Make sure to check your spam folder if you don't see the email in your inbox.</p>
              <Button onClick={this.requestVerification} disabled={isRequestingVerification}>
                <Spin spinning={isRequestingVerification}>Resend Verification Email</Spin>
              </Button>
            </Col>
          </Row>
        </div>
      </Fragment>
    );
  }
}
