import React, { Component } from 'react';
import { Form, Input, Button, Typography, Icon, Upload } from 'antd/lib/index';
import { Col, Row, message } from "antd";
import { DeleteOutlined } from '@ant-design/icons';
import AccountSettingsDeleteModal from "../Modals/AccountSettingsDeleteModal";

const { Title } = Typography;

class PackingSlipDetailsForm extends Component {
  state = {
    fileList: [],
    uploading: false,
  };
  /**
   *
   * @param e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        let formData = new FormData();
        formData.append('email', values.email);
        formData.append('message', values.message);
        if (values.upload !== undefined) {
          formData.append('packingSlipLogo', this.state.fileList[0].originFileObj);
        } else {
          formData.append('packingSlipLogo', '');
        }
        this.setState({ fileList: [] });
        this.props.handlePackingSlip(formData);
      }
    });
  };
  /**
   *
   * @param e
   */
  onNormFile = e => {
    if (Array.isArray(e)) {
      return e;
    }
    return e && e.fileList;
  };

  /**
   *
   * @param file
   */
  beforeUpload(file) {
    const isJPG = file.type === 'image/jpeg';
    if (!isJPG) {
      message.error('You can only upload JPG file!');
    }
    return false;
  }

  /**
   *
   * @param info
   */
  handleChange = info => {
    let fileList = [...info.fileList];
    fileList = fileList.slice(-1);
    this.setState({ fileList });
  };
  /**
   *
   * @param packingSlipLogo
   */
  handleDeleteModal = packingSlipLogo => {
    this.props.handlePackingSlipDeleteModal(packingSlipLogo.id);
  };

  render() {
    const {
      packingSlip,
      packingSlipLogo,
    } = this.props;
    const { getFieldDecorator } = this.props.form;

    return (
      <Form onSubmit={this.handleSubmit} layout={'vertical'} encType="multipart/form-data">
        <Row>
          <Col span={24}>
            <Form.Item label={'Email'}>
              {getFieldDecorator('email', {
                rules: [{
                  required: false,
                  message: 'Please input your Email Address'
                }],
                initialValue: packingSlip.email
              })(
                <Input
                  type="email"
                  placeholder="Email"
                  name="email"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>
        <Row>
          <Col span={24}>
            <Form.Item label={'Special Message/Discount Code'}>
              {getFieldDecorator('message', {
                rules: [{
                  required: false,
                  message: 'Please input your Message'
                }],
                initialValue: packingSlip.message
              })(
                <Input
                  type="message"
                  placeholder="Message"
                  name="message"
                />,
              )}
            </Form.Item>
          </Col>
        </Row>
        <Row>
          <Col span={24}>
            <div style={{ width: '150px', height: '150px', border: '1px solid #444FE5' }}>
              {
                packingSlipLogo &&
                <img
                  src={packingSlipLogo.fileUrl}
                  alt={packingSlipLogo.fileName}
                  style={{ width: '148px', height: '148px' }}
                />
              }
            </div>
            <Form.Item label="Logo" extra="Image Size 500px By 500px">
              {getFieldDecorator('upload', {
                valuePropName: 'fileList',
                getValueFromEvent: this.onNormFile,
                name: "upload",
                label: "Upload",
              })(
                <Upload
                  name="logo"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleChange}
                  beforeUpload={this.beforeUpload}
                >
                  <Button>
                    <Icon type="upload"/> Select File
                  </Button>
                  {
                    this.state.fileList.length ? <div>{this.state.fileList[0].originFileObj.name}</div> : ''
                  }
                </Upload>,
              )}
              <Button
                style={{
                  margin: '0 40px',
                  color: 'red'
                }}
                onClick={e => this.handleDeleteModal(packingSlipLogo)}
              >
                <DeleteOutlined/>
              </Button>
            </Form.Item>
          </Col>
        </Row>
        <Form.Item>
          <Button type="primary" htmlType="submit">
            Save Packing Slip
          </Button>
        </Form.Item>
      </Form>
    );
  }
}

export default Form.create({ name: 'packing_slip' })(PackingSlipDetailsForm);
