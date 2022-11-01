import React, { Component } from 'react';
import {
  Avatar,
  Table,
  Col,
  Row,
  Button,
  Spin,
  Modal,
  Input,
  Pagination,
  Typography,
  message,
  Timeline,
} from 'antd';
import StoreSelect from '../../Components/Modals/StoreSelect';

const { Title } = Typography;
import axios from 'axios';
import { Link } from 'react-router-dom';

import { displayErrors } from '../../../utils/errorHandler';
import qs from 'qs';
import BatchActions from '../../Components/Buttons/BatchActions';
import ForceSyncProductsModal from "../../Components/Modals/ForceSyncProductsModal";

const { Search } = Input;

/**
 * TODO this file needs more refactoring.
 *
 */
export default class UserProducts extends Component {
  /**
   * @param props
   */
  constructor(props) {
    super(props);

    this.state = {
      products: [],
      isVisible: false,
      isLoadingProducts: false,
      selectedRowKeys: [],
      forceSelectedRowKeys: [],
      selectedStores: [],
      previouslySyncedProducts: [],
      previouslySyncedProductsData: [],
      storeCollection: [],
      isDeleteConfirmationVisible: false,
      isForceSyncConfirmationVisible: false,
      currentPage: 0,
      total: 0,
      pageSize: 0,
      query:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .query || null,
      page:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .page || 1,
      queryDisplay:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .query || '',
      productTimeoutTime: Date.now() - 60000 * 60 * 2,
    };
  }

  componentDidMount() {
    this.setPage(this.state.page, this.state.query);
    this.getStoreCollection();
  }

