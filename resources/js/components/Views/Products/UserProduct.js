import React, { Component } from "react";
import axios from "axios";
import { Link } from "react-router-dom";
import {
  Avatar,
  Card,
  Col,
  Icon,
  Row,
  Spin,
  Table,
  message,
  Button,
  Modal,
  Switch,
  Timeline
} from "antd";
import { displayErrors } from "../../../utils/errorHandler";
import BatchActions from "../../Components/Buttons/BatchActions";
import Title from "antd/es/typography/Title";
import StoreSelect from "../../Components/Modals/StoreSelect";
import ForceSyncProductsModal from "../../Components/Modals/ForceSyncProductsModal";

/**
 *
 */
export default class UserProduct extends Component {
  constructor(props) {
    super(props);

    this.state = {
      productId: props.match.params.id,
      product: [],
      storeCollection: [],
      isLoadingProduct: true,
      selectedRowKeys: [],
      forceSelectedRowKeys: [],
      fetchAttempts: 0,
      fetchProductTimeout: null,
      productTimeoutTime: Date.now() - 60000 * 60 * 1,
      isDeleteConfirmationVisible: false,
      isForceSyncConfirmationVisible: false,
      isDeleting: false,
      sendingCreateMockupsRequest: false,
      isStoreModalVisible: false,
      selectedStores: [],
      previouslySyncedProductsData: []
    };
  }

  componentDidMount() {
    this.fetchProduct();
    this.getStoreCollection();
  }

  componentWillUnmount() {
    if (this.state.fetchProductTimeout) {
      clearTimeout(this.state.fetchProductTimeout);
    }
  }

  fetchProduct = () => {
    this.setState(prevState => {
      return {
        ...prevState,
        fetchAttempts: prevState.fetchAttempts + 1
      };
    });
    axios
      .get(`/products/${this.state.productId}`)
      .then(res => {
        const { data } = res.data;
        if (data) {
          this.setState({
            product: data,
            forceSelectedRowKeys: [data.id],
            isLoadingProduct: false
          });
          if (!this.allImagesProcessed() && this.state.fetchAttempts < 120) {
            const fetchProductTimeout = setTimeout(() => {
              this.fetchProduct();
            }, 30000);
            this.setState(prevState => {
              return { ...prevState, fetchProductTimeout };
            });
          }
        }
      })
      .catch((error, res) => {
        if (error.response.status === 404) {
          this.props.history.push("/my-products");
          return;
        }
        displayErrors(error);
      })
      .finally(() => {
        this.setState({ isLoadingProduct: false });
      });
  };

  /**
   * @param {[]} forceSelectedRowKeys
   */
  onForceSelectChange(forceSelectedRowKeys) {
    this.setState({ forceSelectedRowKeys });
  }

  /**
   * On Cancel Force Sync Modal
   */
  handleForceSyncCancel = () => {
    this.setState({
      isForceSyncConfirmationVisible: false,
    });
  };

  allImagesProcessed = () => {
    const processingVariants = this.state.product.variants.filter(variant => {
      const processingStageFiles = variant.stageFiles.filter(stageFile => {
        return !stageFile.finishedAt;
      });
      const processingMockupFiles = variant.mockupFiles.filter(mockupFile => {
        return !mockupFile.finishedAt;
      });
      const processingPrintFiles = variant.printFiles.filter(mockupFile => {
        return !mockupFile.finishedAt;
      });
      return (
        processingStageFiles.length ||
        processingMockupFiles.length ||
        processingPrintFiles.length
      );
    });
    const processingArtFiles = this.state.product.artFiles.filter(artFile => {
      return !artFile.finishedAt;
    });
    return !processingVariants.length && !processingArtFiles;
  };

  onSelectChange(selectedRowKeys) {
    this.setState(prevState => {
      return {
        ...prevState,
        selectedRowKeys
      };
    });
  }

