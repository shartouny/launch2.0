import React, {Component} from 'react';
import {Spin} from "antd";
import ReactHtmlParser, { processNodes, convertNodeToElement, htmlparser2 } from 'react-html-parser';

export default class Page extends Component {
  constructor(props) {
    super(props);
  }

  render() {
    if (this.props.pageContent === 'isLoading') return <Spin />;
    return (
      <div>
        {ReactHtmlParser(this.props.pageContent)}
      </div>
    );
  }
}
