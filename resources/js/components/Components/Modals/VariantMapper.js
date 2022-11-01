import React, { Component } from "react";
import axios from "axios";
import { displayErrors } from "../../../utils/errorHandler";
import {
  Avatar,
  Button,
  Col,
  Input,
  Modal,
  Row, Spin,
  Table
} from "antd";

const { Search } = Input;

export default class VariantMapper extends Component {
  constructor(props) {
    super(props);
    this.state = {
      products: [],
      isLoadingTeelaunchProducts: false,
      platformVariant: this.props.platformVariant,
    };
  }

  componentDidMount() {
    //console.log('VariantMapper componentDidMount');
    //this.getPlatformProduct();
  };

  componentDidUpdate(prevProps, prevState, snapShot) {
    if (this.props.platformVariant != prevProps.platformVariant) {
      // console.log('componentDidUpdate', this.props.platformVariant);
      //this.getPlatformProduct();
      this.setState({ platformVariant: this.props.platformVariant });

      this.getTeelaunchProducts();
    }
  }

  onSearchTeelaunchProducts = (query) => {
    this.getTeelaunchProducts({ name: query });
  };

  getTeelaunchProducts = (query = {}) => {
    this.setState({ isLoadingTeelaunchProducts: true, teelaunchProducts: [] });

    axios.get(`/products/platform-products`, { params: { ...query } }).then(res => {
      const { data } = res.data;

      if (data) {
        this.setState({
          teelaunchProducts: data
        });
      }
    }).catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingTeelaunchProducts: false }));
  };

  /**
   *
   * @param {string} category
   * @param {array} printFiles
   * @returns {int}
   */
  getSurcharge = (category, printFiles) => {
    let surcharge = 0;
    const isApparel = (category.toLowerCase() === 'apparel');
    if (isApparel && (printFiles.length) > 1) {
      surcharge = 5;
    }
    return surcharge;
  };


  onExpandRow = (expanded, record) => {
    const { teelaunchProducts } = this.state;
    this.setState({ isLoadingTeelaunchVariants: record.id });
    axios.get(`/products/platform-product/${record.id}`).then(res => {
      const { data } = res.data;

      if (data) {
        const index = teelaunchProducts.findIndex(x => x.id === record.id);

        if (index !== -1) {
          teelaunchProducts[index].variants = data.variants;
          this.setState({ teelaunchProducts: teelaunchProducts });
        }
      }
    }).catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingTeelaunchVariants: false }));
  };

  getProductVariantRows = (product) => {
    const { isMappingTeelaunchVariantId, platformVariant, isLoadingTeelaunchVariants } = this.state;
    //const { platformVariant } = this.props;
    const outOfStockStyle = {
      opacity:'0.5',
      pointerEvents:'none'
    }
    const columns = [
      {
        title: 'Variants',
        dataIndex: 'blankVariant',
        key: 'blankVariant',
        render: (value, record) => {
          const isOutOfStock = record.isOutOfStock == 1;
          const mockupFile = record.mockupFiles ? record.mockupFiles[0] : null;
          return <table
            style={isOutOfStock ? outOfStockStyle : {}}
          >
            <tbody>
            <tr>
              <td width={64}>
                <Avatar
                  key={record.mockUpFiles ? record.mockUpFiles[0].id : ""}
                  style={{
                    backgroundImage: `url(${
                      record.mockUpFiles[0].thumb_url ? record.mockUpFiles[0].thumb_url : ""
                    })`
                  }}
                  shape="square"
                  size={64}
                  icon=""
                />
              </td>
              <td>
                {record.optionValues &&
                record.optionValues.map(oVal => (
                  <div key={oVal.id} style={{ whiteSpace: "no-wrap" }}>
                    {oVal.option.name}: {oVal.name}{" "}
                    {oVal.hexCode ? (
                      <div
                        style={{
                          display: "inline-block",
                          width: "10px",
                          height: "10px",
                          background: oVal.hexCode
                        }}
                      />
                    ) : null}
                  </div>
                ))}
                <div>teelaunch Price: ${record.teelaunchPrice}</div>
                <div>Retail Price: ${record.retailPrice}</div>
                <div>
                  Profit: $
                  {Number(record.retailPrice - record.teelaunchPrice).toFixed(2)}
                </div>
              </td>
            </tr>
            </tbody>
          </table>
        }
      },
      {
        title: '',
        dataIndex: 'id',
        key: 'id',
        render: (value, record) => {
          const isOutOfStock = record.isOutOfStock == 1;
          if(isOutOfStock) {
            return <span style={outOfStockStyle}>Out of Stock</span>
          } else {
            return <Button onClick={() => this.onClickSelectVariant(value)} disabled={isMappingTeelaunchVariantId}><Spin
              spinning={isMappingTeelaunchVariantId === value}>Link Variant</Spin></Button>
          }
        }
      }
    ];

    return <Spin spinning={isLoadingTeelaunchVariants === product.id}>
      <Table rowKey="id" columns={columns}
             dataSource={product.variants}
             pagination={false} size="small"/>
    </Spin>
  };

  onClickSelectVariant = (productVariantId) => {
    const { orderId } = this.props;
    if (orderId) {
      return this.updateOrderLineItemMapping(productVariantId);
    }
    this.updatePlatformStoreMapping(productVariantId);
  };

  updateOrderLineItemMapping = (productVariantId) => {
    const { orderId, orderLineItemId } = this.props;

    this.setState({ isMappingTeelaunchVariantId: productVariantId });

    axios.post(`/orders/${orderId}/line-items/${orderLineItemId}/variants`, {
      productVariantId,
    }).then(res => {
      this.props.onVariantMapped();
    })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({ isMappingTeelaunchVariantId: false, teelaunchProducts: []})
      });
  };

  updatePlatformStoreMapping = (productVariantId) => {
    const { platformVariant } = this.state;

    this.setState({ isMappingTeelaunchVariantId: productVariantId });

    axios.post(`/variant-mappings`, {
      productVariantId,
      platformStoreProductVariantId: platformVariant.id
    }).then(res => {
      this.props.onVariantMapped();
    })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({ isMappingTeelaunchVariantId: false, teelaunchProducts: []})
      });
  };

  variantMapperContent = () => {
    const { teelaunchProducts } = this.state;
    const columns = [
      {
        title: 'Products',
        dataIndex: 'mainImageThumbUrl',
        key: 'mainImageThumbUrl',
        render: (value, record) => {
          return <table>
            <tbody>
            <tr>
              <td width={64}>
                <Avatar
                  style={{
                    backgroundImage: `url(${value})`
                  }}
                  shape="square"
                  size={64}
                  icon=""/>
              </td>
              <td>
                {record.name}
              </td>
            </tr>
            </tbody>
          </table>
        }
      }
    ];

    return <Table rowKey="id"
                  columns={columns}
                  dataSource={teelaunchProducts}
                  expandedRowRender={(record) => this.getProductVariantRows(record)}
                  expandRowByClick={true}
                  onExpand={(expanded, record) => this.onExpandRow(expanded, record)}
                  size="small"
    />
  };

  render() {
    const { isLoadingTeelaunchProducts } = this.state;
    const {
      visible,
      handleCancel
    } = this.props;

    return (
      <Modal
        title="Link to teelaunch Variant"
        visible={visible}
        centered
        onCancel={handleCancel}
        footer={[
          <Button
            key={1}
            onClick={handleCancel}
          >
            Cancel
          </Button>
        ]}
      >
        <Row>
          <Col>
            <Search
              placeholder="Search teelaunch products"
              onSearch={(value) => this.onSearchTeelaunchProducts(value)}
              style={{ marginBottom: '16px' }}
              enterButton
            />
          </Col>
        </Row>

        <Row>
          <Col span={24} style={{ textAlign: 'center' }}>
            <Spin spinning={isLoadingTeelaunchProducts} tip="Loading...">{this.variantMapperContent()}</Spin>
          </Col>
        </Row>
      </Modal>
    );
  }
}
