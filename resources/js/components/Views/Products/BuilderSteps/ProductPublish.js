import React, { Component } from 'react';
import {
  Col,
  Row,
  Result,
  Button,
} from "antd/lib/index";

import ProductSuccess from '../../../../../images/product-success.svg';

/**
 *
 */
export default class ProductPublish extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  /**
   *
   * @returns {*}
   */
  render() {
    return (
      <div>
        <Row>
          <Col span={24}>
            <Result
              icon={<img src={ProductSuccess}/>}
              status="success"
              title="Your product was successfully created!"
              subTitle="It may take a few minutes for it to appear in your shop."
              extra={[
                <div style={{ marginBottom: 16 }} key={1}>
                  <Button
                    key="restart"
                    onClick={() => this.props.history.go()}>
                    Create another {this.props.selectedProductName}
                  </Button>
                </div>,
                <div style={{ marginBottom: 16 }} key={2}>or</div>,
                <div style={{ marginBottom: 16 }} key={3}>
                  <Button
                    type="primary"
                    key="new"
                    onClick={() => this.props.history.push('/catalog')}
                  >
                    Choose a different product
                  </Button>
                </div>
              ]}
            />
          </Col>
        </Row>
      </div>
    );
  }
}
