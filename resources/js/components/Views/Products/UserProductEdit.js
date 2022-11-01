import React, { Component } from "react";
import {Col, message, Row, Spin} from "antd";
import Title from "antd/es/typography/Title";
import ProductEditForm from '../../Components/Forms/ProductEditForm';
import axios from "axios";
import {displayErrors} from "../../../utils/errorHandler";
import {ContentState, convertFromHTML, convertToRaw, EditorState} from "draft-js";
import draftToHtml from "draftjs-to-html";

/**
 *
 */
export default class UserProductEdit extends Component {
  constructor(props) {
    super(props);

    this.state = {
      productId: props.match.params.id,
      title: '',
      description: '',
      artFiles: [],
      product: [],
      isLoadingProduct: true
    }
  }

  componentDidMount() {
    this.fetchProduct();
  }

  onEditorStateChange = value => {
    this.setState({
      description: value
    });
  };

  onInputChange = event => {
    const { target } = event;

    this.setState({
      title: target.value
    });
  };

  onSelectedImage = (data, artFileIdToReplace) => {
    let artFiles = this.state.artFiles;

    Object.keys(artFiles).forEach(artFile => {
      if (artFiles[artFile].id === artFileIdToReplace) {

        artFiles[artFile]['accountImageId'] = data.id;
        delete data.id;

        artFiles[artFile] = {...artFiles[artFile], ...data}
      }
    });

    this.setState({
      artFiles: artFiles,
    });
  };

  handleSubmit = e => {
    e.preventDefault();
      axios
        .put("/products/"+this.state.product.id, {
          name: this.state.title,
          description: draftToHtml(convertToRaw(this.state.description.getCurrentContent())),
          artFiles: this.state.artFiles
        })
        .then(res => {
          if (res.status === 200) {
            message.success("Product Updated");
            this.props.history.push("/my-products/"+this.state.product.id)
          }
        })
        .catch(error => {
          displayErrors(error);
        })
        .finally();
  };

  fetchProduct = () => {
    axios
      .get(`/products/${this.state.productId}`)
      .then(res => {
        const { data } = res.data;
        if (data) {
          this.setState({
            product: data,
            title: data.name,
            description: data.description ? EditorState.createWithContent(ContentState.createFromBlockArray(convertFromHTML(data.description))) : "",
            artFiles: data.artFiles || [],
            isLoadingProduct: false
          });
        }
      })
      .catch((error, res) => {
        displayErrors(error);
      })
      .finally(() => {
        this.setState({ isLoadingProduct: false });
      });
  };

  render() {
    const {
      isLoadingProduct,
      title,
      description,
      artFiles,
      product,
    } = this.state;

    return (
      <div style={{background: 'white'}}>
        <Col span={24}><Title>Product Edit</Title></Col>
        <Col span={24}>
          <Spin spinning={isLoadingProduct}>
            <ProductEditForm
              product={product}
              title={title}
              description={description}
              artFiles={artFiles}
              onEditorStateChange={this.onEditorStateChange}
              onSubmit={this.handleSubmit}
              onInputChange={this.onInputChange}
              onSelectedImage={this.onSelectedImage}
            />
          </Spin>
        </Col>
      </div>
    );
  }
}
