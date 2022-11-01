import React, { Component } from "react";
import axios from "axios";
import { Col, Icon, Row, Menu, message } from "antd";
import { Link, Redirect } from "react-router-dom";

import HeaderBrand from "./HeaderBrand";

import tokenService from "../../../utils/tokenService";
import { axiosLogout } from "../../../utils/axios";

const { SubMenu } = Menu;

/**
 *
 */
class NavMenu extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      current: "dashboard",
      isAuthenticated: !!tokenService.getToken(),
      isVerified: tokenService.getIsVerified()
    };
  }

  /**
   *
   * @param {{}} e
   */
  handleClick = e => {
    this.setState({
      current: e.key
    });
  };
  /**
   *
   * @param {{}} e
   */
  handleLogout = e => {
    e.preventDefault();

    axios.defaults.baseURL = "";
    axios.post("/api/logout").finally(() => {
      document.cookie = `token=`;
      tokenService.deleteToken();
      axiosLogout(axios);
      this.setState({ isAuthenticated: false });
    });
  };

  /**
   *
   * @returns {*}
   */
  render() {
    const { isAuthenticated, isVerified } = this.state;

    if (!isAuthenticated) {
      return <Redirect to={"/login"} />;
    }

    if (!isVerified) {
      return (
        <Menu
          onClick={this.handleClick}
          selectedKeys={[this.state.current]}
          id="nav"
          mode="horizontal"
          overflowedIndicator={<Icon type="menu" />}
        >
          <SubMenu
            title={
              <span className="submenu-title-wrapper">
                Account <Icon type="caret-down" />
              </span>
            }
          >
            <Menu.ItemGroup>
              <Menu.Item key="account:3">
                <a onClick={this.handleLogout}>Logout</a>
              </Menu.Item>
            </Menu.ItemGroup>
          </SubMenu>
        </Menu>
      );
    }

    // allow to disable manual orders with env variable
    let allowManualOrders = true;
    if(process.env.MIX_ALLOW_MANUAL_ORDERS && parseInt(process.env.MIX_ALLOW_MANUAL_ORDERS) === 0){
      allowManualOrders = false;
    }

    return (
      <Menu
        onClick={this.handleClick}
        selectedKeys={[this.state.current]}
        id="nav"
        mode="horizontal"
        overflowedIndicator={<Icon type="menu" />}
      >
        <SubMenu
          title={
            <span className="submenu-title-wrapper">
              Products <Icon type="caret-down" />
            </span>
          }
        >
          <Menu.ItemGroup>
            <Menu.Item key="products:1">
              <Link to="/catalog">Create</Link>
            </Menu.Item>
            <Menu.Item key="products:2">
              <Link to="/my-products">My Products</Link>
            </Menu.Item>
          </Menu.ItemGroup>
        </SubMenu>

        {
          allowManualOrders ?
          <SubMenu
            title={
              <span className="submenu-title-wrapper">
                Orders <Icon type="caret-down"/>
              </span>
            }>
            <Menu.ItemGroup>
              <Menu.Item key="order:1"><Link to="/manual-order">Create</Link></Menu.Item>
              <Menu.Item key="order:2"><Link to="/orders">Orders</Link></Menu.Item>
            </Menu.ItemGroup>
          </SubMenu>
          :
          <Menu.Item key="orders">
            <Link to="/orders">Orders</Link>
          </Menu.Item>
        }

        <Menu.Item key="integrations">
          <Link to="/integrations">Stores</Link>
        </Menu.Item>

        <SubMenu
          title={
            <span className="submenu-title-wrapper">
              Support <Icon type="caret-down" />
            </span>
          }
        >
          <Menu.ItemGroup>
            <Menu.Item key="support:1">
              <a href="https://support.teelaunch.com" target="_blank">
                Help
              </a>
            </Menu.Item>
            <Menu.Item key="support:3">
              <a href="https://blog.teelaunch.com" target="_blank">
                Blog
              </a>
            </Menu.Item>
            <Menu.Item key="support:4">
              <a href="https://blog.teelaunch.com/status/" target="_blank">
                Production Times
              </a>
            </Menu.Item>
          </Menu.ItemGroup>
        </SubMenu>
        <SubMenu
          title={
            <span className="submenu-title-wrapper">
              Account <Icon type="caret-down" />
            </span>
          }
        >
          <Menu.ItemGroup>
            <Menu.Item key="account:1">
              <Link to="/account-settings">Settings</Link>
            </Menu.Item>
            <Menu.Item key="account:2">
              <Link to="/billing">Billing</Link>
            </Menu.Item>
            <Menu.Item key="account:3">
              <a onClick={this.handleLogout}>Logout</a>
            </Menu.Item>
          </Menu.ItemGroup>
        </SubMenu>
      </Menu>
    );
  }
}

/**
 *
 */
export default class HeaderAuth extends Component {
  render() {
    return (
      <>
        <div className="top-nav">
          <div className="page-wrapper">
            <Row>
              <Col xs={19} md={5} xxl={4}>
                <HeaderBrand />
              </Col>
              <Col xs={5} md={19} xxl={20}>
                <NavMenu {...this.props} />
              </Col>
            </Row>
          </div>
        </div>
      </>
    );
  }
}
