import React, { Component, Fragment } from 'react';
import {Redirect} from 'react-router';
import { Row, Col } from 'antd/lib/index';

import HeaderNonAuth from '../../Layout/Header/HeaderNonAuth';
import PasswordResetForm from "../../Components/Forms/Auth/PasswordResetForm";
import Recaptcha from "../../Components/Forms/Auth/Recaptcha";

/**
 *
 */
export default class PasswordReset extends Component {
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
    return (
      <Fragment>
        <HeaderNonAuth {...this.props} />
        <div style={{paddingTop: '30px'}}>
          <Row>
            <Col xs={{ span: 22, offset: 1 }}
                 sm={{ span: 16, offset: 4 }}
                 md={{ span: 10, offset: 7 }}
                 lg={{ span: 8, offset: 8 }}
                 xl={{ span: 4, offset: 10 }}>
              <h1>Reset Password</h1>
              <Recaptcha children={<PasswordResetForm {...this.props}/>} />
            </Col>
          </Row>
        </div>
      </Fragment>
    );
  }
}
