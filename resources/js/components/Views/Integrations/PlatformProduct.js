import React, { Component } from "react";
import {
  Avatar,
  Table,
  Col,
  Row,
  Button,
  Modal,
  Typography,
  Input,
  Card,
  Spin,
  message,
  Tag,
  Pagination,
  Timeline
} from "antd";

const { Search } = Input;
import axios from "axios";
import { Link } from "react-router-dom";

const { Title } = Typography;

import { displayErrors } from "../../../utils/errorHandler";
import BatchActions from "../../Components/Buttons/BatchActions";
import VariantMapper from "../../Components/Modals/VariantMapperEnhanced";

/**
 * TODO this file needs more refactoring.
 *
 */
export default class PlatformProduct extends Component {
  /**
   *
   * @param props
   */
  constructor(props) {
    super(props);
     this.state = {
      storeId: props.match.params.storeId,
      productId: props.match.params.id,
      store: {},
      product: {},
      variants: [],
      isVariantMapperVisible: false,
      teelaunchVariants: [],
      isLoadingProducts: false,
      isLoadingTeelaunchProducts: false,
      isLoadingTeelaunchVariants: false,
      selectedRowKeys: [],
      isDeleteConfirmationVisible: false,
      isIgnoreConfirmationVisible: false,
      isUnlinkConfirmationVisible: false,
      platformVariant: {},
      platform: {},
      platformName: "",
      isMappingTeelaunchVariantId: 0,
      isDeletingVariants: false,
      isUnlinkingVariants: false,
      isIgnoringVariants: false,
      updatedProductVariant: {},
    };
  }

  /**
   *
   */
  componentDidMount() {
    this.getPlatformProduct();
  }

