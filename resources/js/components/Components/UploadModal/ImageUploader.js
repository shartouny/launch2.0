import React, { Component } from 'react';
import {
  Upload,
  Button,
  Icon,
  Row,
  Col,
  Typography,
  Modal,
} from 'antd/lib/index';

import ImageLibrary from './ImageLibrary';

const { Title } = Typography;

/**
 *
 */
export default class ImageUploader extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      isLoadingImages: this.props.isLoadingImages,
      isLoadingDelete: false,
      fileList: [],
      visible: false,
      file: {},
      confirmModal: false,
      selectedImage: {},
    };
  };
  /**
   *
   * @param {{}} selectedImage
   */
  onSelectedImage = selectedImage => {
    this.props.onSelectedImages(selectedImage);
    this.setState({visible: false});
  };
  /**
   *
   */
  showModal = () => {
    this.setState({visible: true});
    this.props.getImages()
  };
  /**
   *
   */
  handleCancel = () => {
    this.setState({visible: false});
  };
  /**
   *
   * @param {number} imageId
   */
  onDeleteConfirmation = (imageId) => {
    this.setState({confirmModal: true, imageId: imageId});
  };
  /**
   *
   * @returns {*}
   */
  render() {
    const {
      visible,
      confirmModal,
      imageId,
      isLoadingDelete
    } = this.state;

    const {
      imageRequirements,
      imageTypes,
      onUploadImage,
      uploadedImages,
      onDeleteImageServer,
      currentPage,
      total,
      pageSize,
      getImages,
      isLoadingImages,
      selectedStage,
      selectedCreateType
    } = this.props;

    const showTitle = () => {
      if (selectedStage.artwork) {
        if (selectedStage.location.fullName == 'Front Vertical') {
          return <span style={{fontSize: 15}}>(Vertical)</span>
        } else if(selectedStage.location.fullName == 'Front Horizontal') {
          return <span style={{fontSize: 15}}>(Horizontal)</span>
        }
      }
    }
    return (
      <>
        <Modal
          title="Delete image"
          visible={confirmModal}
          centered
          onOk={() => {}}
          onCancel={() => this.setState({confirmModal: false})}
          footer={[
            <Button
              key={'cancel'}
              onClick={() => this.setState({confirmModal: false})}
            >
              Cancel
            </Button>,
            <Button
              key={'delete'}
              type="danger"
              loading={isLoadingDelete}
              onClick={() => {
                this.setState({isLoadingDelete: true});
                  onDeleteImageServer(imageId)
                    .then(() => this.setState({confirmModal: false}))
                    .finally(() => this.setState({isLoadingDelete: false, visible: true}))
                }
              }
            >
              Delete
            </Button>,
          ]}
        >
        </Modal>
        <Row type="flex" justify="space-between">
          <Col>
            <Title level={2} style={showTitle() || this.props.isHide ? {marginTop: 25} : null}>
              Add Artwork {showTitle()}
            </Title>
            <Button onClick={this.showModal}>
              Select Image
            </Button>
          </Col>
        </Row>

        <Row>
          <Col>
              <Upload
                listType="picture-card"
                fileList={this.state.fileList}
                openFileDialogOnClick={true}
              >
              </Upload>
          </Col>
        </Row>

        <ImageLibrary
          visible={visible}
          onUploadImage={(file) => {
            onUploadImage(file, (status) => {
              if(status === 'done'){
                this.handleCancel()
              }
            })
          }}
          onDeleteImage={this.onDeleteConfirmation}
          imageRequirements={imageRequirements}
          imageTypes={imageTypes}
          selectedCreateType={selectedCreateType}
          handleCancel={this.handleCancel}
          uploadedImages={uploadedImages}
          uploaded={this.props.uploaded}
          currentPage={currentPage}
          total={total}
          pageSize={pageSize}
          isLoadingImages={isLoadingImages}
          onSelectedImage={this.onSelectedImage}
          getImages={getImages}
          isHide={this.props.isHide}
          closeUploaderModel={this.props.closeUploaderModel}
        />
      </>
    );
  }
}
