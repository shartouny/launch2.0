import {Col, Icon, Input, Row} from "antd";
import React, {Component} from "react";

export default class VariantUploadField extends Component {
  constructor(props) {
    super(props);
    this.state = {
      loading: false,
    };
  }
  showModal = () => {
    this.props.showModal();
  }
  render() {
    return(
        <div>
          <Row type="flex" justify="space-around" align="middle">
            <Col xs={24} md={4}>
              <div className="upload-button" onClick={this.showModal}>
                <div className="upload-button-inner">
                  <Icon type={this.state.loading ? 'loading' : 'plus'}/>
                  <div className="ant-upload-text">Upload</div>
                </div>
              </div>
            </Col>
            <Col xs={24} md={13} offset={1}>
              <Input placeholder="Variant Name"/>
            </Col>
            <Col xs={24} md={4} offset={2}><Icon type="close-circle" theme="twoTone" twoToneColor="#EC3E3E"/></Col>
          </Row>
        </div>
    )
  }
}