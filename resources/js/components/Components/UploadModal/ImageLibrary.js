import React, { Component } from 'react';
import {
  Button,
  Col,
  Divider,
  Icon,
  Modal,
  Row,
  Typography,
  Spin,
  Upload,
  Form,
  Input,
  Pagination
} from 'antd';
import ImageCropper from './ImageCropper';
import axios from 'axios';
import LibraryCard from '../Cards/LibraryCard';

import { displayErrors } from '../../../utils/errorHandler';
import ImageTrace from "./ImageTrace";

const { Title } = Typography;
const { Search } = Input;

/**
 *
 */
class ImageRequirementsTable extends Component {
  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      imageRequirements,
      imageTypes
    } = this.props;

    const {
      storeWidthMin,
      storeWidthMax,
      storeHeightMin,
      storeHeightMax,
      storeSizeMinReadable,
      storeSizeMaxReadable,
      storeSizeMin
    } = imageRequirements;

    const displayReadableSize = () => {
      if (storeSizeMinReadable && storeSizeMaxReadable) {
        if (storeSizeMinReadable === storeSizeMaxReadable) {
          if (storeSizeMin === null) {
            return <div>File Size: Up to 4 GB</div>;
          }
          return <div>File Size: {storeSizeMinReadable}</div>;
        } else {
          return <div>File Size: {storeSizeMinReadable} - {storeSizeMaxReadable}</div>;
        }
      } else if (storeSizeMaxReadable) {
        return <div>Max File Size: {storeSizeMaxReadable}</div>;
      } else if (storeSizeMinReadable) {
        return <div>Min File Size: {storeSizeMinReadable}</div>;
      }
    };

    const displayDimensions = () => {
      if (storeWidthMin === storeWidthMax &&
        storeHeightMin === storeHeightMax) {
        if (this.props.isHide) {
          return <div>Dimensions: {storeWidthMin} x {storeHeightMin} or {storeHeightMin} x {storeWidthMin}</div>;
        } else {
          return <div>Dimensions: {storeWidthMin} x {storeHeightMin}</div>;
        }
      }

      return <div>
        <div>Min Dimensions: {storeWidthMin} x {storeHeightMin}</div>
        <div>Max Dimensions: {storeWidthMax} x {storeHeightMax}</div>
      </div>
    };

    return (
      <div>
        <Title level={4}>Image Requirements</Title>
        {imageTypes && Array.isArray(imageTypes) && <div>File Formats: {imageTypes.map(imageType => {
          return imageType && imageType.fileExtension;
        }).join(', ')}</div>}
        {displayDimensions()}
        {displayReadableSize()}
      </div>
    )
  }
}

/**
 *
 */