  /**
   *
   */
  getPlatformProduct = () => {
    const { storeId, productId } = this.state;

    this.setState({ isLoadingProducts: true });
      axios
        .get(`/stores/${storeId}/products/${productId}`, {
          params: {}
        })
        .then(res => {
          const { data } = res;
          if (data) {
            // Orders Additional Charge For Apparel
            data.variants.map(variant => {
                if(variant.productVariant && variant.productVariant.printFiles) {
                  const surcharge = this.getSurcharge(variant.productVariant.blankVariant.blank.categoryDisplay, variant.productVariant.printFiles);
                  const price = parseFloat(variant.productVariant.blankVariant.price) + surcharge;
                  variant.productVariant.blankVariant.price = price;
                }
            });

            this.setState({
              product: data,
              variants: data.variants,
              store: data.store,
              platform: data.store.platform,
              platformName: data.store.platform.name
            });
          }
        })
        .catch(error => displayErrors(error))
        .finally(() => this.setState({ isLoadingProducts: false }));
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

  /**
   *
   * @param {[]} selectedRowKeys
   */
  onSelectChange(selectedRowKeys) {
    this.setState({ selectedRowKeys });
  }

  /**
   *
   */
  deleteSelectedVariants() {
    this.setState({
      isDeleteConfirmationVisible: true
    });
  }

  unlinkSelectedVariants() {
    this.setState({
      isUnlinkConfirmationVisible: true
    });
  }

  ignoreSelectedVariants() {
    this.setState({
      isIgnoreConfirmationVisible: true
    });
  }

  unignoreSelectedVariants() {
    this.onConfirmUnignoreVariant();
  }

  /**
   * TODO Modal should have a loader on the delete and the modal
   * should also stay open.
   *
   */
  onConfirmDeleteVariant = () => {
    const { selectedRowKeys, storeId, productId } = this.state;
    this.setState({
      isDeletingVariants: true
    });

    axios
      .delete(
        `/stores/${storeId}/products/${productId}/variants/${selectedRowKeys.join()}`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getPlatformProduct();
        this.setState({
          isDeleteConfirmationVisible: false
        });
        message.success("Variants deleted");
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isDeletingVariants: false
        });
      });
  };

  onConfirmUnlinkVariant = () => {
    const { selectedRowKeys, storeId, productId } = this.state;
    this.setState({
      isUnlinkingVariants: true
    });

    axios
      .post(
        `/stores/${storeId}/products/${productId}/variants/${selectedRowKeys.join()}/unlink`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getPlatformProduct();
        this.setState({
          isUnlinkConfirmationVisible: false
        });
        message.success("Products unlinked");
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isUnlinkingVariants: false
        });
      });
  };

  onConfirmIgnoreVariant = () => {
    const { selectedRowKeys, storeId, productId } = this.state;
    this.setState({
      isIgnoringVariants: true
    });

    axios
      .post(
        `/stores/${storeId}/products/${productId}/variants/${selectedRowKeys.join()}/ignore`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: [],
          isIgnoreConfirmationVisible: false
        });
        this.getPlatformProduct();
        message.success('Product variants ignored');
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isIgnoringVariants: false
        });
      });
  };

  onIgnoreSingleVariant = variantId => {
    const { storeId, productId } = this.state;
    this.setState({
      isIgnoringVariants: true
    });

    axios
      .post(
        `/stores/${storeId}/products/${productId}/variants/${variantId}/ignore`
      )
      .then(res => {
        this.setState({
          isIgnoreConfirmationVisible: false
        });
        this.getPlatformProduct();
        message.success("Product variant ignored");
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isIgnoringVariants: false
        });
      });
  };

  onConfirmUnignoreVariant = (recordId = null) => {
    const { selectedRowKeys, storeId, productId } = this.state;
    let variantIds = [];
    if (recordId) {
      variantIds.push(recordId);
    } else {
      variantIds = selectedRowKeys;
    }
    let successMessage = variantIds.length > 1 ? 'Product Variants unignored' : 'Product Variant unignored';
    axios
      .post(
        `/stores/${storeId}/products/${productId}/variants/${variantIds.join()}/unignore`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: [],
          isUnlinkConfirmationVisible: false
        });
        this.getPlatformProduct();
        message.success(successMessage);
      })
      .catch(error => displayErrors(error));
  };

  onClickOpenVariantMapper = platformVariant => {
    this.setState({
      isVariantMapperVisible: true,
      platformVariant: platformVariant
    });
  };

  /**
   * @param {int} productVariantId
   */
  onSelectVariant = productVariantId => {
    this.updatePlatformStoreMapping(productVariantId);
    return
  };


  updatePlatformStoreMapping = (productVariantId) => {
    const { platformVariant } = this.state;
    axios
      .post(`/variant-mappings`, {
        productVariantId: productVariantId,
        platformStoreProductVariantId: platformVariant.id
      })
      .then(res => {
        this.setState({ isVariantMapperVisible: false }, this.getPlatformProduct(productVariantId))
      })
      .catch(error => displayErrors(error))
  };

  /**
   *
   * @param {{}} product
   */
  editProductInPlatform = product => {
    const { link, store, platformProductId } = product;
    let productUrl = link;

    if (store.platform.name === "Etsy") {
      productUrl = `https://www.etsy.com/your/shops/${store.name}/tools/listings/${platformProductId}`;
    }

    const httpsRegex = new RegExp(/^(https?:\/\/)/, "i");
    window.open("https://" + productUrl.replace(httpsRegex, ""), "_blank");
  };
  /**
   * @param {{}} productVariant
   * @param {{}} record
   * @returns {JSX.Element}
   **/
  //MainPage
  displayTeelaunchVariant = (productVariant, record) => {
    const { blankVariant, mockupFiles } = productVariant;
    const mockupFile = mockupFiles ? mockupFiles[0] : null;
    return (
      <table>
        <tbody>
          <tr>
            <td width={64}>
              <Avatar
                key={mockupFile ? mockupFile.id : ""}
                style={{
                  backgroundImage: `url(${
                    mockupFile ? mockupFile.thumbUrl : ""
                  })`
                }}
                shape="square"
                size={64}
                icon=""
              />
            </td>
            <td>
              {blankVariant.optionValues &&
                blankVariant.optionValues.map(oVal => (
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
              <div>teelaunch Price: ${Object.keys(this.state.updatedProductVariant).length !== 0  ? this.state.updatedProductVariant.teelaunchPrice : blankVariant.price }</div>
              <div>Retail Price: ${Object.keys(this.state.updatedProductVariant).length !== 0 ? this.state.updatedProductVariant.retailPrice : productVariant.price}</div>
              <div>
                Profit: ${Object.keys(this.state.updatedProductVariant).length !== 0 ?  Number(this.state.updatedProductVariant.retailPrice - this.state.updatedProductVariant.teelaunchPrice).toFixed(2) : Number(productVariant.price-blankVariant.price).toFixed(2) }
              </div>
            </td>
            <td>
              <div className="button-group">
                <Button onClick={() => this.onClickOpenVariantMapper(record)}>
                  Switch variant
                </Button>
                <span>or&nbsp;</span>
                <Button onClick={() => this.onIgnoreSingleVariant(record.id)}>
                  Not teelaunch
                </Button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    );
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      selectedRowKeys,
      isDeleteConfirmationVisible,
      isIgnoreConfirmationVisible,
      isUnlinkConfirmationVisible,
      isLoadingProducts,
      storeId,
      product,
      variants,
      isVariantMapperVisible,
      platformName,
      isDeletingVariants,
      isUnlinkingVariants,
      isIgnoringVariants,
    } = this.state;

    const platformProductMappingColumns = [
      {
        title: `${platformName} Variant`,
        dataIndex: "variant",
        key: "variantImage",
        render: (value, record) => (
          <table>
            <tbody>
              <tr>
                {record.image && (
                  <td width={64}>
                    <Avatar
                      style={{
                        backgroundImage: `url(${record.image})`
                      }}
                      shape="square"
                      size={64}
                    />
                  </td>
                )}
                <td>
                  {product.title}
                  {record.title && ` - ${record.title}`}
                  <br />
                  SKU: {record.sku}
                  <br />
                  Price: ${record.price}
                  <br />
                  <div className="data-id">#{record.platformVariantId}</div>
                </td>
              </tr>
            </tbody>
          </table>
        )
      },
      {
        title: "teelaunch Variant",
        dataIndex: "productVariant",
        key: "productVariant",
        render: (value, record) =>
          record.isIgnored ? (
            <>
              <Tag style={{ marginRight: 16 }}>Ignored</Tag>
              <Button
                type="dashed"
                size="small"
                onClick={() => this.onConfirmUnignoreVariant(record.id)}
              >
                Unignore
              </Button>
            </>
          ) : value ? (
            <>{this.displayTeelaunchVariant(value, record)}</>
          ) : (
            <div className="button-group">
              <Button onClick={() => this.onClickOpenVariantMapper(record)}>
                Link variant
              </Button>
              <span>or&nbsp;</span>
              <Button onClick={() => this.onIgnoreSingleVariant(record.id)}>
                Not teelaunch
              </Button>
            </div>
          )
      }
    ];

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this)
    };

    const hasSelected = selectedRowKeys.length > 0;

    return (
      <>
        {isVariantMapperVisible &&
          <VariantMapper
            title = "Link to teelaunch Variant"
            properties={['sku', 'optionValues', 'teelaunchPrice', 'retailPrice', 'profit']}
            onSelectVariant={(productVariantId)=>this.onSelectVariant(productVariantId)}
            visible={isVariantMapperVisible}
            handleCancel={() => this.setState({ isVariantMapperVisible: false })}
            isManualOrder={false}
          />
        }

        <Modal
          title="Delete selected variants?"
          visible={isDeleteConfirmationVisible}
          onCancel={() => this.setState({ isDeleteConfirmationVisible: false })}
          centered
          content={<p>Confirm Variant Delete</p>}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isDeleteConfirmationVisible: false })
              }
            >
              Cancel
            </Button>,
            <Button key={2} type="danger" onClick={this.onConfirmDeleteVariant}>
              <Spin spinning={isDeletingVariants}>Delete</Spin>
            </Button>
          ]}
        >
          <p>
            {selectedRowKeys.length} variants will be permanently deleted from{" "}
            <b>teelaunch</b>. The variant will continue to exist in{" "}
            <b>{platformName}</b> and will be re-imported if it is not deleted
            there as well.
          </p>
          <p>
            If you intend to maintain the variant in <b>{platformName}</b> but
            wish to not have <b>teelaunch</b> fulfill the item then you should{" "}
            <i>Ignore</i> the variant instead.
          </p>
        </Modal>

        <Modal
          title="Unlink selected variants?"
          visible={isUnlinkConfirmationVisible}
          onCancel={() => this.setState({ isUnlinkConfirmationVisible: false })}
          centered
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isUnlinkConfirmationVisible: false })
              }
            >
              Cancel
            </Button>,
            <Button key={2} type="danger" onClick={this.onConfirmUnlinkVariant}>
              <Spin spinning={isUnlinkingVariants}>Unlink</Spin>
            </Button>
          ]}
        >
          {selectedRowKeys.length} {platformName} products will be unlinked.
          Ignored products will not be sent for production.
        </Modal>

        <Modal
          title="Ignore selected variants?"
          visible={isIgnoreConfirmationVisible}
          onCancel={() => this.setState({ isIgnoreConfirmationVisible: false })}
          centered
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isIgnoreConfirmationVisible: false })
              }
            >
              Cancel
            </Button>,
            <Button key={2} type="danger" onClick={this.onConfirmIgnoreVariant}>
              <Spin spinning={isIgnoringVariants}>Ignore</Spin>
            </Button>
          ]}
        >
          {selectedRowKeys.length} {platformName} products will be ignored.
          These variants will be ignored when processing orders for production,
          allowing mixed orders to process without errors. Ensure you have
          another method of fulfilling ignored variants.
        </Modal>

        <Row gutter={{ xs: 8, md: 24, lg: 32 }}>
          <Col xs={24} md={24}>
            <Row gutter={16} style={{ marginBottom: 16 }}>
              <Col span={24}>
                <Card>
                  <table style={{ width: "100%" }}>
                    <tbody>
                      <tr>
                        {product.image && (
                          <td width={64}>
                            <Avatar
                              style={{
                                backgroundImage: `url(${product.image})`
                              }}
                              shape="square"
                              size={64}
                            />
                          </td>
                        )}
                        <td>
                          <div>
                            <h1>{product.title}</h1>
                          </div>

                          {platformName !== 'Rutter' && platformName !== 'Launch' ?
                          (<div>
                            <Button
                              onClick={() =>
                                this.editProductInPlatform(product)
                              }
                            >
                              Edit in {platformName}
                            </Button>
                          </div>):""
                          }

                        </td>
                      </tr>
                    </tbody>
                  </table>
                </Card>
              </Col>
            </Row>

            <Row gutter={16}>
              <Col span={24}>
                <BatchActions
                  hasSelected={hasSelected}
                  selectedRowKeys={selectedRowKeys}
                >
                  <div style={{ display: hasSelected ? "initial" : "none" }}>
                    <Button onClick={this.ignoreSelectedVariants.bind(this)}>
                      Not teelaunch
                    </Button>
                    <Button onClick={this.unignoreSelectedVariants.bind(this)}>
                      Unignore
                    </Button>
                    <Button onClick={this.unlinkSelectedVariants.bind(this)}>
                      Unlink
                    </Button>
                    {/*<Button onClick={this.deleteSelectedVariants.bind(this)}>*/}
                    {/*  Delete*/}
                    {/*</Button>*/}
                  </div>
                </BatchActions>
              </Col>
            </Row>
            <Row >
                <Table
                  rowKey="id"
                  columns={platformProductMappingColumns}
                  loading={isLoadingProducts}
                  dataSource={variants}
                  rowSelection={rowSelection}
                  pagination={{ pageSize: 25 }}
                />
            </Row>
          </Col>
        </Row>
        <Row>
          <Col>
            <Title level={3}>Product History</Title>
            <Timeline>
              {product && product.logs && product.logs.length
                ? product.logs.map((status, index) => (
                  <Timeline.Item
                    key={status.id}
                    // color={this.formatStatusCodeColor(status.messageType)}
                  >
                    {status.message}{" "}
                    {status.messageType === 3 &&
                    index === 0 &&
                    hasError == 1 && (
                      <>
                        <Tag color="red" className="error-tag">
                          Resolve Error
                        </Tag>
                      </>
                    )}
                    <br />
                    &nbsp;
                    {new Date(status.createdAt).toLocaleString([], {
                      weekday: "short",
                      year: "numeric",
                      month: "short",
                      day: "numeric",
                      hour: "numeric",
                      minute: "2-digit"
                    })}
                  </Timeline.Item>
                ))
                : null}
            </Timeline>
          </Col>
        </Row>
      </>
    );
  }
}
