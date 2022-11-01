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
import { PlusSquareOutlined } from "@ant-design/icons";

const { Search } = Input;

export default class VariantMapperEnhanced extends Component {
  constructor(props) {
    super(props);
    this.state = {
      teelaunchProducts: [],
      productsPerPage: 20,
      totalTeelaunchProducts:0,
      relatedVariants:[],
      isLoadingTeelaunchProducts: false,
    };
  }
  componentDidMount(){
    this.getTeelaunchProducts();
  }

  onSearchTeelaunchProducts = (query) => {
    this.getTeelaunchProducts({ name: query });
  };

  getTeelaunchProducts = (query = {}) => {
    this.setState({ isLoadingTeelaunchProducts: true, teelaunchProducts: [] });

    axios.get(`/products/platform-products`, { params: { ...query } }).then(res => {
      const { data, meta } = res.data;

      if (data) {
        this.setState({
          teelaunchProducts: data,
          totalTeelaunchProducts: meta.total,
          productsPerPage : meta.per_page
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
    const index = teelaunchProducts.findIndex(x => x.id === record.id);
    if(expanded){
      this.setState({ isLoadingTeelaunchVariants: record.id });
      const {isManualOrder} = this.props
      if(!this.state.relatedVariants.find(v=>v.id == record.id)){
        axios.get(`/products/platform-product/${record.id}`, isManualOrder ? {params:{withFiles:isManualOrder}} : '').then(res => {
          const { data } = res.data;
          if (data && index !== -1) {
            if(isManualOrder){
              teelaunchProducts[index].variants = data.variants.map(variant => {
                return {
                  ...variant,
                  name: data.name,
                  key: variant.id,
                  quantity: 1,
                  artFiles: data.artFiles,
                  stageFiles: data.stageFiles
                }
              })
            }else{
                teelaunchProducts[index].variants = data.variants;
              }
            }
            this.setState({
              teelaunchProducts: teelaunchProducts, relatedVariants: [...this.state.relatedVariants, {
                id: record.id,
                variants: data.variants.map(variant => {
                    return {
                      ...variant,
                      name: data.name,
                      key: variant.id,
                      quantity: 1,
                      artFiles: data.artFiles,
                      stageFiles: data.stageFiles
                    }
                  }
                )
              }]
            })

        }).catch(error => displayErrors(error))
          .finally(() => {
            this.setState({ isLoadingTeelaunchVariants: false});
        })
      }else{
        let localvariants=(this.state.relatedVariants.find(v=>v.id == record.id))
         teelaunchProducts[index].variants = localvariants.variants;
        this.setState({ teelaunchProducts: teelaunchProducts, isLoadingTeelaunchVariants: false});
      }
    }else{
      teelaunchProducts[index].variants = []
      this.setState({ teelaunchProducts: teelaunchProducts, isLoadingTeelaunchVariants: false});
    }
  };

  getProductVariantRows = (product) => {
    const { isMappingTeelaunchVariantId, isLoadingTeelaunchVariants } = this.state;
    const { properties , isManualOrder} = this.props;
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
          const optionValues = isManualOrder ? record.blankVariant.optionValues : record.optionValues
          return (
            <table style={isOutOfStock ? outOfStockStyle : {}} >
              <tbody>
                <tr>
                  <td width={64}>
                    <Avatar
                      key={record?.mockUpFiles ? record?.mockUpFiles[0]?.id : ""}
                      style={{
                        backgroundImage: `url(${isManualOrder ? record.thumb :
                          record?.mockUpFiles[0]?.thumb_url ? record?.mockUpFiles[0]?.thumb_url : ""
                        })`
                      }}
                      shape="square"
                      size={64}
                      icon=""
                    />
                  </td>
                  <td>
                    {properties.includes('optionValues') && optionValues &&
                    optionValues.map(oVal => (
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
                    {properties.includes('sku') && <div>SKU: {record.sku ? record.sku : record.blankVariant.sku}</div>}
                    {properties.includes('price') && <div>Price: ${record.total}</div>}
                    {properties.includes('teelaunchPrice') && <div>teelaunch Price: ${record.price ? record.price : record.blankVariant.price}</div>}
                    {properties.includes('retailPrice') && <div>Retail Price: ${record.total ? record.total : record.total}</div>}
                    {properties.includes('profit') && <div>Profit: $ {Number(record.total - record.price ? record.price : record.blankVariant.price).toFixed(2)} </div>}
                  </td>
                </tr>
              </tbody>
            </table>
          )
        }
      },
      {
        dataIndex: 'id',
        key: 'id',
        render: (value, record) => {
          const isOutOfStock = record.isOutOfStock == 1;
          if(isOutOfStock) {
            return <span style={outOfStockStyle}>Out of Stock</span>
          } else {
            return (
              isManualOrder ?
              <PlusSquareOutlined style={{cursor:'pointer'}} onClick={() => this.onClickSelectVariant(record)}/> :
              <Button onClick={() => this.onClickSelectVariant(value)} disabled={isManualOrder ? false : isMappingTeelaunchVariantId}>
                <Spin spinning={isMappingTeelaunchVariantId === value}>Link Variant</Spin>
              </Button>

            )
          }
        }
      }
    ];

    return <Spin spinning={isLoadingTeelaunchVariants === product.id}>
      <Table
        rowKey="id"
        columns={columns}
        dataSource={product.variants}
        pagination={false} size="small"
      />
    </Spin>
  };

  onClickSelectVariant = (productVariant) => {
    const {isManualOrder} = this.props
    this.setState({isMappingTeelaunchVariantId:true})
    this.props.onSelectVariant(productVariant);
    if(!isManualOrder){
      this.setState({isMappingTeelaunchVariantId:false})
    }
  };

  variantMapperContent = () => {
    const { teelaunchProducts, totalTeelaunchProducts, productsPerPage } = this.state;
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
            pagination={{
              size:'default',
              total:totalTeelaunchProducts,
              defaultPageSize:productsPerPage,
              onChange: (page, pageSize) => {
                this.getTeelaunchProducts({'page':page})
              }
            }}
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
        title={this.props.title}
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
