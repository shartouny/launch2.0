import React, { Component } from "react";
import { Row, Col } from "antd/lib/index";

import HeaderBrand from "./HeaderBrand";

/**
 *
 */
export default class HeaderNonAuth extends Component {
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
      <>
        <div className="top-nav">
          <div className="page-wrapper">
            <Row>
              <Col xs={19} md={5} xxl={4}>
                <HeaderBrand />
              </Col>
            </Row>
          </div>
        </div>
      </>
    );
  }
}
