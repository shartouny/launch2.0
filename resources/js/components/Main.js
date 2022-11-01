import React, { Component, Fragment } from 'react';
import rootReducer from './Reducers';
import ReactDOM from 'react-dom';
import {
  BrowserRouter as Router,
  Route,
  Switch,
} from 'react-router-dom';
import { Provider } from 'react-redux';
import { createStore } from 'redux';

import Footer from './Layout/Footer/Footer';
import HeaderNonAuth from './Layout/Header/HeaderNonAuth';

import Content from './Views/Content';
import Login from './Views/Pages/Login';
import Page from "./Views/Pages/Page";
import Register from "./Views/Pages/Register";
import RequestPasswordReset from "./Views/Pages/RequestPasswordReset";
import PasswordReset from "./Views/Pages/PasswordReset";

import ShopifyAuth from "./Views/Platforms/Shopify/ShopifyAuth";

import * as Sentry from "@sentry/react";
import { Integrations } from "@sentry/tracing";
import VerifyUser from "./Views/Pages/VerifyUser";
import RequestEmailVerification from "./Views/Pages/RequestEmailVerification";

import { message } from "antd/es";

Sentry.init({
  environment: process.env.MIX_APP_ENV,
  dsn: process.env.MIX_SENTRY_REACT_DSN,
  integrations: [
    new Integrations.BrowserTracing(),
  ],
  tracesSampleRate: process.env.MIX_SENTRY_TRACES_SAMPLE_RATE
});

const store = createStore(rootReducer);



/**
 *
 */
class Main extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      current: window.location.pathname,
      page: 'home',
      target: '',
      pageContent: 'isLoading',
    };

    message.config({
      top: 8,
      maxCount: 3,
      duration: 3
    });
  };

  /**
   *
   * @param {{}} target
   */
  updateNavigation = (target) => {
    this.setState({ page: target.key });
  };

  /**
   *
   * @returns {*}
   */
  render() {
    const {
      page,
      pageContent,
    } = this.state;
    const navigate = this.updateNavigation;

    return (
      <Provider store={store}>
        <Router>
          <Switch>
            <Route path="/login" component={() => <Login updateNavigation={navigate}/>}/>
            <Route path="/register" component={() => <Register updateNavigation={navigate}/>}/>
            <Route path="/password/forgot" component={(props) => <RequestPasswordReset updateNavigation={navigate} {...props} />}/>
            <Route path="/password/reset/:token" component={(props) => <PasswordReset {...props} updateNavigation={navigate}/>}/>

            <Route path="/verify/:id" component={(props) => <VerifyUser {...props} />}/>
            <Route path="/verify" component={(props) => <RequestEmailVerification {...props} updateNavigation={navigate}/>}/>

            <Route path="/page/*"
                   render={(props) => (
                     <Fragment>
                       <HeaderNonAuth updateNavigation={navigate}/>
                       <Page {...props} page={page} pageContent={pageContent}/>
                     </Fragment>
                   )}
            />
            {/* Shopify */}
            <Route path="/shopify/auth" component={(props) => <ShopifyAuth {...props} updateNavigation={navigate}/>}/>

            <Route path="/"
                   page={page}
                   component={(props) => <Content updateNavigation={navigate} {...props}/>}
                   pageContent={pageContent}
            />
          </Switch>
          <Footer/>
        </Router>
      </Provider>
    );
  }
}

if (document.getElementById('root')) {
  ReactDOM.render(<Main/>, document.getElementById('root'));
}
