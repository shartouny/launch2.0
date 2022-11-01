import React, {Component, Fragment} from "react";
import {Route, Switch, Redirect} from "react-router-dom";
import axios from "axios";
import {axiosConfig} from "../../utils/axios";

import Home from "./Pages/Home";
import ProductsAnon from "./Products/Products";
import Dashboard from "./User/Dashboard";
import ProductsAdmin from "./Products/Catalog";
import ProductsBuilder from "./Products/ProductBuilder";
import UserProducts from "./Products/UserProducts";
import UserProductEdit from "./Products/UserProductEdit";
import ImageLibrary from "./Products/ImageLibrary";
import Orders from "./Orders/Orders";
import Order from "./Orders/Order";
import ManualOrder from "./Orders/ManualOrder";
import ExportOrders from "./Orders/ExportOrders";
import SampleOrder from "./Orders/SampleOrder";
import IntegrationsDashboard from "./Integrations/IntegrationsDashboard";
import InstallShopifyPage from "./Integrations/InstallShopifyPage";
import PlatformStore from "./Integrations/PlatformStore";
import ApiIntegration from "./Integrations/ApiIntegration";
import AccountSettings from "./User/AccountSettings";
import AccountPaymentInfo from "./User/AccountPaymentsInfo";
import tokenService from "../../utils/tokenService";
import HeaderAuth from "../Layout/Header/HeaderAuth";
import UserProduct from "./Products/UserProduct";
import PlatformProduct from "./Integrations/PlatformProduct";
import VerifyUser from "./Pages/VerifyUser";
import RequestEmailVerification from "./Pages/RequestEmailVerification";


/**
 *
 */
export default class Content extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    axiosConfig(axios, props.history);
  }

  /**
   *
   * @returns {*}
   */
  render() {
    if (!tokenService.isLoggedIn()) {
      return <Redirect to={"/register"}/>;
    }

    if (!tokenService.getIsVerified()) {
      return <Redirect to={"/verify"}/>;
    }

    return (
      <Fragment>
        <HeaderAuth {...this.props} />
        <div className="page-wrapper" style={{paddingTop: 30}}>
          <Switch>
            <Route path="/verify/:id" component={props => <VerifyUser {...props} />}/>
            <Route path="/verify" component={RequestEmailVerification}/>
            <Route exact path="/page/" component={Home}/>
            <Route path="/products" component={ProductsAnon}/>
            <Route path="/dashboard" component={Dashboard}/>
            <Route path="/catalog" component={ProductsAdmin}/>
            <Route path="/product-design/:id" component={ProductsBuilder}/>
            <Route path="/my-products/:id/edit" component={UserProductEdit}/>
            <Route path="/my-products/:id" component={UserProduct}/>
            <Route path="/my-products" component={UserProducts}/>
            <Route path="/image-library" component={ImageLibrary}/>
            <Route path="/orders/:id" component={props => <Order {...props} />}/>
            <Route path="/orders" component={Orders}/>
            <Route path="/manual-order" component={ManualOrder}/>
            <Route path="/export-orders" component={ExportOrders}/>
            <Route path="/sample-order" component={SampleOrder}/>
            <Route path="/api-integration" component={ApiIntegration}/>
            <Route path="/integrations/:storeId/products/:id" component={PlatformProduct}/>
            <Route path="/integrations/:id/products" component={PlatformStore}/>
            <Route path="/integrations" component={IntegrationsDashboard}/>
            <Route path="/install-shopify" component={InstallShopifyPage}/>
            <Route path="/account-settings" component={AccountSettings}/>
            <Route path="/billing" component={AccountPaymentInfo}/>
            <Route component={ProductsAdmin}/>
          </Switch>
        </div>
      </Fragment>
    );
  }
}