  deleteSelectedVariants() {
    this.setState({
      isDeleteConfirmationVisible: true
    });
  }

  onConfirmDeleteVariant = () => {
    this.setState({ isDeleting: true });
    axios
      .delete(
        `/products/${
          this.state.productId
        }/variants/${this.state.selectedRowKeys.join()}`
      )
      .then(res => {
        this.setState(
          { selectedRowKeys: [], isDeleteConfirmationVisible: false },
          () => this.fetchProduct()
        );
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isDeleting: false }));
  };

  createMockups = () => {
    if (!this.state.sendingCreateMockupsRequest) {
      this.setState({ sendingCreateMockupsRequest: true });
      axios
        .post(`/mockup-files`, {
          productId: this.state.productId
        })
        .then(res => {
          this.fetchProduct();
        })
        .catch(error => {
          displayErrors(error);
        })
        .finally(() => this.setState({ sendingCreateMockupsRequest: false }));
    }
  };

  onStoreChange = event => {
    const { checked, name } = event.target;
    const { selectedStores } = this.state;
    let newSelectedStores = [...selectedStores];

    if (checked) {
      newSelectedStores.push(name);
    } else {
      newSelectedStores = newSelectedStores.filter(id => id !== name);
    }

    this.setState(prevState => {
      return {
        ...prevState,
        selectedStores: newSelectedStores
      };
    });
  };

  /**
   * @param checked
   */
  onOrdersReleaseClick = () => {
    let product = this.state.product;
    product.orderHold = false;
    this.setState({ product: product });

    axios
      .post(`/products/${this.state.productId}/orders-release`)
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;
          if (data) {
            message.success('Upcoming Product Orders will be released');
          }
        }
      })
      .catch(error => {
        displayErrors(error);
        let product = this.state.product;
        product.orderHold = true;
        this.setState({ product: product });
      });
  };

  onOrdersHoldClick = () => {
    let product = this.state.product;
    product.orderHold = true;
    this.setState({ product: product });

    axios
      .post(`/products/${this.state.productId}/orders-hold`)
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;

          if (data) {
            message.success('Upcoming Product Orders will be on hold');
          }
        }
      })
      .catch(error => {
        displayErrors(error);
        let product = this.state.product;
        product.orderHold = false;
        this.setState({ product: product });
      });
  };

  getStoreCollection() {
    axios
      .get('/platforms')
      .then(res => {
        if (res.data) {
          const stores = res.data.data;
          this.setState({
            storeCollection: stores,
          });
        }
      })
      .catch(error => displayErrors(error));
  }

  /**
   *
   */
  sendSelectedProductsToIntegration = (forceSyncProducts) => {
    const {
      selectedStores,
      forceSelectedRowKeys,
      isForceSyncConfirmationVisible,
      product,
      storeCollection
    } = this.state;

    if(isForceSyncConfirmationVisible){
      this.setState({
        isForceSyncConfirmationVisible: false
      })
    }

    const selectedProducts = forceSelectedRowKeys;

    axios
      .post("/platform-products/all", {
        selectedStores,
        selectedProducts,
        forceSyncProducts
      })
      .then(({ data }) => {
        this.setState({
          isStoreModalVisible: false,
          isForceSyncConfirmationVisible: false
        });

        if (data.previouslySynced) {
          const previouslySyncedProducts = data.previouslySyncedProducts;

          if(Object.keys(previouslySyncedProducts).length){
            const previouslySyncedProductsData = [];

            if(Object.keys(previouslySyncedProducts).includes(String(product.id))){
              const productStoreNames = [];
              previouslySyncedProducts[product.id].map( productStores => {
                let productStoreId = productStores.platform_store_id
                storeCollection.map(stores => {
                  stores.stores.map( storeDetails => {
                    if(storeDetails.id === productStoreId){
                      productStoreNames.push(storeDetails.name);
                    }
                  })
                })
              })

              product.store = productStoreNames.join(", ");
              previouslySyncedProductsData.push(product);
            }

            this.setState({
              previouslySyncedProductsData: previouslySyncedProductsData,
              isForceSyncConfirmationVisible: true,
              selectedProductRowKey: data.productsToSync
            })
          }
        }
        else{
          message.success(
            "The product will be available in your store after a few minutes"
          );
          this.setState({
            previouslySyncedProductsData: [],
            isForceSyncConfirmationVisible: false,
          })
        }

      })
      .catch(error => displayErrors(error));
  };

  render() {
    const {
      selectedRowKeys,
      productTimeoutTime,
      isLoadingProduct,
      isDeleteConfirmationVisible,
      isForceSyncConfirmationVisible,
      isDeleting,
      product,
      isStoreModalVisible,
      forceSelectedRowKeys,
      previouslySyncedProductsData
    } = this.state;

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this)
    };

    const forceRowSelection = {
      forceSelectedRowKeys,
      onChange: this.onForceSelectChange.bind(this),
    };

    const hasSelected = selectedRowKeys.length > 0;

    const columns = [
      {
        title: "Variant",
        width: "",
        dataIndex: "blankVariant",
        key: "blankVariant",
        render: (value, record) => (
          <table>
            <tbody>
              <tr>
                <td width={72}>
                  <Avatar
                    style={{ background: "none" }}
                    shape="square"
                    size={64}
                    icon={
                      <img
                        src={
                          value && value.thumbnail
                            ? value.thumbnail
                            : this.state.product.mainImageUrl
                        }
                      />
                    }
                  />
                </td>
                <td>
                  {/*<div style={{whiteSpace: 'no-wrap'}}>SKU: {value.sku}</div>*/}
                  {record.blankVariant &&
                    record.blankVariant.optionValues &&
                    record.blankVariant.optionValues.map(oVal => (
                      <div key={oVal.id} style={{ whiteSpace: "no-wrap" }}>
                        {oVal.option.name}: {oVal.name}{" "}
                        {oVal.hexCode ? (
                          <div
                            style={{
                              display: "inline-block",
                              width: "12px",
                              height: "12px",
                              background: oVal.hexCode,
                              border: "1px solid #ccc"
                            }}
                          />
                        ) : null}
                      </div>
                    ))}
                  <div>teelaunch Price: ${value.price}</div>
                  <div>Retail Price: ${record.price}</div>
                  <div>
                    Profit: ${Number(record.price - value.price).toFixed(2)}
                  </div>
                  <div className="data-id">#{record.id}</div>
                </td>
              </tr>
            </tbody>
          </table>
        )
      },
      {
        title: "Art Files",
        dataIndex: "stageFiles",
        key: "stageFiles",
        render: (value, record) =>
          value.map(stageFile => {
            const createdAt = Date.parse(stageFile.createdAt);

            const artFile = product.artFiles.find(
              artFile => artFile.id === stageFile.productArtFileId
            );
            if (!artFile) {
              return <span key={stageFile.id}>Missing Art File</span>;
            }

            return artFile.fileUrl ? (
              <a key={stageFile.id} href={artFile.fileUrl} target="_blank" download>
                <Avatar
                  key={artFile.id}
                  style={{
                    backgroundColor: "#fff",
                    backgroundImage: `url(${artFile.thumbUrl})`,
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
            ) : createdAt < productTimeoutTime ? (
              <div>Image Failed</div>
            ) : (
              <Spin
                key={stageFile.id}
                tip="Processing..."
                style={{ position: "relative", top: "-2px" }}
              />
            );
          })
      },
      {
        title: "Mockup Files",
        dataIndex: "mockupFiles",
        key: "mockupFiles",
        render: (value, record) => {
          if (!value.length && product.artFiles.length) {
            return (
              <Spin
                tip="Processing..."
                style={{ position: "relative", top: "-2px" }}
              />
            );
          }
          let hasFailedMockup = false;
          value.forEach(file => {
            const createdAt = Date.parse(file.productMockupFile?.updatedAt);
            const mockupFile = product.mockupFiles.find(
              mockupFile => mockupFile.id === file.productMockupFileId
            );

            if (mockupFile) {
              hasFailedMockup =
                mockupFile.status !== 6 && createdAt < productTimeoutTime;
            }
          });

          return (
            <>
              {value.map(file => {
                const createdAt = Date.parse(file.productMockupFile?.updatedAt);

                const mockupFile = product.mockupFiles.find(
                  mockupFile => mockupFile.id === file.productMockupFileId
                );
                if (!mockupFile) {
                  return <span key={file.id}>Missing Mockup File</span>;
                }

                return mockupFile.fileUrl ? (
                  <>
                    <a key={file.id} href={mockupFile.fileUrl} target="_blank" download>
                      <Avatar
                        key={mockupFile.id}
                        style={{
                          backgroundColor: "#fff",
                          backgroundImage: `url(${mockupFile.thumbUrl})`,
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
                  </>
                ) : createdAt < productTimeoutTime ? (
                  <>
                    <div>Image Failed</div>
                  </>
                ) : (
                  <Spin
                    key={file.id}
                    tip="Processing..."
                    style={{ position: "relative", top: "-2px" }}
                  />
                );
              })}
              <br />
              {hasFailedMockup && (
                <Button
                  size="small"
                  type="dashed"
                  onClick={() => this.createMockups()}
                >
                  Retry Mockups
                </Button>
              )}
            </>
          );
        }
      },
      {
        title: "Print Files",
        dataIndex: "printFiles",
        key: "printFiles",
        render: (value, record) => {
          if (!value.length && product.artFiles.length) {
            return (
              <Spin
                tip="Processing..."
                style={{ position: "relative", top: "-2px" }}
              />
            );
          }

          return value.map(file => {
            const createdAt = Date.parse(file.createdAt);
            const printFile = product.printFiles.find(
              printFile => printFile.id === file.productPrintFileId
            );
            if (!printFile) {
              return <span key={file.id}>Missing Print File</span>;
            }
            return printFile.fileUrl ? (
              <a key={file.id} href={printFile.fileUrl} target="_blank" download>
                <Avatar
                  key={printFile.id}
                  style={{
                    backgroundColor: "#fff",
                    backgroundImage: `url(${printFile.thumbUrl})`,
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
            ) : createdAt < productTimeoutTime ? (
              <div>Image Failed</div>
            ) : (
              <Spin
                key={file.id}
                tip="Processing..."
                style={{ position: "relative", top: "-2px" }}
              />
            );
          });
        }
      }
    ];

    return (
      <div>
        <Modal
          title="Delete selected variants?"
          visible={isDeleteConfirmationVisible}
          centered
          content={<p>Confirm Product Delete</p>}
          onCancel={() => this.setState({ isDeleteConfirmationVisible: false })}
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
              <Spin spinning={isDeleting}>Delete</Spin>
            </Button>
          ]}
        >
          {" "}
          {selectedRowKeys.length} variants will be permanently deleted. You
          cannot undo this action.
        </Modal>

        <Row gutter={16}>
          <Col span={24}>
            <Card
              title={
                <table style={{ width: "100%" }}>
                  <tbody>
                    <tr>
                      <td width={64}>
                        {this.state.product.mainImageThumbUrl ? (
                          <Avatar
                            style={{
                              background: "none",
                              borderRadius: 0,
                              marginRight: "8px"
                            }}
                            shape="square"
                            size={64}
                            icon={
                              <img src={this.state.product.mainImageThumbUrl} />
                            }
                          />
                        ) : Date.parse(this.state.product.createdAt) <
                          productTimeoutTime ? (
                          <Avatar
                            style={{
                              background: "none",
                              borderRadius: 0,
                              marginRight: "8px"
                            }}
                            shape="square"
                            size={64}
                            icon=""
                          />
                        ) : (
                          <Spin
                            spinning={
                              this.state.product.createdAt > productTimeoutTime
                            }
                            tip=""
                            style={{ position: "relative", top: "-2px" }}
                          />
                        )}
                      </td>
                      <td style={{
                            position: 'relative',
                            width: '100%',
                      }}>
                        <Title
                          style={{
                            overflow: 'hidden',
                            minHeight:40,
                            position: 'absolute',
                            top: 0,
                            left: 0,
                            right: 0,
                            bottom: 0,
                            textOverflow: 'ellipsis',
                            display: 'block'
                          }}
                        >
                          {this.state.product.name}
                        </Title>
                      </td>
                      <td>
                        {!isLoadingProduct ? (
                          <div style={{ display: 'flex', alignItems: 'center', float: 'right' }}>
                            <Button
                              style={{ float: 'right' }}
                              type=""
                              onClick={ () => { this.props.history.push("/my-products/"+this.state.product.id+"/edit"); }}
                            >
                              Edit Product
                            </Button>
                            { this.state.product.orderHold ? (
                              <Button
                                style={{ float: 'right', marginLeft: '10px' }}
                                type=""
                                onClick={this.onOrdersReleaseClick}
                              >
                                Release Orders
                              </Button>) : (
                              <Button
                                style={{ float: "right", marginLeft: '10px' }}
                                type=""
                                onClick={this.onOrdersHoldClick}
                              >
                                Hold Orders
                              </Button>
                            )
                            }
                            <Button
                              style={{ float: "right", marginLeft: '10px' }}
                              type=""
                              onClick={e => {
                                this.setState({ isStoreModalVisible: true });
                              }}
                            >
                              Send to Store
                            </Button>
                          </div>
                        ) : (<span/>) }
                      </td>
                    </tr>
                  </tbody>
                </table>
              }
            >
              <table>
                <tbody>
                  <tr>
                    <td>
                      <span
                        dangerouslySetInnerHTML={{
                          __html: this.state.product.description
                        }}
                      />
                    </td>
                  </tr>
                </tbody>
              </table>
            </Card>
          </Col>
        </Row>
        <Row gutter={16} style={{ marginTop: "16px" }}>
          <Col span={24}>
            <BatchActions
              hasSelected={hasSelected}
              selectedRowKeys={selectedRowKeys}
            >
              <Button
                type="primary"
                style={{ display: hasSelected ? "initial" : "none" }}
                onClick={() => this.deleteSelectedVariants()}
              >
                <Spin spinning={isDeleting}>Delete</Spin>
              </Button>
            </BatchActions>
            <Spin spinning={isLoadingProduct}>
              <Table
                rowKey="id"
                columns={columns}
                dataSource={this.state.product.variants}
                rowSelection={rowSelection}
                pagination={{
                  pageSize: 20
                }}
              />
            </Spin>
          </Col>
        </Row>
        <StoreSelect
          visible={isStoreModalVisible}
          onCancel={() => this.setState({ isStoreModalVisible: false })}
          handleChange={this.onStoreChange}
          onOk={() => this.sendSelectedProductsToIntegration(false)}
          content={this.getStoreModalContent}
        />
        <ForceSyncProductsModal
          visible={isForceSyncConfirmationVisible}
          onCancel={this.handleForceSyncCancel}
          onOk={() => this.sendSelectedProductsToIntegration(true)}
          previouslySyncedProductsData = {previouslySyncedProductsData}
          forceRowSelection = {forceRowSelection}
        />
        <Row>
          <Title level={3}>Product History</Title>
          <Timeline>
            {product && product.logs && product.logs.length
              ? product.logs.map((status, index) => (
                <Timeline.Item
                  key={status.id}
                >
                  {status.message}{" "}
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
        </Row>
      </div>
    );
  }
}