export default class ImageLibrary extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      file: {},
      selectedImage: {},
      uploading: false,
      search: '',
      searchedImages: [],
      fixImageDimensions: null,
      traceImageDetails: null
    };
  };

  /**
   *
   * @param {{}} image
   */
  onImageSelection = image => {
    const { onSelectedImage } = this.props;
    const { selectedImage } = this.state;
    //Deselect image
    if (selectedImage && selectedImage.id === image.id) {
      this.setState({ selectedImage: {} });
    }
    this.setState({ selectedImage: image }, onSelectedImage(image));
  };

  /**
   *
   * @returns {JSX.Element|*}
   */
  buildLibrary = () => {
    const {
      isLoadingImages,
      onDeleteImage,
      uploadedImages
    } = this.props;

    const {
      selectedImage,
      searchedImages
    } = this.state;

    let images = uploadedImages;

    if (searchedImages.length) {
      images = searchedImages;
    }

    if (!images.length) {
      return <Col xs={24} style={{ marginTop: 15 }}>No images found. Upload an image that meets the requirements</Col>;
    }

    return images.map(image => {
      return (
        <Col xs={24} sm={24} md={12} lg={12} xl={6} xxl={4} style={{ marginTop: 15 }} key={image.id}>
          <LibraryCard
            image={image}
            selectedImage={selectedImage || {}}
            onImageSelection={this.onImageSelection}
            onDeleteImage={(imageId) => onDeleteImage(imageId)}
          />
        </Col>
      );
    });
  };

  /**
   * call parent imageFetch()
   * @param {*} page from antd
   **/
  handlePageChange = (page) => {
    this.props.getImages(page);
  };

  /**
   *
   * @param {string} query
   */
  onSearchImages = query => {
    const { getImages } = this.props;
    getImages(1, query);
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      file,
      uploading,
      selectedImage,
      fixImageDimensions,
      traceImageDetails
    } = this.state;

    const {
      imageRequirements,
      imageTypes,
      visible,
      handleCancel,
      onSelectedImage,
      onUploadImage,
      currentPage,
      total,
      pageSize,
      isLoadingImages,
      closeUploaderModel,
      isHide,
      selectedCreateType
    } = this.props;
    /**
     *
     * props for the image uploader
     */
    const props = {
      onChange: file => {
        if (file && file.file) {
          // create a file reader to check the file
          const reader = new FileReader();
          const img = document.createElement('img');
          reader.addEventListener("load", () => {
            img.onload = (e) => {
              // img.src = reader.result;
              img.name = file.file.name;
              // check image dimensions.
              const normalCorrect = img.width >= imageRequirements.storeWidthMin &&
                img.width <= imageRequirements.storeWidthMax &&
                img.height >= imageRequirements.storeHeightMin &&
                img.height <= imageRequirements.storeHeightMax;

              const rotatedCorrect = img.height >= imageRequirements.storeWidthMin &&
                img.height <= imageRequirements.storeWidthMax &&
                img.width >= imageRequirements.storeHeightMin &&
                img.width <= imageRequirements.storeHeightMax;

              if (isHide ? normalCorrect || rotatedCorrect : normalCorrect) {
                // image is good

                //Metal Sign Templates
                if(selectedCreateType && selectedCreateType.createType && selectedCreateType.createType.name === 'Metal Sign'){
                  this.setState({
                    traceImageDetails: {
                      name: file.file.name,
                      src: reader.result,
                      width: img.width,
                      height: img.height
                    }
                  });
                }

                //Normal Templates
                else{
                  onUploadImage(file)
                  this.setState({
                    uploading: true,
                    previewImage: this.state.file.url || this.state.file.preview,
                    previewVisible: true,
                  });
                }

                // cleanup
                img.remove();
              }
              else {

                //Metal Sign Templates
                if(selectedCreateType && selectedCreateType.createType && selectedCreateType.createType.name === 'Metal Sign'){
                  this.setState({
                    traceImageDetails: {
                      name: file.file.name,
                      src: reader.result,
                      width: img.width,
                      height: img.height
                    }
                  });
                }
                else{
                  // image is not correct dimensions
                  this.setState({
                    fixImageDimensions: {
                      name: file.file.name,
                      src: reader.result,
                      width: img.width,
                      height: img.height
                    }
                  });
                }
              }
            };
            img.src = reader.result;
          }, false);

          if (file) {
            // we have a file
            reader.readAsDataURL(file.file);
          }
        }
        return false;
      },
      beforeUpload: file => {
        this.setState({ file });
        return false;
      },
      showUploadList: false,
      multiple: false,
      file,
    };

    return (
      <Modal
        visible={visible}
        title={fixImageDimensions ? "Crop and Resize" : traceImageDetails ? "Continuity Tester" : selectedCreateType && selectedCreateType.createType && selectedCreateType.createType.name === 'Metal Sign' ? "Image Picker" : "Image Library"}
        width="80%"
        onOk={() => onSelectedImage(selectedImage)}
        onCancel={handleCancel}
        footer={[
          <Button key="back" onClick={() => {
            this.setState({
              fixImageDimensions: null,
              traceImageDetails: null
            })
            handleCancel();
          }}>
            Cancel
          </Button>,
          <Button
            key="submit"
            type="primary"
            style={{ display: "none" }}
            onClick={() => onSelectedImage(selectedImage)}
          >
            Choose Image
          </Button>,
        ]}
      >
        { fixImageDimensions && selectedCreateType &&  selectedCreateType.createType && selectedCreateType.createType.name !== 'Metal Sign' &&
          <>
          <ImageCropper
            imageRequirements={imageRequirements}
            img={fixImageDimensions}
            imageTypes={imageTypes}
            isHide={isHide}
            onComplete={(file) => {
              if (file) {
                  onUploadImage({ file: file })
                  this.setState({
                    uploading: true,
                    previewImage: fixImageDimensions.src,
                    previewVisible: true,
                    fixImageDimensions: null,
                    traceImageDetails: null,
                    file: { file: file }
                  });
              }
            }}
          />
        </>
        }

        { traceImageDetails && selectedCreateType && selectedCreateType.createType && selectedCreateType.createType.name === 'Metal Sign' &&
          <>
          <ImageTrace
            img={traceImageDetails}
            imageTypes={imageTypes}
            onComplete={(file) => {
              if (file) {
                onUploadImage({file: file})
                this.setState({
                  uploading: true,
                  previewImage: traceImageDetails.src,
                  previewVisible: true,
                  fixImageDimensions: null,
                  traceImageDetails: null,
                  file: {file: file}
                });
              }
            }}
          />
        </>
        }

        { !fixImageDimensions && !traceImageDetails &&
          <>
          <Row>
            <Col
              span={24}
              style={{
                display: 'flex',
                justifyContent: 'flex-start',
                alignItems: 'flex-end'
              }}
            >
              <ImageRequirementsTable imageRequirements={imageRequirements} imageTypes={imageTypes}
                                      isHide={this.props.isHide}/>
              <span style={{marginLeft: '16px'}}>
                    <Upload
                      {...props}
                      disabled={closeUploaderModel}
                    >
                      <Button
                        loading={closeUploaderModel}
                        type="primary"
                      >
                        <Icon type={!closeUploaderModel ? "upload" : "primary"}/>
                        <span
                          style={closeUploaderModel ? {textIndent: '15px'} : {}}>
                          {!closeUploaderModel ? 'Upload File' : 'Uploading'}
                        </span>
                      </Button>
                    </Upload>
                  </span>
            </Col>
          </Row>

            { selectedCreateType && selectedCreateType.createType && selectedCreateType.createType.name !== 'Metal Sign' &&
            <>
              <Divider/>
              <Title level={4}>Choose Image</Title>
              <Search placeholder="Search for images"
                      onSearch={value => this.onSearchImages(value)}
                      onChange={(event) => this.setState({query: event.target.value})}
                      style={{marginBottom: '16px'}}
                      enterButton/>
              <Row gutter={16}>
                <Spin spinning={isLoadingImages}>
                  {this.buildLibrary()}
                </Spin>
              </Row>
              <Row style={{textAlign: 'center'}}>
                <Pagination
                  key="pagination"
                  onChange={this.handlePageChange}
                  total={total}
                  current={currentPage}
                  pageSize={pageSize}
                />
              </Row>
            </>
            }
        </>
        }

      </Modal>
    )
  }
}
