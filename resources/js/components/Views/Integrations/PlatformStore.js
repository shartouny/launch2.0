import React, { Component } from 'react';
import {
  Avatar,
  Table,
  Col,
  Row,
  Button,
  Spin,
  Modal,
  Typography,
  Pagination,
  Input,
  Menu,
  Dropdown,
  Icon,
  Tag,
  message,
  Timeline,
} from 'antd';
import axios from 'axios';
import { Link } from 'react-router-dom';

const { Search } = Input;
const {
  Title
} = Typography;

import { displayErrors } from "../../../utils/errorHandler";
import qs from "qs";
import BatchActions from "../../Components/Buttons/BatchActions";

/**
 * TODO this file needs more refactoring.
 *
 */
export default class PlatformStore extends Component {
  /**
   *
   * @param props
   */
  constructor(props) {
    super(props);

    this.state = {
      storeId: props.match.params.id,
      store: {},
      products: [],
      isLoadingProducts: false,
      isReSyncingProducts: false,
      selectedRowKeys: [],
      isDeleteConfirmationVisible: false,
      isRemoveConfirmationVisible: false,
      totalPages: 1,
      currentPage: 1,
      pageSize: 1,
      query: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query || null,
      page: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).page || 1,
      queryDisplay: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query || '',
      total: 0
    };
  };

  componentDidMount() {
    this.setPage(this.state.page, this.state.query);
  };

  componentDidUpdate(prevProps, prevState, snapshot) {
    const page = qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).page || 1;
    const query = qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query || '';
    if (Number(this.state.page) !== Number(page)) {
      this.setState({ page: parseInt(page), query: query }, () => this.fetchProducts(page, query));
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
    const { storeId } = this.state;
    let newUrl = `products?page=${page}`;
    if (query) {
      newUrl += `&query=${query}`;
    }
    this.props.history.push(newUrl);

    this.fetchProducts(page, query);
  };

  /**
   *
   * @param {int|null} page
   * @param {string|null} query
   */
  fetchProducts = (page = null, query = null) => {

    this.setState({ isLoadingProducts: true });
    const { storeId } = this.state;

    axios.get(`/stores/${storeId}`).then(res => {
      const { data } = res.data;
      if (data) {
        this.setState({
          store: data,
        });
      }
    }).then(() => {
      axios.get(`/stores/${storeId}/products`, {
        params: {
          page: page,
          title: query
        }
      })
        .then(res => {
          const { data, meta } = res.data;
          if (data) {
            this.setState({
              products: data,
              currentPage: meta.current_page,
              totalPages: meta.total, // Total pages or products?
              pageSize: meta.per_page,
              total: meta.total
            });
          }
        }).finally(() => this.setState({ isLoadingProducts: false }))
    }).catch(error => displayErrors(error));
  };

  /**
   *
   * @param {[]} selectedRowKeys
   */
  onSelectChange(selectedRowKeys) {
    this.setState({ selectedRowKeys });
  };

  /**
   *
   */
  deleteSelectedProducts() {
    this.setState({
      isDeleteConfirmationVisible: true,
    });
  };

  /**
   * TODO Modal should have a loader on the delete and the modal
   * should also stay open.
   *
   */
  onConfirmDeleteProduct = () => {
    const {
      storeId,
      page,
      query
    } = this.state;

    axios.delete(`/stores/${storeId}/products/${this.state.selectedRowKeys.join()}`)
      .then(res => {
        const { data } = res.data;
        if (data) {
          this.setState({
            selectedRowKeys: []
          });
          this.fetchProducts(page, query);
        }
      })
      .catch(error => displayErrors(error));

    this.setState({
      isDeleteConfirmationVisible: false,
    });
  };

  onSearchProducts = (query) => {
    this.getProducts(1, query);
  };

  showRemoveModal = () => {
    this.setState({
      isRemoveConfirmationVisible: true,
    });
  };

  onConfirmRemoveIntegration = () => {
    const { storeId } = this.state;
    axios.delete(`/stores/${storeId}`)
      .then(() => this.props.history.push('/integrations'))
      .catch(error => displayErrors(error));
  };

  onIgnoreProducts = (event = null, productId = null) => {
    if (event) {
      event.stopPropagation();
    }

    const { selectedRowKeys, storeId } = this.state;
    let productIds = [];
    if (productId) {
      productIds.push(productId);
    } else {
      productIds = selectedRowKeys;
    }

    axios
      .post(
        `/stores/${storeId}/products/${productIds.join()}/ignore`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: [],
          isUnlinkConfirmationVisible: true
        });
        this.getProducts();
        message.success("Product variants ignored");
      })
      .catch(error => displayErrors(error))
  };

  onReSyncProducts = (event = null) => {
    if (event) {
      event.stopPropagation();
    }

    this.setState({
      isReSyncingProducts: true
    });

    const { storeId } = this.state;

    axios
      .post(
        `/stores/${storeId}/products/resync`
      )
      .then(res => {
        this.setState({
          isReSyncingProducts: false
        });
        message.success("Products re-sync will start after a few minutes.");
      })
      .catch(error => {
        this.setState({
          isReSyncingProducts: false
        });
        displayErrors(error)
      })
  };

  onUnignoreProducts = (event = null, productId = null) => {

    if (event) {
      event.stopPropagation();
    }

    const { selectedRowKeys, storeId } = this.state;
    let productIds = [];
    if (productId) {
      productIds.push(productId);
    } else {
      productIds = selectedRowKeys;
    }
    axios
      .post(
        `/stores/${storeId}/products/${productIds.join()}/unignore`
      )
      .then(res => {
        this.setState({
          selectedRowKeys: [],
          isUnlinkConfirmationVisible: false
        });
        this.getProducts();
        message.success("Products unignored");
      })
      .catch(error => displayErrors(error));
  };

  ignoreSelectedProducts() {
    this.onIgnoreProducts();
  }

  unignoreSelectedProducts() {
    this.onUnignoreProducts();
  }

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      selectedRowKeys,
      isDeleteConfirmationVisible,
      isRemoveConfirmationVisible,
      isLoadingProducts,
      storeId,
      store,
      products,
      totalPages,
      currentPage,
      pageSize,
      total,
      isReSyncingProducts
    } = this.state;

    const columns = [
      {
        title: '',
        width: '',
        dataIndex: 'image',
        key: 'image',
        render: (value, record) =>
          <Avatar
            style={{ background: 'none' }}
            shape="square"
            size={64}
            icon={<img src={value}/>}/>
      },
      {
        title: 'Title',
        dataIndex: 'title',
        key: 'title',
      },
      {
        title: 'Variants',
        dataIndex: 'variants.length',
        key: 'variantCount',
      },
      {
        title: "",
        dataIndex: "is_ignored",
        key: "is_ignored",
        render: (value, record) =>
          record.isIgnored ? (
            <>
              <Tag style={{ marginRight: 16 }}>Ignored</Tag>
              <Button
                type="dashed"
                size="small"
                onClick={(event) => this.onUnignoreProducts(event, record.id)}
              >
                Unignore
              </Button>
            </>
          ) : (
            <div className="button-group">
              <Button
                onClick={(event) => this.onIgnoreProducts(event, record.id)}
              >
                Not teelaunch
              </Button>
            </div>
          )
      }
    ];

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this),
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
              onClick={() => this.setState({ isDeleteConfirmationVisible: false })}
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={this.onConfirmDeleteProduct}
            >
              Delete
            </Button>,
          ]}
        >
          {selectedRowKeys.length} products will be permanently deleted. You cannot undo this action.
        </Modal>

        <Modal
          title="Remove this store"
          visible={isRemoveConfirmationVisible}
          centered
          content={<p>Confirm Removal of Store</p>}
          onCancel={() => this.setState({ isRemoveConfirmationVisible: false })}
          footer={[
            <Button
              key={1}
              onClick={() => this.setState({ isRemoveConfirmationVisible: false })}
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={this.onConfirmRemoveIntegration}
            >
              Confirm
            </Button>,
          ]}
        >
          All orders from this store will fail to process. You cannot undo this action.
        </Modal>

        <Row gutter={{ xs: 8, md: 24, lg: 32 }}>
          <Col xs={24} md={24}>

            <Row gutter={16}>
              <Col span={24}>

              </Col>
            </Row>

            <Row gutter={16}>
              <Col xs={24} md={12}>
                <Title>Platform Products</Title>
                {store.name && (
                  <Title level={4}>{store.platform.name !== 'Rutter' ? store.platform.name : store.platformType }: {store.name}</Title>
                )}
              </Col>
              <Col xs={24} md={12} style={{ textAlign: 'right', justifyContent: "flex-end" }}>
                <Button onClick={this.onReSyncProducts}>
                  <Spin spinning={isReSyncingProducts}>Re-sync Products</Spin>
                </Button>
                {/*<Button type="danger">Remove Store</Button>*/}

                {/*<Button>*/}
                {/*  <Dropdown trigger={['click']}*/}
                {/*            overlay={<Menu>*/}
                {/*              <Menu.Item onClick={this.showRemoveModal}>*/}
                {/*                <span>*/}
                {/*                  Remove Store*/}
                {/*                </span>*/}
                {/*              </Menu.Item>*/}
                {/*            </Menu>}>*/}
                {/*    <a className="ant-dropdown-link" onClick={e => e.preventDefault()}>*/}
                {/*      Actions <Icon type="down"/>*/}
                {/*    </a>*/}
                {/*  </Dropdown>*/}
                {/*</Button>*/}

                <div style={{ marginBottom: 16 }}/>
                <Search placeholder="Search products"
                        value={this.state.queryDisplay}
                        onChange={(event) => this.setState({ queryDisplay: event.target.value })}
                        onSearch={value => this.onSearchProducts(value)}
                        style={{ marginBottom: '16px' }}
                        enterButton/>
              </Col>
            </Row>

            <div>
              Total: {total}
            </div>

            <Row gutter={16}>
              <Col xs={24} md={24}>
                <BatchActions hasSelected={hasSelected} selectedRowKeys={selectedRowKeys}>
                  <Button
                    style={{ display: hasSelected ? 'initial' : 'none' }}
                    onClick={this.ignoreSelectedProducts.bind(this)}
                  >
                    Not teelaunch
                  </Button>
                  <Button
                    style={{ display: hasSelected ? 'initial' : 'none' }}
                    onClick={this.unignoreSelectedProducts.bind(this)}
                  >
                    Unignore
                  </Button>
                  {store.platform?.name === 'Launch' &&
                    <Button
                      style={{ display: hasSelected ? 'initial' : 'none' }}
                      onClick={this.deleteSelectedProducts.bind(this)}
                      >
                      Delete
                    </Button>
                  }
                </BatchActions>
                <Table
                  rowKey="id"
                  columns={columns}
                  loading={isLoadingProducts}
                  dataSource={products}
                  rowSelection={rowSelection}
                  pagination={false}
                  onRow={(record, index) => ({
                    onClick: () => this.props.history.push(`/integrations/${storeId}/products/${record.id}`, [record])
                  })}
                />
                <Pagination
                  onChange={page => this.getProducts(page)}
                  total={totalPages}
                  current={currentPage}
                  pageSize={pageSize}
                />
              </Col>
            </Row>
          </Col>
        </Row>
      </>
    );
  }
}
