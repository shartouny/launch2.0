import React, { Component, Fragment } from 'react';
import { Redirect } from 'react-router';
import { Row, Col } from 'antd';

import LoginForm from '../../Components/Forms/Auth/LoginForm';

import tokenService from '../../../utils/tokenService';

import HeaderNonAuth from '../../Layout/Header/HeaderNonAuth';
import PasswordResetForm from "../../Components/Forms/Auth/PasswordResetForm";
import Recaptcha from "../../Components/Forms/Auth/Recaptcha";
import { getQueryParameters } from "../../../utils/parameters";
import registrationBanner from "../../../../images/registration_banner.png";

/**
 *
 */
export default class Login extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  }

  componentWillMount() {
    const url = new URL(window.location.href);
    const params = url.searchParams;
    const token = params.get('token');

    if(token){
      tokenService.saveToken(token);
      tokenService.saveIsVerified(true);
    }
  }

  /**
   *
   * @returns {*}
   */
  render() {
    /**
     * If already logged in, dont show login.
     */
    if (tokenService.isLoggedIn()) {
      // see if we have a redirect in the query string
      const query = getQueryParameters();
      if(query && query.redirect){
        return <Redirect to={decodeURIComponent(query.redirect)}/>;
      } else {
        return <Redirect to={'/catalog'}/>;
      }
    }

    return (
      <Fragment>
        <HeaderNonAuth {...this.props} />
        <div>
          <Row type='flex'>
            <Col xs={24} sm={24} md={8} lg={8} xl={8} style={{display: 'flex'}}>
              <img src={registrationBanner} width='80%' style={{margin: 'auto', padding: '5%'}}/>
            </Col>
            <Col xs={24} sm={24} md={16} lg={16} xl={16} style={{background: 'white', padding: '13% 12%'}}>
              <div style={{textAlign: 'center', padding: '5% 0'}}>
                <h1 style={{margin: '0', fontSize: '55px', lineHeight: '85%'}}>WELCOME BACK!</h1>
                <h4 style={{fontSize: '14px', color:'#EEA320'}}>LOGIN TO YOUR TEELAUNCH ACCOUNT</h4>
              </div>
              <Recaptcha children={<LoginForm {...this.props}/>}/>
            </Col>
          </Row>
        </div>
      </Fragment>
    );
  }
}
