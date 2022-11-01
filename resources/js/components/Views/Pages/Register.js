import React, { Component } from 'react';
import { Redirect } from "react-router";
import { Col, Row } from "antd";

import RegisterForm from "../../Components/Forms/Auth/RegisterForm";

import HeaderNonAuth from "../../Layout/Header/HeaderNonAuth";

import tokenService from "../../../utils/tokenService";
import Recaptcha from "../../Components/Forms/Auth/Recaptcha";
import registrationBanner from "../../../../images/registration_banner.png";

/**
 *
 */
export default class Register extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  }
  /**
   *
   * @returns {*}
   */
  render() {
    /**
     * If already logged in, dont show register.
     */
    if (tokenService.isLoggedIn()) {
      return <Redirect to={'/catalog'} />;
    }

    return (
      <div>
        <HeaderNonAuth {...this.props} />
        <Row type='flex'>
          <Col xs={24} sm={24} md={8} lg={8} xl={8} style={{display: 'flex'}}>
              <img src={registrationBanner} width='80%' style={{margin: 'auto', padding: '5%'}}/>
          </Col>
          <Col xs={24} sm={24} md={16} lg={16} xl={16} style={{background: 'white', padding: '0 12%'}}>
            <div style={{textAlign: 'center', padding: '5% 0'}}>
              <h1 style={{margin: '0', fontSize: '50px', lineHeight: '85%'}}>ARE YOU IN NEED OF HELP GROWING YOUR BUSINESS?</h1>
              <h4 style={{fontSize: '17px', color:'#EEA320'}}>DO YOU WANT ACCESS TO OVER 300 PRODUCTS, INCLUDING MANY EXCLUSIVE TO TEELAUNCH?</h4>
            </div>

            <Recaptcha children={<RegisterForm {...this.props}/>}/>
          </Col>
        </Row>
      </div>
    );
  }
}
