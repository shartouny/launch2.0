import React, { Component } from "react";
import { Link, Redirect } from "react-router-dom";
import axios from "axios";
import {
  Badge,
  Input,
  Row,
  Col,
  Table,
  Timeline,
  Typography,
  Button,
  Avatar,
  Spin,
  Modal,
  Tag,
  Icon,
  Tooltip,
  Popconfirm,
  message
} from "antd";

import ImageLibrary from "../../Components/UploadModal/ImageLibrary";
import { displayErrors } from "../../../utils/errorHandler";
import EditAddressModal from "../../Components/Modals/EditAddressModal";
import OrderStatusTag from "../../Components/Tags/OrderStatusTag";
import ImageUploader from "../../Components/UploadModal/ImageUploader";
import VariantMapper from "../../Components/Modals/VariantMapperEnhanced";
import PlatformLogo from "../../Components/Tags/PlatformLogo";
import EditAddressForm from "../../Components/Forms/EditAddressForm";
import imagePlaceHolder from "../../Assets/image-placeholder.svg"

const { Title, Paragraph } = Typography;

//TODO: Store in global const or pull from api
const ORDER_STATUS_HOLD = 0;
const ORDER_STATUS_OUT_OF_STOCK = 2;
const ORDER_STATUS_PENDING = 10;
const ORDER_STATUS_PROCESSING_PAYMENT = 20;
const ORDER_STATUS_PAID = 30;
const ORDER_STATUS_PROCESSING_PRODUCTION = 40;
const ORDER_STATUS_PRODUCTION = 50;
const ORDER_STATUS_PARTIAL_SHIPPED = 60;
const ORDER_STATUS_SHIPPED = 70;
const ORDER_STATUS_PROCESSING_FULFILLMENT = 80;
const ORDER_STATUS_FULFILLED = 90;
const ORDER_STATUS_CANCELLED = 100;
const ORDER_STATUS_IGNORED = 110;
const PRINT_FILE_STATUS_FINISHED = 6;

/**
 *
 */
export default class Order extends Component {
  /**
   *
   * @param {{}} props
   */

