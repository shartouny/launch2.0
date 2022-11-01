import React, {Component} from 'react';
import {Row, Col, Typography, Input, Button} from 'antd';

const { Title } = Typography;

export default class ApiIntegration extends Component {
  render() {
    return (
        <div>
          <Row>
            <Col xs={24} md={12}>
              <Title>API</Title>
              <div className="my-2">
                <Title level={4}>Key:</Title>
                <Input/>
              </div>
              <div className="my-2">
                <Title level={4}>Secret Key:</Title>
                <Input.Password placeholder="input password" />
              </div>
              <div className="my-2">
                <Title level={4}>Website:</Title>
                <Input/>
              </div>
              <Row type="flex" justify="space-between">
                <Col>
                  <Button type="primary">
                  Save
                </Button>
                </Col>
                <Col>
                  <Button type="danger">
                    Remove
                  </Button>
                </Col>
              </Row>
            </Col>
          </Row>
        </div>
    );
  }
}