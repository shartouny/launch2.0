import React, {Component} from "react";
import axios from "axios";
import {ORDER_STATUSES} from "../../../constants/OrderStatus";

import {
  Row,
  Col,
  Table,
  Pagination,
  Input,
  Tag,
  Button,
  Modal,
  Typography,
  Select
} from "antd";

const {Title} = Typography;
import {displayErrors} from "../../../utils/errorHandler";
import OrderStatusTag from "../../Components/Tags/OrderStatusTag";

const {Search} = Input;

import qs from "qs";
import PlatformLogo from "../../Components/Tags/PlatformLogo";
import BatchActions from "../../Components/Buttons/BatchActions";

/**
 * TODO this file needs a bit more refactoring.
 *
 */
export default class Orders extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      orders: [],
      query:
        qs.parse(this.props.location.search, {ignoreQueryPrefix: true})
          .query || null,
      status:
        qs.parse(this.props.location.search, {ignoreQueryPrefix: true})
          .status || "all",
      view:
        qs.parse(this.props.location.search, {ignoreQueryPrefix: true})
          .view || 'normal',
      page:
        qs.parse(this.props.location.search, {ignoreQueryPrefix: true})
          .page || 1,
      queryDisplay:
        qs.parse(this.props.location.search, {ignoreQueryPrefix: true})
          .query || "",
      pageSize: 0,
      current: 0,
      total: 0,
      path: "",
      isLoadingOrders: false,
      selectedRowKeys: [],
      isDeleteConfirmationVisible: false,
      isCancelConfirmationVisible: false,
      orderStatuses: ORDER_STATUSES,
    };

    this.columns = [
      {
        title: "Platform",
        dataIndex: "store",
        key: "store",
        render: (value, record) => {
          if (!value) {
            return;
          }

          return (
            <>
              <PlatformLogo platform={value.platform || {}} platformType={value.platformType}/>{" "}
              {value && this.storeName(value)}
            </>
          );
        }
      },
      {
        title: "Order",
        dataIndex: "platformOrderNumber",
        key: "platformOrderNumber"
      },
      {
        title: "Customer",
        dataIndex: "email",
        key: "email",
        render: (value, record) => {
          const {shippingAddress} = record;
          if (shippingAddress) {
            return shippingAddress['first_name'] + " " + `${shippingAddress['last_name'] ?? ''}`;
          }
          return value;
        }
      },
      {
        title: "Status",
        dataIndex: "statusReadable",
        key: "statusReadable",
        width: 140,
        render: (value, record) => {
          return (
            <OrderStatusTag status={record.statusId} statusReadable={value}/>
          );
        }
      },
      {
        title: "",
        dataIndex: "",
        key: "error",
        width: 92,
        render: (value, record) => {
          return (
            record.hasError === 1 && (
              <Tag color="red" className="error-tag">
                Error
              </Tag>
            )
          );
        }
      },
      {
        title: "Total",
        dataIndex: "total",
        key: "total",
        render: (value, record) => {
          let total = this.formatValueToUsCurrency(record.lineItemCost)
          return record.ship ? total + " + Ship" : total
        }
      },
      {
        title: "Date",
        dataIndex: "createdAtFormatted",
        key: "createdAtFormatted"
      }
    ];
  }

  /**
   *
   */
  componentDidMount() {
    this.getOrders(this.state.page, this.state.query, this.state.status, this.state.view);
  }

  componentDidUpdate(prevProps, prevState, snapshot) {
    const page =
      qs.parse(this.props.location.search, {ignoreQueryPrefix: true}).page ||
      1;
    const query =
      qs.parse(this.props.location.search, {ignoreQueryPrefix: true}).query ||
      "";

    if (Number(this.state.page) !== Number(page)) {

      //console.log('%c componentDidUpdate', 'color:white; background: black');
      this.setState({page: parseInt(page), query: query}, () =>
        this.fetchOrders(page, query)
      );
    }
  }

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
   * @param {int|null} page
   * @param {string|null} query
   */
  getOrders = (page = null, query = null, status = null, view = null) => {
    if (page) {
      this.setState({page: parseInt(page)});
    } else {
      page = this.state.page;
    }

    if (query !== null) {
      this.setState({query: query});
    } else {
      query = this.state.query;
    }

    if (status !== null) {
      this.setState({status: status});
    } else {
      status = this.state.status;
    }

    if (view !== null) {
      this.setState({view: view});
    } else {
      view = this.state.view;
    }

    this.setPage(page, query, status, view);
  };

  setPage = (page, query, status, view) => {
    let newUrl = `orders?page=${page}`;
    if (query) {
      newUrl += `&query=${query}`;
    }
    if (status !== null) {
      newUrl += `&status=${status}`;
    }
    if (view !== null) {
      newUrl += `&view=${view}`;
    }
    this.props.history.push(newUrl);
    this.fetchOrders(page, query, status, view);
  };

  storeName = store => {
    const {name, platform} = store;
    let storeName = name;
    if (platform.name === "Shopify") {
      storeName = name.split('.')[0];
    }
    return storeName;
  };

  fetchOrders = (page, query, status, view) => {
    this.setState({isLoadingOrders: true});
    axios
      .get(`/orders`, {
        params: {
          page: page,
          search: query,
          status: status,
          view: view
        }
      })
      .then(res => {
        const { data, meta } = res.data;
        if (data) {
          this.setState({
            orders: data,
            page: meta.current_page,
            pageSize: meta.per_page,
            current: parseInt(meta.current_page),
            total: meta.total,
            path: meta.path
          });
        }
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({isLoadingOrders: false}));
  };

  handleChange = (pagination, filters, sorter) => {
    this.setState({
      filteredInfo: filters,
      sortedInfo: sorter
    });
  };

  clearFilters = () => {
    this.setState({filteredInfo: null});
  };

  clearAll = () => {
    this.setState({
      filteredInfo: null,
      sortedInfo: null
    });
  };

  onSearchOrders = query => {
    this.getOrders(1, query, this.state.status, this.state.view);
  };

  onOrdersStatusSearch = status => {
    this.setState({status: status});
    this.getOrders(1, this.state.query, status, this.state.view);
  };

  onSelectChange(selectedRowKeys) {
    this.setState({selectedRowKeys});
  }

  clearErrorSelectedOrders = () => {
    axios
      .post(`/orders/${this.state.selectedRowKeys.join()}/clear-error`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error));
  };

  holdSelectedOrders = () => {
    axios
      .post(`/orders/${this.state.selectedRowKeys.join()}/hold`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error));
  };

  releaseSelectedOrders = () => {
    axios
      .post(`/orders/${this.state.selectedRowKeys.join()}/release`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error));
  };

  cancelSelectedOrders = () => {
    this.setState({isCancelConfirmationVisible: true});
  };

  onConfirmCancelSelectedOrders = () => {
    axios
      .post(`/orders/${this.state.selectedRowKeys.join()}/cancel`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error));
  };

  deleteSelectedOrders = () => {
    this.setState({isDeleteConfirmationVisible: true});
  };

  onConfirmDeleteOrders = () => {
    axios
      .delete(`/orders/${this.state.selectedRowKeys.join()}`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error))
      .finally(() =>
        this.setState({
          isDeleteConfirmationVisible: false
        })
      );
  };

  switchOrdersView = (view) => {
    let ordersView = view === 'normal' ? 'deleted' : 'normal';

    this.setState({
      view: ordersView
    });

    this.getOrders(1, this.state.query, this.state.status, ordersView);
  };

  restoreSelectedOrders = () => {
    axios
      .post(`/orders/${this.state.selectedRowKeys.join()}/restore`)
      .then(res => {
        this.setState({
          selectedRowKeys: []
        });
        this.getOrders();
      })
      .catch(error => displayErrors(error));
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      orders,
      isLoadingOrders,
      total,
      current,
      pageSize,
      selectedRowKeys,
      isDeleteConfirmationVisible,
      isCancelConfirmationVisible,
      orderStatuses,
      view
    } = this.state;

    let {sortedInfo, filteredInfo} = this.state;

    sortedInfo = sortedInfo || {};

    filteredInfo = filteredInfo || {};

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this)
    };

    const hasSelected = selectedRowKeys.length > 0;

    const statusesRender = Object.entries(orderStatuses).map(status => {
      if (status[0].indexOf("PROCESSING") === -1) {
        return (
          <Select.Option key={status[1]} value={status[1]}>
            {status[0].replaceAll("_", " ")}
          </Select.Option>
        );
      }
    });

    return (
      <>
        <Modal
          title="Delete selected orders"
          visible={isDeleteConfirmationVisible}
          centered
          content={<p>Confirm Product Delete</p>}
          onCancel={() => this.setState({isDeleteConfirmationVisible: false})}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({isDeleteConfirmationVisible: false})
              }
            >
              Cancel
            </Button>,
            <Button key={2} type="danger" onClick={this.onConfirmDeleteOrders}>
              Delete
            </Button>
          ]}
        >
          {selectedRowKeys.length} orders will be permanently deleted. You
          cannot undo this action.
        </Modal>

        <Modal
          title="Cancel selected orders"
          visible={isCancelConfirmationVisible}
          centered
          content={<p>Confirm Product Delete</p>}
          onCancel={() => this.setState({isCancelConfirmationVisible: false})}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({isCancelConfirmationVisible: false})
              }
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={this.onConfirmCancelSelectedOrders}
            >
              Cancel Orders
            </Button>
          ]}
        >
          {selectedRowKeys.length} orders will be cancelled. You cannot undo
          this action.
        </Modal>

        <Row>
          <Col>
            <Row>
              <Col sm={24} md={12}>

                <Title>{this.state.view === 'normal' ? "Orders" : "Deleted Orders"}</Title>
              </Col>
              <Col sm={24} md={12}>
                <Row>
                  <Search
                    placeholder="Search orders"
                    value={this.state.queryDisplay}
                    onChange={event =>
                      this.setState({queryDisplay: event.target.value})
                    }
                    onSearch={value => this.onSearchOrders(value)}
                    style={{marginBottom: "16px"}}
                    enterButton
                  />

                </Row>
              </Col>
            </Row>

            <div>Total: {total}</div>

            <div style={{display: "flex"}}>
              <BatchActions
                hasSelected={hasSelected}
                selectedRowKeys={selectedRowKeys}
              >

                {view === 'deleted' ?
                  <>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.restoreSelectedOrders.bind(this)}
                    >
                      Restore
                    </Button>
                  </>
                  :
                  <>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.clearErrorSelectedOrders.bind(this)}
                    >
                      Clear Errors
                    </Button>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.holdSelectedOrders.bind(this)}
                    >
                      Hold
                    </Button>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.releaseSelectedOrders.bind(this)}
                    >
                      Release
                    </Button>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.cancelSelectedOrders.bind(this)}
                    >
                      Cancel
                    </Button>
                    <Button
                      style={{display: hasSelected ? "initial" : "none"}}
                      onClick={this.deleteSelectedOrders.bind(this)}
                    >
                      Delete
                    </Button>
                  </>
                }
              </BatchActions>
              <div
                style={{
                  alignItems: "center",
                  display: hasSelected ? "none" : "flex"
                }}
              >
                <div>Status</div>

                <Select
                  className="statusFilter"
                  defaultValue={isNaN(Number(this.state.status)) ? 'all' : Number(this.state.status)}
                  onChange={this.onOrdersStatusSearch}
                >
                  <Select.Option key={"all"} value={"all"}>
                    ALL
                  </Select.Option>
                  {statusesRender}
                </Select>
              </div>
              <div
                style={{marginLeft: "auto", alignItems: "center", display: "flex"}}>
                <Button
                  onClick={() => this.switchOrdersView(view)}
                >
                  {
                    view === 'normal' ?
                      <>
                        View Deleted Orders
                      </>
                      :
                      <>
                        View Orders
                      </>
                  }
                </Button>
              </div>
            </div>

            {
              view === "deleted" ?
                <Table
                  rowKey={"id"}
                  rowSelection={rowSelection}
                  columns={this.columns}
                  locale={{
                    emptyText: 'No orders placed yet'
                  }}
                  dataSource={orders}
                  pagination={false}
                  loading={isLoadingOrders}
                />
                :
                <Table
                  rowKey={"id"}
                  rowSelection={rowSelection}
                  columns={this.columns}
                  locale={{
                    emptyText: 'No orders placed yet'
                  }}
                  dataSource={orders}
                  pagination={false}
                  loading={isLoadingOrders}
                  onRow={(record, index) => ({
                    onClick: () => {
                      this.props.history.push(`/orders/${record.id}`);
                    }
                  })}
                />
            }

            <Pagination
              onChange={page => this.getOrders(page)}
              total={total}
              current={current}
              pageSize={pageSize}
            />
          </Col>
        </Row>
      </>
    );
  }
}