  constructor(props) {
    super(props);

    this.state = {
      selectedRowKeys: [],
      isDeletingLineItem: false,
      isLoadingApply: false,
      isLoadingOrderDetails: true,
      showShippingAddressModal: false,
      showDeleteOrderAlert: false,
      showApplyBtn: false,
      isLoadingDelete: false,
      isCancellingOrder: false,
      isImageLibraryVisible: false,
      isCancelConfirmationVisible: false,
      showEditAddressModal: false,
      showEditLineItemModal: false,
      orderData: {},
      uploadedImages: [],
      lineItemId: null,
      lineItemQuantity: null,
      printFileId: null,
      confirmModal: false,
      imageRequirements: {
        storeWidthMin: null,
        storeWidthMax: null,
        storeHeightMin: null,
        storeHeightMax: null,
        storeSizeMinReadable: null,
        storeSizeMaxReadable: null
      },
      selectedCreateType: {
        createType: {}
      },
      isLoadingImages: false,
      pageSize: 1,
      currentPage: 1,
      imageId: null,
      isVariantMapperVisible: false,
      selectedVariant: null,
      orderLineItemId: null,
      isIgnoreConfirmationVisible: false,
      isIgnoringVariants: false,
      platformStoreProductVariantToIgnore: {},
      isProcessingPrintFiles: false,
      isProcessingArtFiles: false,
      getOrdersTimeout: null,
      fetchAttempts: 0,
      maxFetchAttempts: 30,
      refreshRate: 10000,
      lineItems: []
    };

    this.columns = [
      {
        title: "Line Item",
        dataIndex: "title",
        width: 400,
        align: "left",
        render: (value, record) => {
          const platformStoreProductVariant =
            record.platformStoreProductVariant || {};
          const { platformStoreProductId } = platformStoreProductVariant;
          const { store } = this.state.orderData;
          const storeId = store ? store.id : null;
          let properties = JSON.parse(record.properties);

          return (
            <table style={{ minWidth: 200 }}>
              <tbody>
              <tr>
                <td width={72}>
                  <Link
                    to={
                      (storeId &&
                        platformStoreProductId &&
                        `/integrations/${storeId}/products/${platformStoreProductId}`) ||
                      "#"
                    }
                  >
                    <Avatar
                      style={{ background: "none" }}
                      shape="square"
                      size={64}
                      icon={<img src={record.thumbUrl ?? imagePlaceHolder} />}
                    />
                  </Link>
                </td>
                <td>
                  <div>{value}</div>
                  <div>SKU: {record.sku}</div>
                  {properties && properties.custom_text && (this.state.orderData.statusReadable === 'HOLD' || this.state.orderData.statusReadable === 'PENDING') ?
                    <div>Personalization:
                      <Input type="text"
                             value={properties.custom_text}
                             placeholder={"Enter Personalization Text Here"}
                             onChange={e => this.onSaveLineItemsPropertiesChanges(e, this.state.orderData.lineItems.find(item => record.id === item.id))}
                             style={{width: '260px'}}
                      />
                    </div>
                    :
                    ( properties && properties.custom_text && <div>Personalization: {properties.custom_text}</div> )
                  }
                  <div>
                    Retail Price: {this.formatValueToUsCurrency(record.price)}
                  </div>
                  <div className="data-id">#{record.platformVariantId}</div>
                </td>
              </tr>
              </tbody>
            </table>
          );
        }
      },
      {
        title: "teelaunch Variant",
        align: "left",
        dataIndex: "platformStoreProductVariant.productVariant",
        render: (value, lineItem) => {
          const { isIgnoringVariants } = this.state;
          const { status } = this.state.orderData;
          const platformStoreProductVariant =
            lineItem.platformStoreProductVariant || {};
          const { productVariant } = lineItem.productVariant
            ? lineItem
            : platformStoreProductVariant || {};
          const { blankVariant } = productVariant || {};
          const env = process.env.MIX_APP_ENV;

          if (
            status > ORDER_STATUS_PAID &&
            !productVariant &&
            lineItem.productVariantId
          ) {
            return <Tag style={{ marginRight: 16 }}>Deleted</Tag>;
          }

          if (
            (status > ORDER_STATUS_PENDING && !productVariant) ||
            (status <= ORDER_STATUS_PENDING &&
              platformStoreProductVariant &&
              platformStoreProductVariant.isIgnored)
          ) {
            return (
              <div>
                <Tag style={{ marginRight: 16 }}>Ignored</Tag>
                <Button
                  type="dashed"
                  size="small"
                  style={
                    platformStoreProductVariant.isIgnored &&
                    status <= ORDER_STATUS_PENDING
                      ? {}
                      : { display: "none" }
                  }
                  onClick={() =>
                    this.onUnignoreSingleVariant(platformStoreProductVariant)
                  }
                >
                  <Spin spinning={isIgnoringVariants}>Unignore</Spin>
                </Button>
              </div>
            );
          }

          if (
            !productVariant &&
            (status <= ORDER_STATUS_PENDING || env === "local")
          ) {
            return (
              <div>
                <div className="button-group">
                  <Button onClick={() => this.onLinkVariant(lineItem)}>
                    Link variant
                  </Button>

                  <span>or&nbsp;</span>

                  <Button
                    onClick={() =>
                      this.onIgnoreSingleVariant(platformStoreProductVariant)
                    }
                  >
                    Not teelaunch
                  </Button>
                </div>
                {this.state.orderData.hasError === 1 && (
                  <div style={{display: 'flex'}}>
                    <Tag color="red"
                         style={{margin: '0 auto'}}
                         className="error-tag">
                      Resolve Error
                    </Tag>
                  </div>
                )}
              </div>
            );
          }

          return (
            <>
              <table>
                <tbody>
                <tr>
                  <td width={72}>
                    <Link to={`/my-products/${productVariant.productId}`}>
                      <Avatar
                        style={{
                          backgroundColor: "#fff",
                          backgroundImage: `url(${productVariant.thumbnail})`,
                          backgroundPosition: "center",
                          backgroundSize: "contain",
                          backgroundRepeat: "no-repeat",
                          borderRadius: 0,
                          border: "0px solid #ccc"
                        }}
                        shape="square"
                        size={64}
                        icon=""
                      />
                    </Link>
                  </td>
                  <td>
                    {blankVariant &&
                    blankVariant.optionValues &&
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
                    <div>SKU: {blankVariant && blankVariant.sku}</div>
                    {/*<div>Price: {blankVariant && this.formatValueToUsCurrency(blankVariant.price)}</div>*/}
                    {/*<div>Profit: {blankVariant && platformStoreProductVariant && this.formatValueToUsCurrency(Number(platformStoreProductVariant.price - blankVariant.price).toFixed(2))}</div>*/}
                    <div className="data-id">#{productVariant.id}</div>

                    {(status <= ORDER_STATUS_PENDING || env === "local") && (
                      <div style={{ marginTop: 8 }}>
                        <Button
                          onClick={() => this.onLinkVariant(lineItem)}
                          type="dashed"
                          size="small"
                        >
                          Switch Variant
                        </Button>
                      </div>
                    )}
                  </td>
                </tr>
                </tbody>
              </table>
            </>
          );
        }
      },
      {
        title: "Art Files",
        align: "center",
        dataIndex: "platformStoreProductVariant.productVariant.stageFiles",
        render: (value, lineItem) => {
          const { status } = this.state.orderData;
          const platformStoreProductVariant = lineItem.platformStoreProductVariant ? lineItem.platformStoreProductVariant : {};
          const productVariant = lineItem.productVariant ? lineItem.productVariant : platformStoreProductVariant.productVariant ? platformStoreProductVariant.productVariant : {};
          const artFiles = productVariant ? productVariant.stageFiles : [];
          let properties = JSON.parse(lineItem.properties);

          if (status > ORDER_STATUS_PAID && !productVariant) {
            return <div>N/A</div>;
          }
          if (status <= ORDER_STATUS_PAID && platformStoreProductVariant && platformStoreProductVariant.isIgnored) {
            return <div>N/A</div>;
          }
          if (!artFiles) {
            return <div>N/A</div>;
          }

          return (
            artFiles && artFiles.length || (properties && properties.custom_print) ? (
                artFiles && artFiles.length ? (
                  <div>
                    {artFiles.map(file => {
                      const replacement = lineItem.artFiles.find(
                        af => af.productArtFileId === file.productArtFile.id
                      ) || null;

                      const fileUrl = replacement ? replacement.fileUrl : file.productArtFile ? file.productArtFile.fileUrl : null;
                      const thumbUrl = replacement ? replacement.thumbUrl : file.productArtFile ? file.productArtFile.thumbUrl : null;

                      return (
                        <div key={file.id}>
                          {replacement && replacement.status < PRINT_FILE_STATUS_FINISHED ? (
                            <Spin tip="Processing..." />
                          ) : (
                            <>
                              <a href={fileUrl} download>
                                <Avatar
                                  key={file.id}
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
                                {file.blankStageLocation && file.blankStageLocation.shortName}
                              </div>
                            </>
                          )}
                          {/* This caused issues if customer has same personalization field name for non monogram */}
                          {/* {status <= ORDER_STATUS_PAID && !(properties && properties.custom_text) && ( */}
                          {status <= ORDER_STATUS_PAID && (
                            <div style={{display: "flex", flexDirection: "column", marginTop: "4px"}}>
                              <Button size="small" type="dashed"
                                      onClick={() =>
                                        this.openImageUpload(lineItem, file)
                                      }
                                      style={{position: "relative", top: "-8px", marginBottom: "4px"}}
                              >
                                Change File
                              </Button>
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                ) : (
                  <>
                    <a href={properties && properties.custom_print} download>
                      <Avatar
                        key={properties && properties._print}
                        style={{
                          backgroundColor: "#fff",
                          backgroundImage: `url(${properties && properties.custom_print})`,
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
                )
              ) :
              <></>
          );
        }
      },
      // {
      //   title: 'Print Files',
      //   align: 'center',
      //   dataIndex: 'platformStoreProductVariant.productVariant.printFiles',
      //   render: (value, lineItem) => {
      //     const {isProcessingArtFiles, isProcessingPrintFiles} = this.state;
      //     const { status } = this.state.orderData;
      //     const platformStoreProductVariant = lineItem.platformStoreProductVariant ? lineItem.platformStoreProductVariant : {};
      //     const productVariant = lineItem.productVariant ?  lineItem.productVariant : platformStoreProductVariant.productVariant ? platformStoreProductVariant.productVariant : {};
      //     const { printFiles } = productVariant ? productVariant : [];
      //
      //     if (status > ORDER_STATUS_PAID && !productVariant ) {
      //       return <div>N/A</div>
      //     }
      //     if ( status <= ORDER_STATUS_PAID && platformStoreProductVariant && platformStoreProductVariant.isIgnored) {
      //       return <div>N/A</div>
      //     }
      //     if (!printFiles) {
      //       return <div>N/A</div>
      //     }
      //
      //     return printFiles && printFiles.length && (
      //       <div>
      //         {printFiles.map(file => {
      //           const replacement = lineItem.printFiles.find(pf => pf.productPrintFileId === file.productPrintFileId) || null;
      //
      //           const fileUrl = replacement ? replacement.fileUrl : file.fileUrl;
      //           const thumbUrl = replacement ? replacement.thumbUrl : file.thumbUrl;
      //
      //           return <div key={file.id}>
      //             {isProcessingArtFiles || replacement && replacement.status < PRINT_FILE_STATUS_FINISHED ? <Spin tip="Processing..."/> :
      //               <a href={fileUrl} download>
      //                 <Avatar
      //                   key={file.id}
      //                   style={{
      //                     backgroundColor: '#fff',
      //                     backgroundImage: `url(${thumbUrl})`,
      //                     backgroundPosition: 'center',
      //                     backgroundSize: 'contain',
      //                     backgroundRepeat: 'no-repeat',
      //                     borderRadius: 0,
      //                     border: '1px solid #ccc'
      //                   }}
      //                   shape="square"
      //                   size={64}
      //                   icon=""
      //                 />
      //               </a>}
      //
      //
      //             {/*{status <= ORDER_STATUS_PAID &&*/}
      //             {/*<div style={{*/}
      //             {/*  display: 'flex',*/}
      //             {/*  flexDirection: 'column',*/}
      //             {/*  marginTop: '4px'*/}
      //             {/*}}>*/}
      //             {/*  <Button*/}
      //             {/*    size="small"*/}
      //             {/*    type="dashed"*/}
      //             {/*    onClick={() => this.openImageUpload(lineItem, file)}*/}
      //             {/*    style={{ marginBottom: '4px' }}*/}
      //             {/*  >*/}
      //             {/*    Change Print File*/}
      //             {/*  </Button>*/}
      //             {/*</div>*/}
      //             {/*}*/}
      //
      //           </div>
      //         })}
      //       </div>
      //     )
      //   }
      // },
      {
        title: "Price",
        dataIndex:
          "productVariant.blankVariant.price",
        align: "right",
        key: "price",
        render: (value, record) => {
          if (this.state.orderData.status >= ORDER_STATUS_PAID) {
            const unitCost = this.getLineItemUnitCost(record.id);
            if (!unitCost) {
              return <div>N/A</div>;
            }
            return (
              <div>
                {this.formatValueToUsCurrency(unitCost)}
              </div>
            );
          }
          if (
            record.platformStoreProductVariant &&
            record.platformStoreProductVariant.isIgnored
          ) {
            return <div>N/A</div>;
          }
          return <span>{value && this.formatValueToUsCurrency(value)}</span>;
        }
      },
      {
        title: "Qty",
        dataIndex: "quantity",
        align: "center",
        width: 60,
        render: (value, record) => {
          const orderData = {...this.state.orderData}
          const lineItem = orderData.lineItems.find(item => record.id === item.id);
          const { statusReadable } = this.state.orderData;

          return (statusReadable === 'HOLD' || statusReadable === 'PENDING') ? <Input type="number"
                                                                                      value={lineItem.quantity}
                                                                                      min={1}
                                                                                      onChange={e => this.onSaveLineItemsChanges(e, lineItem)}
                                                                                      style={{
                                                                                        width: '60px'
                                                                                      }} /> : <p style={{margin: '0'}}>{value}</p>
        }
      },
      {
        title: "Total",
        dataIndex:
          "productVariant.blankVariant.price",
        align: "right",
        key: "total_price",
        render: (value, record) => {
          if (this.state.orderData.status >= ORDER_STATUS_PAID) {
            const unitCost = this.getLineItemUnitCost(record.id);
            if (!unitCost) {
              return <div>N/A</div>;
            }
            return (
              <div>
                {this.formatValueToUsCurrency(record.quantity * unitCost)}
              </div>
            );
          }

          if (
            record.platformStoreProductVariant &&
            record.platformStoreProductVariant.isIgnored
          ) {
            return <div>N/A</div>;
          }
          return (
            <span>
              {value && this.formatValueToUsCurrency(record.quantity * value)}
            </span>
          );
        }
      }
    ];

    this.styles = {
      shippingHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center'
      },
      sideInfo: {
        lineHeight: 1,
        margin: "10px 0"
      },
      tableFooter: {
        padding: 0,
        display: "flex",
        flexDirection: "column",
        justifyContent: "flex-end",
        alignItems: "flex-end",
        lineHeight: 1.0,
        margin: "20px 0"
      },
      totalText: {
        marginTop: "5px",
        fontWeight: "bold"
      },
      refundText: {
        fontWeight: "bold",
        color: "red"
      },
      addressHeader: {
        display: "flex",
        justifyContent: "space-between",
        alignItems: "center",
      }
    };
  }

  /**
   *
   */
  componentDidMount() {
    this.setState({ isLoadingOrderDetails: true });
    this.fetchOrder();
  }

  componentWillUnmount() {
    if (this.state.getOrdersTimeout) {
      clearTimeout(this.state.getOrdersTimeout);
    }
  }

  paginateOrder = orderId => {
    this.props.history.push(`/orders/${orderId}`);
  };

  onUnignoreSingleVariant = platformStoreProductVariant => {
    this.setState({ isIgnoringVariants: true });
    const { platformStoreId } = this.state.orderData;
    axios
      .post(
        `/stores/${platformStoreId}/products/${platformStoreProductVariant.platformStoreProductId}/variants/${platformStoreProductVariant.id}/unignore`
      )
      .then(res => {
        this.fetchOrder();
        message.success("Platform Variant unignored");
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isIgnoringVariants: false
        });
      });
  };

  onSaveLineItemsChanges = (e, lineItem) => {
    const orderData = {...this.state.orderData}
    const prevLineItems = [...this.state.lineItems];

    lineItem.quantity = Number(e.target.value)

    let found = false;
    prevLineItems.forEach((prevItem, indexPrev) => {
      orderData.lineItems.forEach((currItem, indexCurr) => {
        if (currItem.quantity !== prevItem.quantity && indexPrev === indexCurr) {
          found = true
        }
      })

      if (found) {
        this.setState({showApplyBtn: true})
        return;
      }
      this.setState({showApplyBtn: false})
    })

    this.setState({orderData})
  }

  onSaveLineItemsPropertiesChanges = (e, lineItem) => {
    const orderData = {...this.state.orderData}
    const prevLineItems = [...this.state.lineItems];

    const properties = JSON.parse(lineItem.properties);
    properties.custom_text = e.target.value;

    lineItem.properties = JSON.stringify(properties);

    let found = false;
    prevLineItems.forEach((prevItem, indexPrev) => {
      orderData.lineItems.forEach((currItem, indexCurr) => {
        if (JSON.parse(currItem.properties).custom_text !== JSON.parse(prevItem.properties).custom_text && indexPrev === indexCurr) {
          found = true
        }
      })

      if (found) {
        this.setState({showApplyBtn: true})
        return;
      }
      this.setState({showApplyBtn: false})
    })

    this.setState({orderData})
  }

  onIgnoreSingleVariant = platformStoreProductVariant => {
    this.setState({
      isIgnoreConfirmationVisible: true,
      platformStoreProductVariantToIgnore: platformStoreProductVariant
    });
  };

  onConfirmIgnoreVariant = () => {
    this.setState({ isIgnoringVariants: true });
    const { platformStoreProductVariantToIgnore } = this.state;
    const { platformStoreId } = this.state.orderData;
    axios
      .post(
        `/stores/${platformStoreId}/products/${platformStoreProductVariantToIgnore.platformStoreProductId}/variants/${platformStoreProductVariantToIgnore.id}/ignore`
      )
      .then(res => {
        this.setState({
          isIgnoreConfirmationVisible: false,
          platformStoreProductVariantToIgnore: {}
        });
        this.fetchOrder();
        message.success("Platform Variant ignored");
      })
      .catch(error => displayErrors(error))
      .finally(() => {
        this.setState({
          isIgnoringVariants: false
        });
      });
  };

  onDeleteLineItems = () => {
    const {selectedRowKeys} = this.state;

    axios.delete(`/orders/${this.state.orderData.id}/line-items`, {
      data: {
        'ids': selectedRowKeys
      }
    }).then(() => this.setState({showDeleteOrderAlert: false}))
      .catch(error => message.error(error))

    if (selectedRowKeys.length === this.state.orderData.lineItems.length) {
      axios.delete(`/orders/${this.state.orderData.id}`)
        .then(() => {
          this.props.history.push('/orders');

        }).catch(error => message.error(error))
    }

    const orderData = {...this.state.orderData};
    const lineItems = orderData.lineItems.filter(item => !selectedRowKeys.includes(item.id))
    orderData.lineItems = lineItems;

    this.setState({
      orderData,
      selectedRowKeys: []
    })
  }

  onLinkVariant = lineItem => {
    const platformStoreProductVariant =
      lineItem.platformStoreProductVariant || null;
    if (!platformStoreProductVariant) {
      return displayErrors(
        "Line Item cannot be linked as there is no record of the platform product variant"
      );
    }
    this.setState({
      isVariantMapperVisible: true,
      selectedVariant: platformStoreProductVariant,
      orderLineItemId: lineItem.id
    });
  };

  getLineItemUnitCost = lineItemId => {
    const payments = this.state.orderData.payments.map(payment => {
      if (payment.accountPayment.status === 1) {
        return payment.lineItems.find(lineItem => {
          return lineItem.orderLineItemId === lineItemId;
        });
      }
    });

    return (payments[0] && payments[0].unitCost) || null;
  };

  /**
   *
   */
  fetchOrder = () => {
    const { fetchAttempts, maxFetchAttempts, refreshRate } = this.state;
    const { match } = this.props;
    const { params } = match;

    if (!params.id) {
      displayErrors("Unable to find order, please try again");
    }

    axios
      .get(`/orders/${params.id}`)
      .then(res => {
        const { data } = res.data;

        if (!data) {
          displayErrors("No data found for this order");
        }

        // const isProcessingPrintFiles = !!(data.lineItems.find(lineItem => {
        //   return lineItem.printFiles.find(printFile => {
        //     return printFile.status < PRINT_FILE_STATUS_FINISHED
        //   });
        // }) || null);

        const isProcessingArtFiles = !!(
          data.lineItems.find(lineItem => {
            return lineItem.artFiles.find(artFile => {
              return artFile.status < PRINT_FILE_STATUS_FINISHED;
            });
          }) || null
        );

        let getOrdersTimeout = null;
        if (isProcessingArtFiles && fetchAttempts < maxFetchAttempts) {
          getOrdersTimeout = setTimeout(() => this.fetchOrder(), refreshRate);
        }

        //Additional Charge For Apparel
        data.lineItems.map(lineItem => {
          if(lineItem.productVariant) {
            const surcharge = this.getSurcharge(lineItem.productVariant.blankVariant.blank.categoryDisplay, lineItem.productVariant.printFiles);
            const price = parseFloat(lineItem.productVariant.blankVariant.price) + surcharge;
            lineItem.productVariant.blankVariant.price = price;
          }

          if(lineItem.platformStoreProductVariant && lineItem.platformStoreProductVariant.productVariant && lineItem.platformStoreProductVariant.productVariant.printFiles) {
            const surcharge = this.getSurcharge(lineItem.platformStoreProductVariant.productVariant.blankVariant.blank.categoryDisplay, lineItem.platformStoreProductVariant.productVariant.printFiles);
            const price = parseFloat(lineItem.platformStoreProductVariant.productVariant.blankVariant.price) + surcharge;
            lineItem.platformStoreProductVariant.productVariant.blankVariant.price = price;
          }
        });

        this.setState({
          orderData: data,
          lineItems: JSON.parse(JSON.stringify(data.lineItems)),
          getOrdersTimeout,
          fetchAttempts: fetchAttempts + 1
        });
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingOrderDetails: false }));
  };
  /**
   *
   * @param {{}} lineItem
   * @param {{}} artFile
   */
  openImageUpload = (lineItem, file) => {

    const storeWidthMin = file.blankStage?.createTypes[0]?.imageRequirement?.storeWidthMin;
    const storeWidthMax = file.blankStage?.createTypes[0]?.imageRequirement?.storeWidthMax;
    const storeHeightMin = file.blankStage?.createTypes[0]?.imageRequirement?.storeHeightMin;
    const storeHeightMax = file.blankStage?.createTypes[0]?.imageRequirement?.storeHeightMax;
    const storeSizeMinReadable = file.blankStage?.createTypes[0]?.imageRequirement?.storeSizeMinReadable;
    const storeSizeMaxReadable = file.blankStage?.createTypes[0]?.imageRequirement?.storeSizeMaxReadable;

    this.setState(
      {
        lineItemId: lineItem.id,
        printFileId: file.productArtFile.id,
        imageRequirements: {
          storeWidthMin: storeWidthMin ?? file.productArtFile.width,
          storeWidthMax: storeWidthMax ?? file.productArtFile.width,
          storeHeightMin: storeHeightMin ?? file.productArtFile.height,
          storeHeightMax: storeHeightMax ?? file.productArtFile.height,
          storeSizeMinReadable: storeSizeMinReadable ?? null,
          storeSizeMaxReadable: storeSizeMaxReadable ?? null
        },
        imageTypes: [file.imageType],
        selectedCreateType: {
          createType: file.blankStageCreateType ?? null
        },
        isImageLibraryVisible: true,
        productArtFile: file.productArtFile
      },
      this.getImages
    );
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
    const selectedCreateType = this.state.selectedCreateType;
    return axios
      .get(`/account-images`, {
        params: {
          page: page,
          file_name: query,
          blankStageId: productArtFile.blankStageId,
          createTypeId: selectedCreateType.createType?.id
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

  /**
   *
   * @param {string|number} value
   * @returns {string}
   */
  formatValueToUsCurrency = value => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD"
    }).format(value);
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
   * @param {string|number} code
   * @returns {string}
   */
  formatStatusCodeColor = code => {
    let color = "blue";

    switch (code) {
      case 1:
        color = "blue";
        break;
      case 2:
        color = "yellow";
        break;
      case 3:
        color = "red";
        break;
    }

    return color;
  };
  /**
   *
   */
  onCancelOrder = () => {
    const { orderData } = this.state;

    this.setState({ isCancellingOrder: true });
    axios
      .post(`/orders/${orderData.id}/cancel`)
      .then(res => {
        const { data } = res.data;
        const order = data[0];

        if (!order) {
          throw new Error("Cancel failed");
        }

        this.setState(prevState => ({
          orderData: {
            ...prevState.orderData,
            logs: order.logs,
            status: order.status,
            statusReadable: order.statusReadable
          }
        }));
      })
      .catch(error => displayErrors(error))
      .then(() =>
        this.setState({
          isCancellingOrder: false,
          isCancelConfirmationVisible: false
        })
      );
  };
  /**
   *
   */
  releaseOrder = () => {
    const { orderData } = this.state;

    this.setState({ isReleasingOrder: true });
    axios
      .post(`/orders/${orderData.id}/release`)
      .then(res => {
        const { data } = res.data;
        const order = data[0];

        if (!order) {
          throw new Error("Release Order failed");
        }

        this.setState(prevState => ({
          orderData: {
            ...prevState.orderData,
            logs: order.logs,
            status: order.status,
            statusReadable: order.statusReadable
          }
        }));

        message.success("Order set to Pending");
      })
      .catch(error => displayErrors(error))
      .then(() => this.setState({ isReleasingOrder: false }));
  };
  /**
   *
   */
  holdOrder = () => {
    const { orderData } = this.state;

    this.setState({ isHoldingOrder: true });
    axios
      .post(`/orders/${orderData.id}/hold`)
      .then(res => {
        const { data } = res.data;
        const order = data[0];

        if (!order) {
          throw new Error("Hold Order failed");
        }

        this.setState(prevState => ({
          orderData: {
            ...prevState.orderData,
            logs: order.logs,
            status: order.status,
            statusReadable: order.statusReadable
          }
        }));

        message.success("Order placed on Hold");
      })
      .catch(error => displayErrors(error))
      .then(() => this.setState({ isHoldingOrder: false }));
  };

  clearError = () => {
    const { orderData } = this.state;

    this.setState({ isClearingError: true });
    axios
      .post(`/orders/${orderData.id}/clear-error`)
      .then(res => {
        const { data } = res.data;
        const order = data[0];

        if (!order) {
          throw new Error("Clear error failed");
        }

        this.setState(prevState => ({
          orderData: {
            ...prevState.orderData,
            logs: order.logs,
            status: order.status,
            statusReadable: order.statusReadable,
            hasError: order.hasError
          }
        }));

        message.success("Errors cleared");
      })
      .catch(error => displayErrors(error))
      .then(() => this.setState({ isClearingError: false }));
  };
  /**
   *
   * @param {[]} payments
   * @param {string} property
   * @returns {string}
   */
  sumTotalAmounts = (payments, property) => {
    const total = payments
      .filter(payment => payment.accountPayment.status === 1)
      .reduce((acc, curr) => (acc += Number(curr[property])), 0);
    return this.formatValueToUsCurrency(total);
  };

  trackingMethodDisplay = tracking => {
    return tracking.carrier && tracking.method
      ? tracking.carrier + ": " + tracking.method
      : tracking.carrier
        ? tracking.carrier
        : "N/A";
  };

  /**
   *
   * @param {{}} file
   */
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
  /**
   *
   * @param file
   * @return {Promise<AxiosResponse<any> | any>}
   */
  onUploadImageToServer = file => {
    let formData = new FormData();
    const { orderData, lineItemId, printFileId } = this.state;

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
          createTypeId: this.state.selectedCreateType.id
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
            this.onSelectedImage(data.data)
            return data.data;
          }
        }
        return false;
      })
      .catch(error => {
        displayErrors(error);
        return false;
      });

    // return axios.post(`/orders/${orderData.id}/line-items/${lineItemId}/print-files`, formData, {
    //   params: {
    //     printFileId: printFileId,
    //   },
    //   headers: {
    //     'Content-Type': 'multipart/form-data'
    //   }
    // })
    //   .then(res => {
    //     const { data } = res;
    //     if (data) {
    //       return data.data;
    //     }
    //   })
    //   .catch(error => {
    //     displayErrors(error);
    //     return false;
    //   });
  };
  /**
   *
   * @return {Promise<AxiosResponse<any> | any>}
   */
  onDeleteImageServer = () => {
    const { imageId } = this.state;

    return axios
      .delete(`/account-images/${imageId}`)
      .then(res => {
        if (res.status === 200) {
          this.getImages();
        }
      })
      .catch(error => message.error(error))
      .finally(() => {});
  };

  editBillingAddress = address => {
    const orderData = {...this.state.orderData};
    const billingAddress = {...this.state.orderData.billingAddress};

    billingAddress.first_name = address.first_name;
    billingAddress.last_name = address.last_name;
    billingAddress.company = address.company;
    billingAddress.address1 = address.address1;
    billingAddress.address2 = address.address2;
    billingAddress.city = address.city;
    billingAddress.state = address.state;
    billingAddress.zip = address.zip;
    billingAddress.country = address.country;
    billingAddress.phone = address.phone;

    orderData.billingAddress = billingAddress;

    this.setState({orderData});
  }

  editShippingAddress = address => {
    const orderData = {...this.state.orderData};
    const shippingAddress = {...this.state.orderData.shippingAddress};
    const fullName = address.fullName.split(',');

    shippingAddress.firstName = fullName[0];
    shippingAddress.lastName = fullName[1] || '';
    shippingAddress.address1 = address.address1;
    shippingAddress.address2 = address.address2;
    shippingAddress.city = address.city;
    shippingAddress.state = address.state;
    shippingAddress.zip = address.zip;
    shippingAddress.country = address.country;
    shippingAddress.phone = address.phone;

    orderData.shippingAddress = shippingAddress;

    this.setState({orderData});
  }

  /**
   *
   */
  getPrintFiles = () => {
    const { orderData, lineItemId, printFileId } = this.state;

    axios
      .get(`/orders/${orderData.id}/line-items/${lineItemId}/print-files`, {
        params: {
          printFileId
        }
      })
      .then(res => {
        const { data } = res;
        if (data) {
          this.setState({ uploadedImages: data.data });
        }
      })
      .catch(error => displayErrors(error));
  };

  /**
   *
   */
  onSelectedImage = image => {
    //this.updatePrintFile(image);
    this.updateArtFile(image);
  };

  updatePrintFile = image => {
    const { orderData, lineItemId, productArtFile } = this.state;

    axios
      .post(`/orders/${orderData.id}/line-items/${lineItemId}/print-files`, {
        productId: productArtFile.productId,
        blankStageId: productArtFile.blankStageId,
        blankStageLocationId: productArtFile.blankStageLocationId,
        accountImageId: image.id
      })
      .then(res => {
        const { data } = res;
        // if (data) {
        //   this.setState({ uploadedImages: data.data });
        // }

        this.setState({ isImageLibraryVisible: false });

        this.fetchOrder();
      })
      .catch(error => displayErrors(error));
  };

  updateLogs = log => {
    const orderData = {...this.state.orderData};
    orderData.logs = [log, ...orderData.logs];

    this.setState({orderData});
  }

  updateOrderLineItems = e => {
    e.preventDefault();
    const {orderData} = this.state;

    this.setState({isLoadingApply: true});

    axios.post(`/orders/${orderData.id}/line-items`, {
      _method: 'PUT',
      lineItems: orderData.lineItems.map(({id, quantity, price, properties}) => {
        return {id, quantity, price, properties}
      })

    }).then(({data}) => {
      const logs = data.logs.map(log => {
        return {
          id: log.id,
          orderId: log.order_id,
          message: log.message,
          messageType: log.message_type,
          updatedAt: log.updated_at,
          createdAt: log.created_at
        }
      })

      orderData.logs = [...logs, ...orderData.logs];

      //Additional Charge For Apparel
      orderData.lineItems.map(lineItem => {
        if(lineItem.productVariant) {
          const surcharge = this.getSurcharge(lineItem.productVariant.blankVariant.blank.categoryDisplay, lineItem.productVariant.printFiles);
          const price = parseFloat(lineItem.productVariant.blankVariant.price) + surcharge;
          lineItem.productVariant.blankVariant.price = price;
        }

        if(lineItem.platformStoreProductVariant && lineItem.platformStoreProductVariant.productVariant && lineItem.platformStoreProductVariant.productVariant.printFiles) {
          const surcharge = this.getSurcharge(lineItem.platformStoreProductVariant.productVariant.blankVariant.blank.categoryDisplay, lineItem.platformStoreProductVariant.productVariant.printFiles);
          const price = parseFloat(lineItem.platformStoreProductVariant.productVariant.blankVariant.price) + surcharge;
          lineItem.platformStoreProductVariant.productVariant.blankVariant.price = price;
        }
      });

      this.setState({
        orderData,
        showApplyBtn: false,
        isLoadingApply: false,
        lineItems: JSON.parse(JSON.stringify(orderData.lineItems))
      })

      message.success(data.message);

    }).catch(error => {
      message.error(error);

    }).finally(() => this.setState({isLoadingApply: false}))
  }

  updateArtFile = image => {
    const { orderData, lineItemId, productArtFile } = this.state;

    axios
      .post(
        `/orders/${orderData.id}/line-items/${lineItemId}/art-files/${productArtFile.id}`,
        {
          ...productArtFile,
          accountImageId: image.id
        }
      )
      .then(res => {
        const { data } = res;
        // if (data) {
        //   this.setState({ uploadedImages: data.data });
        // }

        this.setState({ isImageLibraryVisible: false });

        this.fetchOrder();
      })
      .catch(error => displayErrors(error));
  };

  storeName = store => {
    const { name, platform } = store;
    let storeName = name;
    if (platform.name === "Shopify") {
      storeName = name.split('.')[0];
    }
    return storeName;
  };

  /**
   *
   * @param {{}|[]} prop
   * @returns {*[]}
   */
  propToArray = prop => {
    if (!Array.isArray(prop)) {
      prop = [prop];
    }
    return prop;
  };

  onSelectVariant = (productVariantId=null) => {
    const {id : orderId} = this.state.orderData
    if (orderId) {
      this.updateOrderLineItemMapping(productVariantId);
      return
    }
  }

  updateOrderLineItemMapping = (productVariantId) => {
    const {orderData:{id: orderId},orderLineItemId} = this.state

    axios.post(`/orders/${orderId}/line-items/${orderLineItemId}/variants`, {
      productVariantId,
    }).then(res => {
      this.setState({ isVariantMapperVisible: false }, this.fetchOrder)
    })
      .catch(error => displayErrors(error))

  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      confirmModal,
      isDeletingLineItem,
      isCancellingOrder,
      showEditLineItemModal,
      isLoadingDelete,
      isReleasingOrder,
      isHoldingOrder,
      isLoadingApply,
      isClearingError,
      isLoadingOrderDetails,
      isImageLibraryVisible,
      isCancelConfirmationVisible,
      orderData,
      uploadedImages,
      imageRequirements,
      isVariantMapperVisible,
      selectedVariant,
      orderLineItemId,
      showShippingAddressModal,
      isIgnoreConfirmationVisible,
      showEditAddressModal,
      isIgnoringVariants,
      selectedRowKeys,
      selectedCreateType,
      platformStoreProductVariantToIgnore
    } = this.state;

    const rowSelection = {
      selectedRowKeys,
      onChange: selectedRowKeys => {
        if (selectedRowKeys.length === this.state.orderData.lineItems.length) {
          this.setState({
            showDeleteOrderAlert: true
          })
        }

        this.setState({
          selectedRowKeys,
          showDeleteOrderAlert: false
        })
      }
    }

    let {
      platformOrderNumber,
      lineItems,
      shippingAddress = {},
      billingAddress = {},
      store = {},
      statusReadable,
      status,
      email,
      hasError,
      orderDeskId,
      id: orderId
    } = orderData;

    const storeId = store ? store.id : null;

    const {
      address1: b_address1,
      address2: b_address2,
      city: b_city,
      country: b_country,
      state: b_state,
      company: b_company,
      firstName: b_firstName,
      lastName: b_lastName,
      phone: b_phone,
      zip: b_zip
    } = billingAddress;

    const {
      address1: s_address1,
      address2: s_address2,
      city: s_city,
      country: s_country,
      state: s_state,
      company: s_company,
      firstName: s_firstName,
      lastName: s_lastName,
      phone: s_phone,
      zip: s_zip
    } = shippingAddress;

    if (isLoadingOrderDetails) return <Spin />;

    /**
     * If no order is found on /orders/{id} redirect to the orders view
     */
    if (!Object.keys(orderData).length) {
      return <Redirect to={"/orders"} />;
    }

    /**
     * TODO Laravel switches data types when singular or non singular
     * data, this should be removed once BE can find a solution to this.
     * FE should not be mutating any data from the server.
     */

      // let logs = this.propToArray(orderData.logs);
      // let payments = this.propToArray(orderData.payments);
      // let shipments = this.propToArray(orderData.shipments);

    let refund = this.sumTotalAmounts(orderData.payments, "refund");
    let total = this.sumTotalAmounts(orderData.payments, "totalCost");
    let newTotal = this.formatValueToUsCurrency(parseFloat(total.replace('$', '')) - parseFloat(refund.replace('$', '')));

    return (
      <>
        <Modal
          title="Cancel this order?"
          visible={isCancelConfirmationVisible}
          centered
          content={<p>Confirm Order Cancellation</p>}
          onCancel={() => this.setState({ isCancelConfirmationVisible: false })}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isCancelConfirmationVisible: false })
              }
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={this.onCancelOrder}
              loading={isCancellingOrder}
            >
              Cancel Order
            </Button>
          ]}
        >
          <p>Canceling Order #{platformOrderNumber}</p>
        </Modal>
        <Modal
          title="Delete this image?"
          visible={confirmModal}
          centered
          onOk={() => {}}
          onCancel={() => this.setState({ confirmModal: false })}
          content={<p>Confirm Image Delete</p>}
          footer={[
            <Button
              key={"cancel"}
              onClick={() => this.setState({ confirmModal: false })}
            >
              Cancel
            </Button>,
            <Button
              key={"delete"}
              type="danger"
              loading={isLoadingDelete}
              onClick={() => {
                this.setState({ confirmModal: false });
                this.onDeleteImageServer();
              }}
            >
              Delete
            </Button>
          ]}
        />

        <Modal
          title={`Ignore ${store && store.name ? store.name : "this"} variant?`}
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
          If this isn’t a product that you need fulfilled by teelaunch just
          press the ignore button below and we'll ignore it going forward.
        </Modal>
        <EditAddressModal
          editShippingAddress={this.editShippingAddress}
          visible={showEditAddressModal}
          orderData={orderData}
          onUpdateLogs={this.updateLogs}
          onCloseModal={() => this.setState({showEditAddressModal: false})} />
        <ImageLibrary
          visible={isImageLibraryVisible}
          onUploadImage={this.onUploadImage}
          onSelectedImage={this.onSelectedImage}
          selectedCreateType={selectedCreateType}
          uploadedImages={uploadedImages}
          imageRequirements={imageRequirements}
          imageTypes={this.state.imageTypes}
          onDeleteImage={imageId =>
            this.setState({ confirmModal: true, imageId: imageId })
          }
          handleCancel={() => this.setState({ isImageLibraryVisible: false })}
          isLoadingImages={this.state.isLoadingImages}
          getImages={this.getImages}
          currentPage={this.state.currentPage}
          total={this.state.total}
          pageSize={this.state.pageSize}
          uploaded={this.props.uploaded}
        />

        <VariantMapper
          title = "Link to teelaunch Variant"
          properties={['sku', 'optionValues', 'teelaunchPrice', 'retailPrice', 'profit']}
          onSelectVariant={(productVariantId)=>this.onSelectVariant(productVariantId)}
          visible={isVariantMapperVisible}
          handleCancel={() => this.setState({ isVariantMapperVisible: false })}
          isManualOrder={false}
        />
        <div style={{ marginTop: "40px" }}>
          <Row type="flex" justify="space-between">
            <Col
              xs={{ span: 24, offset: 0 }}
              sm={{ span: 24, offset: 0 }}
              md={{ span: 24, offset: 0 }}
              lg={{ span: 16, offset: 0 }}
              xxl={{ span: 16, offset: 0 }}
            >
              {hasError === 1 && (
                <div
                  style={{
                    display: "flex",
                    justifyContent: "center"
                  }}
                >
                  <Tag
                    color="red"
                    className="error-tag"
                    style={{ marginBottom: 16 }}
                  >
                    Resolve Errors then press Clear Error button to continue
                    processing
                  </Tag>
                </div>
              )}

              {orderData.status === ORDER_STATUS_OUT_OF_STOCK && (
                <div
                  style={{
                    display: "flex",
                    justifyContent: "center"
                  }}
                >
                  <Tag
                    color="red"
                    className="error-tag"
                    style={{ marginBottom: 16 }}
                  >
                    Switch Out Of Stock variants then press Release Order button to continue processing
                  </Tag>
                </div>
              )}

              <div style={{ float: "right" }}>
                <Tooltip title="Previous Order">
                  <Button
                    type="primary"
                    style={{
                      borderRadius: "5px",
                      height: "30px",
                      padding: "0 10px",
                      margin: "0 4px"
                    }}
                    ghost
                    disabled={!orderData.previousOrderId}
                    onClick={() =>
                      this.paginateOrder(orderData.previousOrderId)
                    }
                  >
                    <Icon type="left" />
                  </Button>
                </Tooltip>
                <Tooltip title="Next Order">
                  <Button
                    type="primary"
                    style={{
                      borderRadius: "5px",
                      height: "30px",
                      padding: "0 10px",
                      margin: "0 4px"
                    }}
                    ghost
                    disabled={!orderData.nextOrderId}
                    onClick={() => this.paginateOrder(orderData.nextOrderId)}
                  >
                    <Icon type="right" />
                  </Button>
                </Tooltip>
              </div>

              <Title level={4}>
                {store && store.name && (
                  <div style={{ flexGrow: 1 }}>
                    <PlatformLogo platform={store.platform || {}} />{" "}
                    <span>{store && this.storeName(store)}</span>
                  </div>
                )}
              </Title>

              <div
                style={{
                  display: "flex",
                  justifyContent: "center",
                  alignItems: "center"
                }}
              >
                <Title level={2} style={{ flexGrow: 1 }}>
                  Order #{platformOrderNumber}
                </Title>

                <div style={{ textAlign: "right" }}>
                  {orderDeskId && (
                    <div style={{ fontSize: 12 }}>Ref # {orderDeskId}</div>
                  )}
                  {hasError === 1 && (
                    <Tag
                      color="red"
                      className="error-tag"
                      style={{ marginRight: 8 }}
                    >
                      Error
                    </Tag>
                  )}
                  <OrderStatusTag
                    status={status}
                    statusReadable={statusReadable}
                    style={{ fontSize: "1em", marginRight: 0 }}
                  />
                </div>
              </div>
              <Title level={4}>
                {email && <div style={{ flexGrow: 1 }}>Email: {email}</div>}
              </Title>

              <div
                style={{
                  display: "flex",
                  justifyContent: "flex-end"
                }}
                className="button-group"
              >
                {
                  (this.state.showApplyBtn && <Button onClick={e => this.updateOrderLineItems(e)} loading={isLoadingApply}>Apply</Button>)
                }
                {(!!selectedRowKeys.length
                  && (statusReadable === "HOLD"
                    || statusReadable === "PENDING"))
                && (
                  <Popconfirm title={this.state.showDeleteOrderAlert ? 'Deleting this item will result in the deletion of the order, proceed?' : 'Are you sure?'}
                              onConfirm={() => this.onDeleteLineItems()}>
                    <Button
                      onClick={e => {
                        e.preventDefault();
                        console.log(`selectedRowKeys: ${selectedRowKeys.length}\nLineItems: ${orderData.lineItems.length}`);
                        if (selectedRowKeys.length === orderData.lineItems.length) {
                          this.setState({showDeleteOrderAlert: true});
                        }
                      }}
                      loading={isDeletingLineItem}>
                      Delete Item{selectedRowKeys.length > 1 && 's'}
                    </Button>
                  </Popconfirm>
                )
                }

                {hasError === 1 && (
                  <Button
                    onClick={() => this.clearError()}
                    loading={isClearingError}
                  >
                    Clear Error
                  </Button>
                )}

                {(statusReadable === "HOLD" ||
                  statusReadable === "OUT_OF_STOCK") && (
                  <Button
                    onClick={() => this.releaseOrder()}
                    loading={isReleasingOrder}
                  >
                    Release Order
                  </Button>
                )}

                {statusReadable === "PENDING" && (
                  <Button
                    onClick={() => this.holdOrder()}
                    loading={isHoldingOrder}
                  >
                    Hold Order
                  </Button>
                )}

                {(statusReadable === "HOLD" ||
                  statusReadable === "PENDING" || statusReadable === "OUT_OF_STOCK") && (
                  <Button
                    type="danger"
                    ghost
                    onClick={() =>
                      this.setState({ isCancelConfirmationVisible: true })
                    }
                  >
                    Cancel Order
                  </Button>
                )}
              </div>

              <Table
                columns={this.columns}
                dataSource={lineItems}
                rowSelection={status < ORDER_STATUS_PAID ? rowSelection : null}
                size="middle"
                rowKey={"id"}
                pagination={false}
                className={"overflow-table"}
              />

              <div style={this.styles.tableFooter}>
                {orderData &&
                orderData.payments &&
                orderData.payments.length ? (
                  <>
                    <Paragraph>
                      Sub Total:{" "}
                      {this.sumTotalAmounts(orderData.payments, "lineItemSubtotal")}
                    </Paragraph>
                    <Paragraph>
                      Discount:{" "}
                      {this.sumTotalAmounts(orderData.payments, "discount")}
                    </Paragraph>
                    <Paragraph>
                      Shipping:{" "}
                      {this.sumTotalAmounts(orderData.payments, "shippingSubtotal")}
                    </Paragraph>
                    <Paragraph>
                      Tax: {this.sumTotalAmounts(orderData.payments, "tax")}
                    </Paragraph>
                    {refund != '$0.00' ?
                      <Paragraph style={this.styles.refundText}>
                        Refund: -{refund}
                      </Paragraph>
                      :
                      null
                    }
                    <Paragraph style={this.styles.totalText}>
                      Total:{" "}
                      {newTotal}
                    </Paragraph>
                  </>
                ) : null}
              </div>
            </Col>
            <Col
              xs={{ span: 24, offset: 0 }}
              sm={{ span: 24, offset: 0 }}
              md={{ span: 24, offset: 0 }}
              lg={{ span: 7, offset: 1 }}
              xxl={{ span: 7, offset: 1 }}
            >
              <div style={{ margin: "50px 0" }}>
                <div>
                  <Title level={3}>Billing Address</Title>
                </div>
                <div style={this.styles.sideInfo}>
                  <Paragraph>{b_company}</Paragraph>
                  <Paragraph>
                    {b_firstName} {b_lastName}
                  </Paragraph>
                  <Paragraph>{b_address1}</Paragraph>
                  <Paragraph>{b_address2}</Paragraph>
                  <Paragraph>
                    {b_city}, {b_state} {b_zip}
                  </Paragraph>
                  <Paragraph>{b_country}</Paragraph>
                  <Paragraph>{b_phone}</Paragraph>
                </div>
              </div>
              <div style={{ margin: "50px 0" }}>
                <div style={status < ORDER_STATUS_PAID ? this.styles.addressHeader : null}>
                  <Title level={3}>Shipping Address</Title>
                  {status < ORDER_STATUS_PAID && <Button onClick={e => {
                    e.preventDefault();
                    this.setState({showEditAddressModal: true})
                  }}>Edit Address</Button>}
                </div>
                <div style={this.styles.sideInfo}>
                  <Paragraph>{s_company}</Paragraph>
                  <Paragraph>
                    {s_firstName} {s_lastName}
                  </Paragraph>
                  <Paragraph>{s_address1}</Paragraph>
                  <Paragraph>{s_address2}</Paragraph>
                  <Paragraph>
                    {s_city}, {s_state} {s_zip}
                  </Paragraph>
                  <Paragraph>{s_country}</Paragraph>
                  <Paragraph>{s_phone}</Paragraph>
                </div>
              </div>
              <div style={{ margin: "50px 0" }}>
                <Title level={3}>Shipments</Title>
                {orderData &&
                orderData.shipments &&
                orderData.shipments.length ? (
                  <div style={this.styles.sideInfo}>
                    {orderData.shipments.map((tracking, val) => (
                      <div
                        key={tracking.id}
                        style={val !== 0 ? { marginTop: "30px" } : {}}
                      >
                        <Paragraph>
                          Tracking:{" "}
                          {tracking.trackingUrl ? (
                            <a href={tracking.trackingUrl} target="_blank">
                              {tracking.trackingNumber}
                            </a>
                          ) : (
                            tracking.trackingNumber
                          )}
                        </Paragraph>

                        <Paragraph>
                          Carrier: {this.trackingMethodDisplay(tracking)}
                        </Paragraph>
                        <Paragraph>
                          Ship Date:{" "}
                          {new Date(tracking.shippedAt).toLocaleString([], {
                            year: "numeric",
                            month: "short",
                            day: "numeric"
                          })}
                        </Paragraph>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div>No shipping information available</div>
                )}
              </div>
            </Col>
          </Row>
          <Row>
            <Col>
              <Title level={3}>Order History</Title>
              <Timeline>
                {orderData && orderData.logs && orderData.logs.length
                  ? orderData.logs.map((status, index) => (
                    <Timeline.Item
                      key={status.id}
                      color={this.formatStatusCodeColor(status.messageType)}
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
        </div>
      </>
    );
  }
}
