import React, { Component } from 'react';
import { Editor } from 'react-draft-wysiwyg';
import {
  Form,
  Input,
  Col,
  Row,
  Button, Avatar, Tag, Spin, message,
} from 'antd';

import 'react-draft-wysiwyg/dist/react-draft-wysiwyg.css';
import {Link} from "react-router-dom";
import axios from "axios";
import {displayErrors} from "../../../utils/errorHandler";
import ImageLibrary from "../UploadModal/ImageLibrary";
import DesignGuide from "../../../../images/design-guide.svg";

const ART_FILE_STATUS_FINISHED = 6;

/**
 *
 */
class ProductEditForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      isImageLibraryVisible: false,
      uploadedImages: [],
      productId: null,
      artFileId: null,
      confirmModal: false,
      imageRequirements: {
        storeWidthMin: null,
        storeWidthMax: null,
        storeHeightMin: null,
        storeHeightMax: null,
        storeSizeMinReadable: null,
        storeSizeMaxReadable: null
      },
      isLoadingImages: false,
      pageSize: 1,
      currentPage: 1,
      imageId: null,
      isSaving: false,
    };
  }

  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.setState({
          isSaving: true
        })
        this.props.onSubmit(e);
      }
    });
  };

  onSelectedImage = image => {
    this.setState({isImageLibraryVisible: false});
    this.props.onSelectedImage(image, this.state.artFileId);
  };

  openImageUpload = (product, artFile) => {
    this.setState(
      {
        productId: product.id,
        artFileId: artFile.id,
        imageRequirements: {
          storeWidthMin: artFile.width,
          storeWidthMax: artFile.width,
          storeHeightMin: artFile.height,
          storeHeightMax: artFile.height,
          storeSizeMinReadable: null,
          storeSizeMaxReadable: null
        },
        imageTypes: [artFile.imageTypeId],
        isImageLibraryVisible: true,
        productArtFile: artFile
      },
      this.getImages
    );
  };

  onUploadImage = ({ file }) => {
    const { uploadedImages } = this.state;

    return this.onUploadImageToServer(file)
      .then(file => {
        if (file) {
          this.getImages(1, "");
        }
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          uploading: false,
          file: {}
        });
      });
  };

  onUploadImageToServer = file => {
    let formData = new FormData();

    const acceptedTypes = ["image/png", "image/jpg", "image/jpeg"];

    if (!acceptedTypes.includes(file.type)) {
      message.error("File type not supported");
      throw "File type not supported";
    }

    formData.append("image", file);

    return axios
      .post("/account-images", formData, {
        params: {
          blankStageId: this.state.productArtFile.blankStageId,
          createTypeId: 1
        },
        headers: {
          "Content-Type": "multipart/form-data"
        }
      })
      .then(res => {
        if (res.status === 201) {
          const { data } = res;
          if (data) {
            this.setState({ uploaded: data.data });
            return data.data;
          }
        }
        return false;
      })
      .catch(error => {
        displayErrors(error);
        return false;
      });
  };

  getImages = (page = null, query = null) => {
    if (page) {
      this.setState({ page: page });
    } else {
      page = this.state.page;
    }

    if (query) {
      this.setState({ query: query });
    } else {
      query = this.state.query;
    }

    this.setState({ isLoadingImages: true });
    const productArtFile = this.state.productArtFile;
    return axios
      .get(`/account-images`, {
        params: {
          page: page,
          file_name: query,
          blankStageId: productArtFile.blankStageId,
          createTypeId: 1
        }
      })
      .then(res => {
        const { data, meta } = res.data;
        if (data) {
          this.setState({
            uploadedImages: data,
            currentPage: meta.current_page,
            total: meta.total,
            pageSize: meta.per_page
          });
        }
      })
      .catch(error => {
        this.setState({ isImageLibraryVisible: false });
        if (error.response.status === 422) {
          displayErrors("This product is not currently editable");
          return;
        }
        displayErrors(error);
      })
      .finally(() => this.setState({ isLoadingImages: false }));
  };

  render() {

    const {
      product,
      title,
      description,
      artFiles,
      onEditorStateChange,
      onInputChange,
    } = this.props;

    const {
      isImageLibraryVisible,
      uploadedImages,
      imageRequirements,
      imageTypes,
      isLoadingImages,
      currentPage,
      total,
      pageSize,
      isSaving,
      uploaded
    } = this.state;

    const { getFieldDecorator } = this.props.form;

    return (
      <>
        <ImageLibrary
          visible={isImageLibraryVisible}
          onUploadImage={this.onUploadImage}
          onSelectedImage={this.onSelectedImage}
          uploadedImages={uploadedImages}
          imageRequirements={imageRequirements}
          imageTypes={imageTypes}
          onDeleteImage={imageId => this.setState({ confirmModal: true, imageId: imageId })}
          handleCancel={() => this.setState({ isImageLibraryVisible: false })}
          isLoadingImages={isLoadingImages}
          getImages={this.getImages}
          currentPage={currentPage}
          total={total}
          pageSize={pageSize}
          uploaded={uploaded}
        />

        <Form onSubmit={this.handleSubmit} layout={'vertical'} style={{background: 'white'}}>
        <Row>
          <Col xs={24} md={24} lg={24} style={{padding: '20px 35px'}}>
            <Form.Item label="Title">
              {getFieldDecorator('title', {
                rules: [{
                    required: true,
                    message: 'Please input product title!'
                }],
                initialValue: title
              })(
                <Input
                  type="text"
                  placeholder="Title"
                  onChange={e => onInputChange(e)}
                />,
              )}
            </Form.Item>
            <Form.Item label="Description">
              <div className="box-border">
                <Editor
                  editorState={description}
                  wrapperClassName="demo-wrapper"
                  editorClassName="demo-editor"
                  onEditorStateChange={onEditorStateChange}
                  editorStyle={{ height: 300 }}
                />
              </div>
            </Form.Item>
            {/* <Form.Item label="Artwork">
              <div>
                {artFiles.map(artFile => {
                   const replacement = artFile || null;
                   const fileUrl = replacement ? replacement.fileUrl : null;
                   const thumbUrl = replacement ? replacement.thumbUrl : null;

                    return (
                      <div key={artFile.id} style={{ display: 'inline-block', textAlign: 'center', margin: '0 15px'}}>
                        {replacement && replacement.status < ART_FILE_STATUS_FINISHED ? (
                          <Spin tip="Processing..." />
                        ) : (
                          <>
                            <a href={fileUrl} target="_blank" download>
                              <Avatar
                                key={artFile.id}
                                style={{
                                  backgroundColor: "#fff",
                                  backgroundImage: `url(${thumbUrl})`,
                                  backgroundPosition: "center",
                                  backgroundSize: "contain",
                                  backgroundRepeat: "no-repeat",
                                  borderRadius: 0,
                                  border: "1px solid #ccc"
                                }}
                                shape="square"
                                size={64}
                                icon=""
                              />
                            </a>
                            <div style={{ position: "relative", top: "-8px" }}>
                              {artFile.blankStageLocation && artFile.blankStageLocation.shortName}
                            </div>
                          </>
                        )}

                        <div style={{display: "flex", flexDirection: "column", marginTop: "4px"}}>
                          <Button size="small" type="dashed" onClick={() => this.openImageUpload(product, artFile)} style={{ position: "relative", top: "-8px", marginBottom: "4px"}}>
                            Change File
                          </Button>
                        </div>
                      </div>
                    );
                })}
              </div>
            </Form.Item>
            <div style={{ fontSize: '14px', paddingLeft: '10px' }}>
              Replacing Artwork will affect all of the product variants.
            </div> */}
          </Col>
        </Row>

        <Row>
          <Col xs={24} md={24} lg={24} style={{padding: '0 35px', textAlign: 'right'}}>
            <Form.Item>
              <Link to={`/my-products/${product.id}`}>
                <Button style={{
                  marginRight: '10px'
                }}>
                  Cancel
                </Button>
              </Link>
              <Button type="primary" htmlType="submit">
                <Spin spinning={isSaving}>Save Changes</Spin>
              </Button>
            </Form.Item>
          </Col>
        </Row>
      </Form>
      </>

    );
  }
}

export default Form.create({ name: 'product_edit' })(ProductEditForm);
