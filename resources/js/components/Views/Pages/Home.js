import React, {Component} from 'react';
import axios from "axios";
import {Spin} from "antd";
import ReactHtmlParser, { processNodes, convertNodeToElement, htmlparser2 } from 'react-html-parser';

export default class Page extends Component {
  constructor(props) {
    super(props);
    this.state = {
      current: window.location.pathname,
      page:'isLoading'
    };
  }
  render() {
    if (this.state.page == 'isLoading') return <Spin />;
    return (
      <div>
        {ReactHtmlParser(this.state.page)}
      </div>
    );
  }
}
