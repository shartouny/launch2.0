import React, { Component, Fragment } from "react";
import { Redirect } from "react-router";
import { Row, Col, Button, Typography, message } from "antd";

import tokenService from "../../../../utils/tokenService";
import shopifyLogo from "../../../Assets/shopify_logo.png";
import teelaunchLogo from "../../../Assets/teelaunch-logo-purple.svg";

import HeaderNonAuth from "../../../Layout/Header/HeaderNonAuth";
import { getQueryParameters } from "../../../../utils/parameters";
import { LinkOutlined } from "@ant-design/icons";
import { axiosConfig } from "../../../../utils/axios";
import axios from "axios";

const { Text, Link } = Typography;

/**
 *
 */
export default class ShopifyAuth extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    // set url params
    const query = getQueryParameters();

    this.state = {
      isLoading: true,
      isLoggedIn: tokenService.isLoggedIn(),
      redirect: null,
      queryString: query.queryString ? query.queryString : null,
      shopUrl: query.shop ? query.shop : null,
      account: null
    };

    const loginRedirectTo = encodeURIComponent(`/shopify/auth?queryString=${this.state.queryString}&shop=${this.state.shopUrl}`);
    this.loginLink = `/login?redirect=${loginRedirectTo}`;
  }

  componentDidMount() {
    this.authenticateUser();
  }

  setNotAssociated = () => {
    if (this.state.isLoggedIn) {
      // get logged in user information
      axiosConfig(axios, null);
      axios.get(`/account-check`)
        .then((res) => {
          if (res && res.data && res.data.data) {
            const account = res.data.data;
            this.setState({
              account: account,
              isLoading: false
            })
          }
        }).catch((e) => {
          console.log(e)
        }).finally(() => {
          this.setState({
            isLoading: false
          })
        })
    } else {
      // user is not logged in
      // redirect to login
      this.setState({
        redirect: this.loginLink
      });
    }
  }

  authenticateUser = () => {
    // call to authenticate linked account
    // make sure bearer token is set
    axiosConfig(axios, null);
    // change the base url. axiosConfig sets it to teelaunch api
    axios.defaults.baseURL = '/';
    const postData = {
      encoded_data: this.state.queryString
    };
    axios.post(
      "/shopify/api/authenticate",
      postData
    ).then((res) => {
      if(
        res.data.token &&
        res.data.emailVerified !== undefined
      ){
        tokenService.saveToken(res.data.token);
        tokenService.saveIsVerified(res.data.emailVerified === 1);

        //flag used for shopify app exclusive teelaunch adjustments
        sessionStorage.setItem('shopify_app', true);

        //logged in and associated Shopify account. Redirect to catalog
        this.setState({
          redirect:'/catalog'
        })
      } else {
        // account not associated
        this.setNotAssociated();
      }
    }).catch((e) => {
      console.log(e);
      const status = e.response.status;
      switch (status) {
        case 403:
          // hmac failed
          message.error("HMAC verification failed");
          this.setNotAssociated();
          break;
        case 401:
          // account not associated
          this.setNotAssociated();
          break;
        case 400:
          // missing parameters or something went wrong
          message.error("Something went wrong");
          this.setNotAssociated();
          break;
        default:
          // Unknown error
          message.error("Unknown error occurred");
          this.setNotAssociated();
          console.log(e);
      }
    }).finally(() => {
      // reset axios config back to teelaunch defaults
      axiosConfig(axios, null);
    })
  }

  associateAccount = () => {
    // make sure bearer token is set
    axiosConfig(axios, null);
    // change the base url. axiosConfig sets it to teelaunch api
    axios.defaults.baseURL = '/';
    const postData = {
      encoded_data: this.state.queryString
    };
    axios.post(
      "/shopify/api/associate",
      postData
    ).then((res) => {
      if(res.status === 200){
        // logged in and associated Shopify account. Redirect to catalog
        this.setState({
          redirect:'/catalog'
        })
      } else {
        // should never hit this
        message.error("Unknown error occurred");
        console.log('response.status', res.status);
        console.log('response.data', res.data);
      }
    }).catch((e) => {
      const status = e.response.status;
      switch (status) {
        case 403:
          // hmac failed
          message.error("HMAC verification failed");
          break;
        case 401:
          // user not logged in
          message.error("User authentication error");
          break;
        case 400:
          // missing parameters or something went wrong
          message.error("Something went wrong");
          break;
        default:
          // Unknown error
          message.error("Unknown error occurred");
          console.log(e);
      }
    }).finally(() => {
      // reset axios config back to teelaunch defaults
      axiosConfig(axios, null);
    })
  }

  /**
   *
   * @returns {*}
   */
  render() {
    const { account } = this.state;
    const user = account && account.user ? account.user : null;

    if (this.state.redirect) {
      return <Redirect to={this.state.redirect} />
    } else if (this.state.isLoggedIn) {
      return (
        <Fragment>
          <HeaderNonAuth {...this.props} />
          <div style={{ paddingTop: '30px' }}>
            <Row>
              <Col style={{
                textAlign: 'center'
              }}>
                <div style={{
                  textAlign: 'center',
                  display: 'inline-block'
                }}>
                  <img src={teelaunchLogo} style={{ height: 40 }} alt="teelaunch" />
                  {
                    user ?
                      <div>
                        <Text type="secondary">{user.email}</Text>
                      </div>
                      :
                      null
                  }
                </div>
                <div style={{
                  fontSize: 30,
                  color: '#6a6a6a',
                  position: 'relative',
                  height: 48
                }}>
                  <LinkOutlined style={{
                    position: 'absolute',
                    top: 12,
                    left: '50%',
                    transform: 'translateX(-50%)'

                  }} />
                </div>
                <div style={{
                  textAlign: 'center',
                  display: 'inline-block'
                }}>
                  <img src={shopifyLogo} style={{ height: 40 }} alt="Shopify" />
                  {
                    this.state.shopUrl ?
                      <div>
                        <Text type="secondary">{this.state.shopUrl}</Text>
                      </div>
                      :
                      null
                  }
                </div>
                <div>
                  {
                    account && this.state.shopUrl ?
                      <div>
                        <Button
                          type="primary"
                          style={{ marginTop: 30 }}
                          onClick={() => {
                            this.associateAccount();
                          }}
                        >
                          Connect
                        </Button>
                        <div style={{marginTop: 20}}>
                          <a href={this.loginLink}
                            onClick={(event) => {
                              event.preventDefault();
                              // log the user out
                              tokenService.deleteToken();
                              this.setState({
                                redirect:this.loginLink
                              })
                            }}
                          >
                            Not you?
                          </a>

                        </div>
                      </div>
                      :
                      null
                  }
                </div>
              </Col>
            </Row>
          </div>
        </Fragment>
      );
    } else {
      return (
        <Fragment>
          <HeaderNonAuth {...this.props} />
            {/* Blank until we get information  */}
        </Fragment>
      )
    }
  }
}