  componentDidUpdate(prevProps, prevState, snapshot) {
    const page =
      qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).page ||
      1;
    const query =
      qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query ||
      '';
    if (Number(this.state.page) !== Number(page)) {
      this.setState({ page: parseInt(page), query: query }, () =>
        this.fetchProducts(page, query),
      );
    }
  }

  getProducts = (page = null, query = null) => {
    if (page) {
      this.setState({ page: page });
    } else {
      page = this.state.page;
    }

    if (query !== null) {
      this.setState({ query: query });
    } else {
      query = this.state.query;
    }

    this.setPage(page, query);
  };

  setPage = (page, query) => {
    let newUrl = `my-products?page=${page}`;
    if (query) {
      newUrl += `&query=${query}`;
    }
    this.props.history.push(newUrl);

    this.fetchProducts(page, query);
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
   * Get paginated products
   * @param {int|null} page
   * @param {string|null} query
   */
  fetchProducts = (page = null, query = null) => {
    this.setState({ isLoadingProducts: true });

    axios
      .get(`/products/list`, {
        params: {
          page: page,
          name: query,
        },
      })
      .then(res => {
        const { data, meta } = res.data
        if (data) {
          this.setState({
            products: data,
            currentPage: meta.current_page,
            total: meta.total,
            pageSize: meta.per_page,
          });
        }
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingProducts: false }));
  };

  /**
   * @param {[]} selectedRowKeys
   */
  onSelectChange(selectedRowKeys) {
    this.setState({ selectedRowKeys });
  }

  /**
   * @param {[]} forceSelectedRowKeys
   */
  onForceSelectChange(forceSelectedRowKeys) {
    this.setState({ forceSelectedRowKeys });
  }

  /**
   *
   */
  deleteSelectedProducts() {
    this.setState({
      isDeleteConfirmationVisible: true,
    });
  }

  holdSelectedProductsOrders() {
    axios
      .post(`/products/${this.state.selectedRowKeys.join()}/orders-hold`)
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;
          if (data) {
            this.setState({
              selectedRowKeys: [],
            });
            message.success("Upcoming Products Orders will be on hold");
          }
        }
      })
      .catch(error => {
        displayErrors(error);
      });
  }

  releaseSelectedProductsOrders() {
    axios
      .post(`/products/${this.state.selectedRowKeys.join()}/orders-release`)
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;
          if (data) {
            this.setState({
              selectedRowKeys: [],
            });
            message.success("Upcoming Products Orders will be released");
          }
        }
      })
      .catch(error => {
        displayErrors(error);
      });
  }

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
        selectedStores: newSelectedStores,
      };
    });
  };

  /**
   *
   */
  sendSelectedProductsToIntegration = (forceSyncProducts) => {
    const {
      selectedStores,
      selectedRowKeys,
      forceSelectedRowKeys,
      isForceSyncConfirmationVisible,
    } = this.state;

    if(forceSyncProducts){
      this.state.selectedRowKeys = [...selectedRowKeys, ...forceSelectedRowKeys];
    }

    if(isForceSyncConfirmationVisible){
      this.setState({
        isForceSyncConfirmationVisible: false
      })
    }

    const selectedProducts = this.state.selectedRowKeys;

    axios
      .post('/platform-products/all', {
        selectedStores,
        selectedProducts,
        forceSyncProducts
      })
      .then(({ data }) => {
        this.setState({
          isVisible: false,
        });

        if (data.previouslySynced) {
          this.setState({
              previouslySyncedProducts: data.previouslySyncedProducts,
              selectedRowKeys: data.productsToSync
          },
            () => this.displayInfo(),
          );
        }
        else{
          this.setState({
              previouslySyncedProducts: [],
              previouslySyncedProductsData: [],
              isForceSyncConfirmationVisible: false,
              forceSelectedRowKeys: []
            },
            () => this.displayInfo(),
          );

          this.setState({
            selectedRowKeys: []
          });

          this.props.history.push('/my-products');
        }

      })
      .catch(error => displayErrors(error));
  };

  displayInfo() {
    const {
      selectedStores: selectedStores,
      selectedRowKeys: selectedProducts,
      previouslySyncedProducts,
      storeCollection,
      products,
    } = this.state;

    if(Object.keys(previouslySyncedProducts).length){
      const previouslySyncedProductsData = [];

      products.map(product => {
        if(Object.keys(previouslySyncedProducts).includes(String(product.id))){
          const productStoreNames = [];
          previouslySyncedProducts[product.id].map( productStores => {
            let productStoreId = productStores.platform_store_id
            storeCollection.map(platform => {

              if( platform.name === 'Rutter'){
                Object.keys(platform.stores).length &&
                  Object.keys(platform.stores).map((store) => {
                    platform.stores[store].map((storeDetails) => {
                    if(storeDetails.id === productStoreId){
                      productStoreNames.push(storeDetails.name);
                    }
                  })
                })
              }
              else{
                platform.stores.map( storeDetails => {
                  if(storeDetails.id === productStoreId){
                    productStoreNames.push(storeDetails.name);
                  }
                })
              }



            })
          })

          product.store = productStoreNames.join(", ");
          previouslySyncedProductsData.push(product);
        }
      });

      this.setState({
        isForceSyncConfirmationVisible: true,
        previouslySyncedProductsData: previouslySyncedProductsData
      })

    }
    else{
      message.success(
        `The product${
          selectedProducts.length > 1 ? 's' : ''
        } will be available in your store${
          selectedStores.length > 1 ? 's' : ''
        } after a few minutes`,
      );
    }
  }

  /**
   * TODO Modal should have a loader on the delete and the modal
   * should also stay open.
   *
   */
  onConfirmDeleteProduct = () => {
    axios
      .delete(`/products/${this.state.selectedRowKeys.join()}`)
      .then(res => {
        const { data, meta } = res.data;
        if (data) {
          this.setState({
            products: data,
            selectedRowKeys: [],
            total: meta.total,
          });
          message.success('Product deleted')
        }
      })
      .catch(error => displayErrors(error));

    this.setState({
      isDeleteConfirmationVisible: false,
    });
  };

  onSearchProducts = query => {
    this.getProducts(1, query);
  };

  /**
   * On Cancel Modal
   */
  handleCancel = () => {
    this.setState({
      isVisible: false,
      selectedRowKeys: [],
    });
  };

  /**
   * On Cancel Force Sync Modal
   */
  handleForceSyncCancel = () => {
    this.setState({
      isForceSyncConfirmationVisible: false,
      forceSelectedRowKeys: [],
    });
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      previouslySyncedProductsData,
      selectedRowKeys,
      forceSelectedRowKeys,
      isDeleteConfirmationVisible,
      isForceSyncConfirmationVisible,
      isLoadingProducts,
      currentPage,
      total,
      isVisible,
      pageSize,
      productTimeoutTime,
      products
    } = this.state;

    const columns = [
      {
        title: '',
        width: 72,
        dataIndex: 'mainImageThumbUrl',
        key: 'mainImageThumbUrl',
        render: (value, record) => {
          const createdAt = Date.parse(record.createdAt);
          return (
            // Leave for now in case side effects of changes.
            // <Link to={`/my-products/${record.id}`}>
            //   {value ? (
            //     <Avatar
            //       style={{ background: 'none' }}
            //       shape="square"
            //       size={64}
            //       icon={<img src={record.mainImageThumbUrl} />}
            //     />
            //   ) : createdAt < productTimeoutTime ? (
            //     <Avatar
            //       style={{ background: 'none' }}
            //       shape="square"
            //       size={64}
            //       icon=""
            //     />
            //   ) : record.artFiles.length >= 1 || record.mockupFiles.length >= 1 || record.printFiles.length >=1 ?
            //     (
            //     <Spin style={{ position: 'relative', top: '-2px' }} />
            //   ) :
            //     <Avatar
            //       style={{ background: 'none' }}
            //       shape="square"
            //       size={64}
            //       icon={<img src={record.variants[0].blankVariant.thumbnail} />}
            //     />
            //   }
            // </Link>
            <Link to={`/my-products/${record.id}`}>
              {value ? (
                <Avatar
                  style={{ background: 'none' }}
                  shape="square"
                  size={64}
                  icon={<img src={record.mainImageThumbUrl !== null ? record.mainImageThumbUrl : record.image} />}
                />
              ) : createdAt < productTimeoutTime ? (
                <Avatar
                  style={{ background: 'none' }}
                  shape="square"
                  size={64}
                  icon=""
                />
              ) : record.category == 10 ? (
                  <Avatar
                    style={{ background: 'none' }}
                    shape="square"
                    size={64}
                    icon={<img src={record.image} />}
                  />
                )

                :
                <Spin style={{ position: 'relative', top: '-2px' }} />
              }
            </Link>
          );
        },
      },
      {
        title: 'Title',
        dataIndex: 'name',
        key: 'name',
      },
      {
        dataIndex: '',
        key: 'sendToIntegration',
        render: (value, record) => {
          return (
            <Button
              type=""
              onClick={e => {
                e.stopPropagation();
                const selectedRowKeys = [record.id];
                this.setState({ selectedRowKeys });
                this.setState({ isVisible: true });
              }}>
              Send to Store
            </Button>
          );
        },
      },
    ];

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this),
    };

    const forceRowSelection = {
      forceSelectedRowKeys,
      onChange: this.onForceSelectChange.bind(this),
    };

    const hasSelected = selectedRowKeys.length > 0;

    return (
      <>
        <Modal
          title="Delete selected products"
          visible={isDeleteConfirmationVisible}
          centered
          content={<p>Confirm Product Delete</p>}
          onCancel={() => this.setState({ isDeleteConfirmationVisible: false })}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isDeleteConfirmationVisible: false })
              }>
              Cancel
            </Button>,
            <Button key={2} type="danger" onClick={this.onConfirmDeleteProduct}>
              Delete
            </Button>,
          ]}>
          {selectedRowKeys.length} products will be permanently deleted. You
          cannot undo this action.
        </Modal>
        <Row gutter={{ xs: 8, md: 24, lg: 32 }}>
          <Col xs={24} md={24}>
            <Row gutter={16}>
              <Row>
                <Col sm={24} md={12}>
                  <Title>My Products</Title>
                </Col>
                <Col sm={24} md={12}>
                  <Search
                    placeholder="Search products"
                    value={this.state.queryDisplay}
                    onChange={event =>
                      this.setState({ queryDisplay: event.target.value })
                    }
                    onSearch={value => this.onSearchProducts(value)}
                    style={{ marginBottom: '16px' }}
                    enterButton
                  />
                </Col>
              </Row>

              <div>Total: {total}</div>
              <BatchActions
                hasSelected={hasSelected}
                selectedRowKeys={selectedRowKeys}>
                <Button
                  type="primary"
                  style={{ display: hasSelected ? 'initial' : 'none' }}
                  onClick={this.deleteSelectedProducts.bind(this)}>
                  Delete
                </Button>
                <Button
                  type="primary"
                  style={{ display: hasSelected ? "initial" : "none" }}
                  onClick={this.holdSelectedProductsOrders.bind(this)}
                >
                  Hold Orders
                </Button>
                <Button
                  type="primary"
                  style={{ display: hasSelected ? "initial" : "none" }}
                  onClick={this.releaseSelectedProductsOrders.bind(this)}
                >
                  Release Orders
                </Button>
                <Button
                  type="primary"
                  style={{ display: hasSelected ? 'initial' : 'none' }}
                  onClick={() => this.setState({ isVisible: true })}>
                  Send to Store
                </Button>
              </BatchActions>

              <Table
                rowKey="id"
                columns={columns}
                loading={isLoadingProducts}
                dataSource={this.state.products}
                rowSelection={rowSelection}
                pagination={false}
                onRow={(record, index) => ({
                  onClick: () => {
                    this.props.history.push(`/my-products/${record.id}`, [
                      record,
                    ]);
                  },
                })}
              />
              <Pagination
                onChange={page => this.getProducts(page)}
                total={total}
                current={currentPage}
                pageSize={pageSize}
              />
            </Row>
          </Col>
        </Row>
        <StoreSelect
          visible={isVisible}
          onCancel={this.handleCancel}
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
      </>
    );
  }
}
