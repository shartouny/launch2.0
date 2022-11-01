import React, {Component} from 'react';
import {Form, Input, Button, Typography, Icon, Upload} from 'antd/lib/index';
import {Col, message, Row} from "antd";
import {DeleteOutlined} from "@ant-design/icons";
import frontCardDefault from "../../../../images/card_front.png";
import backCardDefault from "../../../../images/card_back.png";
import outsideStickerDefault from "../../../../images/outside_box_sticker.png";
import backLogoDefault from "../../../../images/canvas_back_logo.png";

const { Title } = Typography;

class PremiumCanvasDetailsForm extends Component {
  state = {
    file: [],
    fileList: [],
    uploading: false,
    premiumCanvasCardFront: [],
    premiumCanvasCardBack: [],
    premiumCanvasBoxSticker: [],
    premiumCanvasBackLogo: [],
    fileListCardFront: [],
    fileCardFront: [],
    fileListCardBack: [],
    fileCardBack: [],
    fileListBoxSticker: [],
    fileBoxSticker: [],
    fileListBackLogo: [],
    fileBackLogo: [],
    premiumCanvasCardFrontImg: '',
    premiumCanvasCardBackImg: '',
    premiumCanvasBoxStickerImg: '',
    premiumCanvasBackLogoImg: ''
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

        if (values.cardFront !== undefined) {
          let valuesCardFront = [...values.cardFront];
          valuesCardFront = valuesCardFront.slice(-1);

          if (this.state.fileCardFront.length) {
            if (this.state.fileCardFront[0].uid === valuesCardFront[0].uid ) {
              formData.append('cardFront', valuesCardFront[0].originFileObj);
            } else {
              formData.append('cardFront', '');
            }
          } else {
            formData.append('cardFront', '');
          }
        } else {
          formData.append('cardFront', '');
        }

        if (values.cardBack !== undefined) {
          let valuesCardBack = [...values.cardBack];
          valuesCardBack = valuesCardBack.slice(-1);

          if (this.state.fileCardBack.length) {
            if (this.state.fileCardBack[0].uid === valuesCardBack[0].uid ) {
              formData.append('cardBack', valuesCardBack[0].originFileObj);
            } else {
              formData.append('cardBack', '');
            }
          } else {
            formData.append('cardBack', '');
          }
        } else {
          formData.append('cardBack', '');
        }

        if (values.boxSticker !== undefined) {
          let valuesBoxSticker = [...values.boxSticker];
          valuesBoxSticker = valuesBoxSticker.slice(-1);

          if (this.state.fileBoxSticker.length) {
            if (this.state.fileBoxSticker[0].uid === valuesBoxSticker[0].uid ) {
              formData.append('boxSticker', valuesBoxSticker[0].originFileObj);
            } else {
              formData.append('boxSticker', '');
            }
          } else {
            formData.append('boxSticker', '');
          }
        } else {
          formData.append('boxSticker', '');
        }

        if (values.backLogo !== undefined) {
          let valuesBackLogo = [...values.backLogo];
          valuesBackLogo = valuesBackLogo.slice(-1);
          if (this.state.fileBackLogo.length) {
            if (this.state.fileBackLogo[0].uid === valuesBackLogo[0].uid ) {
              formData.append('backLogo', valuesBackLogo[0].originFileObj);
            } else {
              formData.append('backLogo', '');
            }
          } else {
            formData.append('backLogo', '');
          }
        } else {
          formData.append('backLogo', '');
        }

        this.setState({
          file: [],
          fileList: [],
          uploading: false,
          premiumCanvasCardFront: [],
          premiumCanvasCardBack: [],
          premiumCanvasBoxSticker: [],
          premiumCanvasBackLogo: [],
          fileListCardFront: [],
          fileCardFront: [],
          fileListCardBack: [],
          fileCardBack: [],
          fileListBoxSticker: [],
          fileBoxSticker: [],
          fileListBackLogo: [],
          fileBackLogo: []
        });

        this.props.handlePremiumCanvas(formData);
      }
    });
  };
  /**
   *
   * @param {{}} e
   * @returns {*}
   */
  onCardFrontFile = e => {
    if (Array.isArray(e)) {
      return e;
    }

    this.setState({
      premiumCanvasCardFront: e.file
    });

    return e && e.fileList;
  };
  /**
   *
   * @param {{}} e
   * @returns {*}
   */
  onCardBackFile = e => {
    if (Array.isArray(e)) {
      return e;
    }

    this.setState({
      premiumCanvasCardBack: e.file
    });

    return e && e.fileList;
  };
  /**
   *
   * @param {{}} e
   * @returns {*}
   */
  onBoxStickerFile = e => {
    if (Array.isArray(e)) {
      return e;
    }

    this.setState({
      premiumCanvasBoxSticker: e.file
    });

    return e && e.fileList;
  };
  /**
   *
   * @param {{}} e
   * @returns {*}
   */
  onBackLogoFile = e => {
    if (Array.isArray(e)) {
      return e;
    }

    this.setState({
      premiumCanvasBackLogo: e.file
    });

    return e && e.fileList;
  };
  /**
   *
   * @param {{}} file
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
  handleCardFrontChange = info => {
    let fileListCardFront = [...info.fileList];
    let fileCardFront = [info.file];

    fileListCardFront = fileListCardFront.slice(-1);
    this.setState({ fileListCardFront });
    this.setState({ fileCardFront });

    let reader  = new FileReader();
    reader.onload = () => this.setState({ premiumCanvasCardFrontImg : reader.result })

    if (fileCardFront) {
      reader.readAsDataURL(info.file);
    }
  };
  /**
   *
   * @param info
   */
  handleCardBackChange = info => {
    let fileListCardBack = [...info.fileList];
    let fileCardBack = [info.file];

    fileListCardBack = fileListCardBack.slice(-1);
    this.setState({ fileListCardBack });
    this.setState({ fileCardBack });

    let reader  = new FileReader();
    reader.onload = () => this.setState({ premiumCanvasCardBackImg : reader.result })

    if (fileCardBack) {
      reader.readAsDataURL(info.file);
    }

  };
  /**
   *
   * @param info
   */
  handleBoxStickerChange = info => {
    let fileListBoxSticker = [...info.fileList];
    let fileBoxSticker = [info.file];

    fileListBoxSticker = fileListBoxSticker.slice(-1);
    this.setState({ fileListBoxSticker });
    this.setState({ fileBoxSticker });

    let reader  = new FileReader();
    reader.onload = () => this.setState({ premiumCanvasBoxStickerImg : reader.result })

    if (fileBoxSticker) {
      reader.readAsDataURL(info.file);
    }
  };
  /**
   *
   * @param info
   */
  handleBackLogoChange = info => {
    let fileListBackLogo = [...info.fileList];
    let fileBackLogo = [info.file];

    fileListBackLogo = fileListBackLogo.slice(-1);
    this.setState({ fileListBackLogo });
    this.setState({ fileBackLogo });

    let reader  = new FileReader();
    reader.onload = () => this.setState({ premiumCanvasBackLogoImg : reader.result })

    if (fileBackLogo) {
      reader.readAsDataURL(info.file);
    }
  };
  /**
   *
   * @param info
   */
  handleDeleteModal= info => {
    this.props.handlePremiumCanvasDeleteModal(info.id);
  } ;

  render() {
    const {
      premiumCanvasCardFront,
      premiumCanvasCardBack,
      premiumCanvasBoxSticker,
      premiumCanvasBackLogo,
    } = this.props;

    const { getFieldDecorator } = this.props.form;

    return (
      <Form onSubmit={this.handleSubmit} layout={'vertical'} encType="multipart/form-data">
        <Row gutter={16}>
          <Col xs={24} sm={24} md={6}>
            <div className="premium-canvas-img-wrapper">
              {
                premiumCanvasCardFront && this.state.premiumCanvasCardFrontImg === '' ?
                <img
                  src={premiumCanvasCardFront.thumbUrl}
                  alt={premiumCanvasCardFront.fileName}
                /> : null
              }
              {
                premiumCanvasCardFront && this.state.premiumCanvasCardFrontImg !== '' ?
                  <img
                    src={this.state.premiumCanvasCardFrontImg}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasCardFront && this.state.premiumCanvasCardFrontImg === '' ?
                  <img
                    src={frontCardDefault}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasCardFront && this.state.premiumCanvasCardFrontImg !== '' ?
                  <img
                    src={this.state.premiumCanvasCardFrontImg}
                    alt=""
                  /> : null
              }
            </div>
            <Form.Item label="Card Front" extra="Image Size 1500px By 2400px">
              {getFieldDecorator('cardFront', {
                valuePropName: 'fileList',
                getValueFromEvent: this.onCardFrontFile,
                name: "cardFront",
                label: "Insert Card Front",
              })(
                <Upload
                  name="cardFront"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleCardFrontChange}
                  beforeUpload={this.beforeUpload}
                >
                  <Button>
                    <Icon type="upload" /> Select File
                  </Button>
                  {
                    this.state.premiumCanvasCardFront && <div>{this.state.premiumCanvasCardFront.name}</div>
                  }
                </Upload>,
              )}
              {premiumCanvasCardFront &&
                <Button
                  style={{
                    margin: '0 40px',
                    color: 'red',
                  }}
                  onClick={e => this.handleDeleteModal(premiumCanvasCardFront)}
                >
                  <DeleteOutlined/>
                </Button>
              }
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={6}>
            <div className="premium-canvas-img-wrapper">
              {
                premiumCanvasCardBack && this.state.premiumCanvasCardBackImg === '' ?
                  <img
                    src={premiumCanvasCardBack.thumbUrl}
                    alt={premiumCanvasCardBack.fileName}
                  /> : null
              }
              {
                premiumCanvasCardBack && this.state.premiumCanvasCardBackImg !== '' &&
                  <img
                    src={this.state.premiumCanvasCardBackImg}
                    alt=""
                  />
              }
              {
                !premiumCanvasCardBack && this.state.premiumCanvasCardBackImg === '' ?
                  <img
                    src={backCardDefault}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasCardBack && this.state.premiumCanvasCardBackImg !== '' ?
                  <img
                    src={this.state.premiumCanvasCardBackImg}
                    alt=""
                  /> : null
              }
            </div>
            <Form.Item label="Card Back" extra="Image Size 1500px By 2400px">
              {getFieldDecorator('cardBack', {
                valuePropName: 'fileList',
                getValueFromEvent: this.onCardBackFile,
                name: "cardBack",
                label: "Insert Card Back",
              })(
                <Upload
                  name="cardBack"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleCardBackChange}
                  beforeUpload={this.beforeUpload}
                >
                  <Button>
                    <Icon type="upload" /> Select File
                  </Button>
                  {
                    this.state.premiumCanvasCardBack && <div>{this.state.premiumCanvasCardBack.name}</div>
                  }
                </Upload>,
              )}
              {premiumCanvasCardBack &&
                <Button
                  style={{
                    margin: '0 40px',
                    color: 'red',
                  }}
                  onClick={e => this.handleDeleteModal(premiumCanvasCardBack)}
                >
                  <DeleteOutlined/>
                </Button>
              }
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={6}>
            <div className="premium-canvas-img-wrapper">
              {
                premiumCanvasBoxSticker && this.state.premiumCanvasBoxStickerImg === '' ?
                  <img
                    src={premiumCanvasBoxSticker.thumbUrl}
                    alt={premiumCanvasBoxSticker.fileName}
                  /> : null
              }
              {
                premiumCanvasBoxSticker && this.state.premiumCanvasBoxStickerImg !== '' ?
                  <img
                    src={this.state.premiumCanvasBoxStickerImg}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasBoxSticker && this.state.premiumCanvasBoxStickerImg === '' ?
                  <img
                    src={outsideStickerDefault}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasBoxSticker && this.state.premiumCanvasBoxStickerImg !== '' ?
                  <img
                    src={this.state.premiumCanvasBoxStickerImg}
                    alt=""
                  /> : null
              }
            </div>
            <Form.Item label="Outside Box Sticker" extra="Image Size 1275px By 1875px">
              {getFieldDecorator('boxSticker', {
                valuePropName: 'fileList',
                getValueFromEvent: this.onBoxStickerFile,
                name: "boxSticker",
                label: "Outside Box Sticker",
              })(
                <Upload
                  name="outsideBoxSticker"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleBoxStickerChange}
                  beforeUpload={this.beforeUpload}
                >
                  <Button>
                    <Icon type="upload" /> Select File
                  </Button>
                  {
                    this.state.premiumCanvasBoxSticker && <div>{this.state.premiumCanvasBoxSticker.name}</div>
                  }
                </Upload>,
              )}
              {premiumCanvasBoxSticker &&
                <Button
                  style={{
                    margin: '0 40px',
                    color: 'red',
                  }}
                  onClick={e => this.handleDeleteModal(premiumCanvasBoxSticker)}
                >
                  <DeleteOutlined/>
                </Button>
              }
            </Form.Item>
          </Col>
          <Col xs={24} sm={24} md={6}>
            <div className="premium-canvas-img-wrapper">
              {
                premiumCanvasBackLogo && this.state.premiumCanvasBackLogoImg === '' ?
                  <img
                    src={premiumCanvasBackLogo.thumbUrl}
                    alt={premiumCanvasBackLogo.fileName}
                  /> : null
              }
              {
                premiumCanvasBackLogo && this.state.premiumCanvasBackLogoImg !== '' ?
                  <img
                    src={this.state.premiumCanvasBackLogoImg}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasBackLogo && this.state.premiumCanvasBackLogoImg === '' ?
                  <img
                    src={backLogoDefault}
                    alt=""
                  /> : null
              }
              {
                !premiumCanvasBackLogo && this.state.premiumCanvasBackLogoImg !== '' ?
                  <img
                    src={this.state.premiumCanvasBackLogoImg}
                    alt=""
                  /> : null
              }
            </div>
            <Form.Item label="Canvas Back Logo" extra="Image Size 360px By 127px">
              {getFieldDecorator('backLogo', {
                valuePropName: 'fileList',
                getValueFromEvent: this.onBackLogoFile,
                name: "backLogo",
                label: "Canvas Back Logo",
              })(
                <Upload
                  name="canvasBackLogo"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleBackLogoChange}
                  beforeUpload={this.beforeUpload}
                >
                  <Button>
                    <Icon type="upload" /> Select File
                  </Button>
                  {
                    this.state.premiumCanvasBackLogo && <div>{this.state.premiumCanvasBackLogo.name}</div>
                  }
                </Upload>,
              )}
              { premiumCanvasBackLogo &&
                <Button
                  style={{
                    margin: '0 40px',
                    color: 'red',
                  }}
                  onClick={e => this.handleDeleteModal(premiumCanvasBackLogo)}
                >
                  <DeleteOutlined />
                </Button>
              }
            </Form.Item>
          </Col>
        </Row>
        <Form.Item>
          <Button type="primary" htmlType="submit">
            Save Premium Canvas
          </Button>
        </Form.Item>
      </Form>
    );
  }
}
export default Form.create({ name: 'premium_canvas' })(PremiumCanvasDetailsForm);
