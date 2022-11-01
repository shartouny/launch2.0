import React, { Component, Fragment } from 'react';
import { Row, Col } from 'antd/lib/index';
import axios from 'axios';

import { Spin } from "antd";
import qs from "qs";
import { message } from "antd/es";

import tokenService from '../../../utils/tokenService';
import HeaderAuth from "../../Layout/Header/HeaderAuth";
import { axiosConfig } from "../../../utils/axios";

/**
 *
 */
export default class VerifyUser extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    axiosConfig(axios, props.history);
    this.state = {
      id: props.match.params.id,
      expires: qs.parse(props.location.search, { ignoreQueryPrefix: true }).expires,
      hash: qs.parse(props.location.search, { ignoreQueryPrefix: true }).hash,
      signature: qs.parse(props.location.search, { ignoreQueryPrefix: true }).signature
    };

    axios.defaults.baseURL = '';
    axios.post(`${this.props.location.pathname}${this.props.location.search}`, {...this.state}).then(res => {
      if(res.status === 204){
        tokenService.saveIsVerified(true);
        this.props.history.push('/billing');
        message.success('Your email has been verified');
      }
    });
  }
  /**
   *
   * @returns {*}
   */
  render() {
    return (
      <Fragment>
        <HeaderAuth {...this.props} />
        <div style={{paddingTop: '30px'}}>
          <Row>
            <Col xs={{ span: 22, offset: 1 }}
                 sm={{ span: 16, offset: 4 }}
                 md={{ span: 10, offset: 7 }}
                 lg={{ span: 8, offset: 8 }}
                 xl={{ span: 4, offset: 10 }}>
              <h1><Spin spinning={true}/>Verifying Email</h1>
            </Col>
          </Row>
        </div>
      </Fragment>
    );
  }
}
