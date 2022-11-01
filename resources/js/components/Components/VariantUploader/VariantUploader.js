import React, {Component} from 'react';
import {Upload, Button, Icon, Row, Col, Typography, Modal, Divider} from 'antd/lib/index';
import LibraryCard from "../Cards/LibraryCard";
import VariantUploadField from "./VariantUploadField";

const { Title } = Typography;

const fileList = [
  {
    uid: '-1',
    name: 'xxx.png',
    status: 'done',
    url: '//cdn.shopify.com/s/files/1/1151/0394/t/8/assets/feature-1-image-1.jpg?4',
    thumbUrl: '//cdn.shopify.com/s/files/1/1151/0394/t/8/assets/feature-1-image-1.jpg?4',
  },
];
const library = [1, 2, 3, 4, 5, 6];
const props2 = {
  action: 'https://www.mocky.io/v2/5cc8019d300000980a055e76',
  listType: 'picture',
  defaultFileList: [...fileList],
  className: 'upload-list-inline',
};

export default class VariantUploader extends Component {
  constructor(props) {
    super(props);
    this.state = {
      loading: false,
      visible: false,
      library: library,
      previewVisible: false,
      variantCount: 0,
      variantList: []
    };
  }
  showModal = () => {
    this.setState({
      visible: true,
    });
  };

  addVariantField = () => {
    let varCount = this.state.variantCount+1
    this.setState({
      variantCount: varCount,
      variantList: [...this.state.variantList, varCount]
    });
  };
  handleOk = () => {
    this.setState({ loading: true });
    setTimeout(() => {
      this.setState({ loading: false, visible: false });
    }, 3000);
  };

  handleCancel = () => {
    this.setState({ visible: false });
  };

  render() {
    const { visible, loading } = this.state;
    return (
        <div>
          <Row type="flex" justify="space-between">
            <Col>
              <Title level={2}>{this.props.label}</Title>
            </Col>
          </Row>
          <VariantUploadField
            showModal={this.showModal}
          />
          {this.state.variantList.map((item)=>{
            return (
                <VariantUploadField
                    showModal={this.showModal}
                    key={item}
                />
            )
          })}
          <Button type="link" onClick={this.addVariantField}>
            <Icon type="plus-square" theme="twoTone" />
            Add Another Style
          </Button>
          <Divider />
          <Modal
              visible={visible}
              title="Title"
              width="80%"
              onOk={this.handleOk}
              onCancel={this.handleCancel}
              footer={[
                <Button key="back" onClick={this.handleCancel}>
                  Cancel
                </Button>,
                <Button key="submit" type="primary" loading={loading} onClick={this.handleOk}>
                  Add Image
                </Button>,
              ]}
          >
            <Upload {...props2}>
              <Button>
                <Icon type="upload" /> Upload
              </Button>
            </Upload>
            <Divider />
            <Title level={4}>Choose Image</Title>
            <Row gutter={16}>
              {this.state.library.map((item) =>{
                <Col xs={24} md={4} style={{marginTop: 15}} key={item}>
                  <LibraryCard />
                </Col>
              })}
            </Row>
          </Modal>
        </div>
    );
  }
}
