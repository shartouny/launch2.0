import React, { Component } from 'react';
import { Redirect } from 'react-router';
import axios from 'axios';
import {
  Row,
  Col,
  Table,
  Button,
  Typography,
  Empty,
  Spin,
  Tag,
  Tabs,
  Pagination
} from 'antd';

import { displayErrors } from '../../../utils/errorHandler';
import AccountSummary from './AccountSummary';

import BillingDeleteModal from '../../Components/Modals/BillingDeleteModal';

import paypalLogo from '../../Assets/paypal_icon.jpg';
import accountCreditLogo from '../../Assets/account-credit.svg';
import { CreditCardOutlined } from "@ant-design/icons";

const {
  Title,
  Paragraph,
  Text,
} = Typography;

const {TabPane} = Tabs;

/**
 *
 */
export default class AccountPaymentHistory extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      activePaymentId: 0,
      isInDeleteProcess: false,
      isCardDeleted: false,
      isDeleteModalVisible: false,
      isLoadingPayments: false,
      isLoadingActivePaymentMethod: false,
      isLoadingOrderData: {},
      activePaymentMethod: this.props.activePaymentMethod,
      paymentHistory: this.props.paymentHistory,
      renderOrderDetails: false,
      orderDetailsId: 0,
      currentSelectedPaymentOrders: {},
      currentPage: 0,
      totalPages: 0,
      pageSize: 0,
      accountCreditValue: 0.00
    };

    this.styles = {
      paymentButtons: {
        marginTop: '10px',
        display: 'inline-flex',
        justifyContent: 'center',
        flexDirection: 'column',
      },
    };

    this.orderColumns = [
      {
        title: 'Store',
        dataIndex: 'order.store',
        render: value => {
          return <>{value.platform.name}: {value.name}</>
        }
      },
      {
        title: 'Order Number',
        dataIndex: 'order.platformOrderNumber',
      },
      {
        title: 'Order total',
        dataIndex: 'totalCost',
        render: value => {
          return <>${value}</>
        }
      },
      {
        title: 'Order Date',
        dataIndex: 'order.platformCreatedAt',
        render: value => (
          <span>
            {new Date(value).toLocaleDateString()}
          </span>
        )
      },
    ];

    this.paymentColumns = [
      {
        title: 'Payment',
        dataIndex: 'id',
        render: (value, record) => <>
          <div>Payment ID: {value}</div>
          {record.accountPaymentMethod.paymentMethodId === 1 &&
          <>
            {record.transactionId == 'ACCOUNT-CREDIT' ?
              <div>
                <img
                  src={accountCreditLogo}
                  style={{ width: '18px', height: '18px', marginRight: '5px' }}
                />
                Account Credit
              </div> :
              <>
                <div>
                  <CreditCardOutlined/> Card
                </div>
                <div>**** **** **** {record.accountPaymentMethod.metadata.find(m => m.key === 'card').value || ''}</div>
              </>
            }
          </>
          }

          {record.accountPaymentMethod.paymentMethodId === 2 &&
          <>
            <div>
              {record.transactionId == 'ACCOUNT-CREDIT' ?
                <div>
                  <img
                    src={accountCreditLogo}
                    style={{ width: '18px', height: '18px', marginRight: '5px' }}
                  />
                  Account Credit
                </div> :
                <>
                  <div>
                    <img
                      src={paypalLogo}
                      style={{ width: '15px', height: '15px', marginRight: '5px' }}
                    />
                    PayPal
                  </div>
                  <div>{record.accountPaymentMethod.metadata.find(m => m.key === 'email').value || ''}</div>
                </>
              }

            </div>
          </>
          }

          {record.accountPaymentMethod.paymentMethodId === 4 &&
          <>
            <div>
              {record.transactionId == 'ACCOUNT-CREDIT' ?
                <div>
                  <img
                    src={accountCreditLogo}
                    style={{ width: '18px', height: '18px', marginRight: '5px' }}
                  />
                  Account Credit
                </div> :
                <div>
                  <img
                    src="/images/manual-pay.svg"
                    style={{ width: '20px', height: '20px' }}
                  /> Manual
                </div>
              }
            </div>
          </>
          }

          {record.accountPaymentMethod.paymentMethodId === 6 &&
          <>
            <div>
              {record.transactionId == 'ACCOUNT-CREDIT' ?
                <div>
                  <img
                    src={accountCreditLogo}
                    style={{ width: '18px', height: '18px', marginRight: '5px' }}
                  />
                  Account Credit
                </div> :
                <div>
                  <div>
                    <img
                      src="/images/stripe_connect.png"
                      style={{ width: '18px', height: '18px', marginRight: '5px' }}
                    />Stripe Connect
                  </div>
                </div>
              }
            </div>
          </>
          }
        </>
      },
      {
        title: 'Status',
        dataIndex: 'status',
        render: (value, record) => <>
            {record.accountPaymentMethod.paymentMethodId !== 4 ? (
              <Tag color={value === 1 ? "green" : "red"}>{value === 1 ? "Completed" : "Failed"}</Tag>
            ) : (
              <Tag color="yellow">Manual</Tag>
            )
            }
        </>
      },
      {
        title: 'Amount',
        dataIndex: 'amount',
        render: value => (
          <span>
            {
              new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
              }).format(value)
            }
          </span>
        ),
      },
      {
        title: 'Date',
        dataIndex: 'createdAt',
        render: value => (
          <span>
            {new Date(value).toLocaleDateString()}
          </span>
        ),
      },
    ];
  };

  componentDidMount() {
    this.getActivePaymentMethod();
    this.getPaymentHistory();
    this.getAccountCredit();
  };

  getAccountCredit = () => {
    axios.get('/account/settings', { params: {
        q: 'account_credit'
      }})
      .then(res => {
        const { data } = res.data;
        this.setState({accountCreditValue: data[0] ? parseFloat(data[0].value) : 0.00})
      })
      .catch(error => displayErrors(error))
  }

  getActivePaymentMethod = () => {
    this.setState({ isLoadingActivePaymentMethod: true });
    axios.get('/account/payment-methods/active')
      .then(res => {
        const { data } = res;
        this.setState({
          activePaymentMethod: data.data
        });
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingActivePaymentMethod: false }));
  };

  /**
   * Get paginated products
   * @param {*} page from antd
   */
  getPaymentHistory = (page = 1) => {
    this.setState({ isLoadingPayments: true });
    axios.get(`/account/payment-history?page=${page}`)
      .then(res => {
        const { data, meta } = res.data;
        if (data) {
          this.setState({
            paymentHistory: data,
            currentPage: meta.current_page,
            totalPages: meta.total,
            pageSize: meta.per_page
          });
        }
      })
      .catch(error => displayErrors(error)).finally(() => this.setState({ isLoadingPayments: false }));
  };

  /**
   *
   * @param {{}} data
   */
  extractDataObject = ({ data }) => {
    return data.data;
  };
  /**
   *
   * @param {{}} record
   */
  onPaymentHistoryClick = record => {
    const { orderId: orderDetailsId } = record;
    //this.setState({ renderOrderDetails: true, orderDetailsId });
    this.props.history.push(`/orders/${record.orderId}`);
  };
  /**
   *
   * @param {boolean} expanded
   * @param {{}} record
   */
  onExpandRow = (expanded, record) => {
    if (!expanded) {
      return;
    }

    if (this.state.currentSelectedPaymentOrders[record.id]) {
      this.setState(prevState => ({
        currentSelectedPaymentOrders: {
          ...prevState.currentSelectedPaymentOrders,
          [record.id]: this.state.currentSelectedPaymentOrders[record.id],
        },
      }));

      return;
    }

    this.setState({ isLoadingOrderData: { [record.id]: true } });

    axios.get(`/account/payment-history/${record.id}`)
      .then(res => {
        const { data } = res.data;

        if (!data) {
          return;
        }

        this.setState(prevState => ({
          currentSelectedPaymentOrders: {
            ...prevState.currentSelectedPaymentOrders,
            [record.id]: data.orderPayments,
          },
        }));

      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingOrderData: { [record.id]: false } }));
  };
  /**
   *
   */
  onDeletePayment = () => {
    const { activePaymentMethod } = this.state;
    this.setState({ isInDeleteProcess: true });

    axios.delete(`/account/payment-methods/${activePaymentMethod.id}`)
      .then(() => this.setState({ isCardDeleted: true, isDeleteModalVisible: false, activePaymentMethod: {} }))
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isInDeleteProcess: false }));
  };

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const {
      onAddPayment,
    } = this.props;

    const {
      currentSelectedPaymentOrders,
      isCardDeleted,
      isInDeleteProcess,
      isDeleteModalVisible,
      isLoadingOrderData,
      paymentHistory,
      activePaymentMethod,
      isLoadingPayments,
      isLoadingActivePaymentMethod,
      renderOrderDetails,
      orderDetailsId,
      currentPage,
      totalPages,
      pageSize
    } = this.state;

    const {
      metadata = {},
      billingAddress = {},
      paymentMethodType = {}
    } = activePaymentMethod;

    const {
      card = '',
      cardExpMonth = '',
      cardExpYear = '',
      email = ''
    } = metadata;

    let hasActivePayment = true;

    /**
     * BE sends two different data types, this extra
     * conditional is needed.
     */
    if (
      Array.isArray(activePaymentMethod)
      && !activePaymentMethod.length
    ) {
      hasActivePayment = false;
    }

    if (renderOrderDetails) {
      return <Redirect to={`/orders/${orderDetailsId}`}/>
    }

    return (
      <>
        <BillingDeleteModal
          title={<span>Delete</span>}
          content={<Text>Are you sure you want to delete this Billing Method?</Text>}
          showModalVisibility={isDeleteModalVisible}
          handleDelete={this.onDeletePayment}
          handleCancel={() => this.setState({ isDeleteModalVisible: false })}
          loading={isInDeleteProcess}
        />
        {/*<Row>*/}
        {/*  <Col>*/}
        {/*    <Title className='text-center'>Billing</Title>*/}
        {/*  </Col>*/}
        {/*</Row>*/}
        <Row gutter={{ xs: 8, md: 24 }}>
          <Col xs={24} md={16}>
            <>
              <Title>Account Summary</Title>
              <Tabs defaultActiveKey="1">
                <TabPane key="1" tab="Payment History">
                  <Spin spinning={isLoadingPayments}>
                    {paymentHistory.length ? (
                      <>
                        <Table
                          rowKey='id'
                          columns={this.paymentColumns}
                          dataSource={paymentHistory}
                          showHeader={true}
                          pagination={false}
                          size='middle'
                          expandedRowRender={record =>
                            <Table
                              rowKey={'id'}
                              columns={this.orderColumns}
                              size={'middle'}
                              loading={isLoadingOrderData[record.id]}
                              showHeader={true}
                              pagination={false}
                              dataSource={currentSelectedPaymentOrders[record.id]}
                              onRow={record => {
                                return {
                                  onClick: () => this.onPaymentHistoryClick(record),
                                };
                              }}
                            />
                          }
                          expandRowByClick={true}
                          onExpand={(expanded, record) => this.onExpandRow(expanded, record)}
                        />
                        <Pagination
                          onChange={page => this.getPaymentHistory(page)}
                          total={totalPages}
                          current={currentPage}
                          pageSize={pageSize}
                        />
                      </>
                    ) : (
                      <Empty
                        style={{ marginTop: '25px' }}
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                      />
                    )}
                  </Spin>
                </TabPane>
                <TabPane key="2" tab="Invoices">
                  <AccountSummary />
                </TabPane>
              </Tabs>
            </>
          </Col>


          <Col xs={24} md={8}>
            <div>
              <Title level={2}>Account Credit</Title>
              <div className="my-2" style={{fontSize: 18}}>
                {this.state.accountCreditValue} $
              </div>
            </div>
            <Title level={2}>Billing Information</Title>
            <div className="my-2">
              <Spin spinning={isLoadingActivePaymentMethod}>

                {activePaymentMethod.paymentMethodId === 1 &&
                <>
                  <div>
                    <CreditCardOutlined/> Card
                  </div>
                  {card && (
                    <div>
                      <Text>**** **** **** {card}</Text>
                      <Paragraph>Exp: <Text>{cardExpMonth}/{cardExpYear}</Text></Paragraph>
                    </div>
                  )}

                  {billingAddress && Object.keys(billingAddress).length && (
                    <div style={{ lineHeight: '0.5' }}>
                      <Paragraph style={{ fontWeight: '600' }}>Billing Address:</Paragraph>
                      <p>
                        {billingAddress.firstName && <Text>{billingAddress.firstName}&nbsp;</Text>}
                        {billingAddress.lastName && <Text>{billingAddress.lastName}</Text>}
                      </p>
                      <p>
                        {billingAddress.company && <Text>{billingAddress.company}</Text>}
                      </p>
                      <p>
                        {billingAddress.address1 && <Text>{billingAddress.address1}</Text>}
                      </p>
                      <p>
                        {billingAddress.address2 && <Text>{billingAddress.address2}</Text>}
                      </p>
                      <p>
                        {billingAddress.city && <Text>{billingAddress.city},&nbsp;</Text>}
                        {billingAddress.state && <Text>{billingAddress.state}&nbsp;</Text>}
                        {billingAddress.zip && <Text>{billingAddress.zip}&nbsp;</Text>}
                      </p>
                      <p>
                        {billingAddress.country && <Text>{billingAddress.country}</Text>}
                      </p>
                    </div>
                  )}
                </>
                }

                {activePaymentMethod.paymentMethodId === 2 &&
                <>
                  <div>
                    <img
                      src={paypalLogo}
                      style={{ width: '15px', height: '15px', marginRight: '5px' }}
                    />
                    PayPal
                  </div>
                  <div>{email}</div>
                </>
                }

                {activePaymentMethod.paymentMethodId === 3 &&
                <div>
                  <img src="/images/payoneer_logo.png"
                       width="100"
                       height="35"
                       alt="payoneer-logo" />
                </div>
                }

                {activePaymentMethod.paymentMethodId === 4 &&
                <div>
                  <img src="/images/manual-payment.svg"
                       width="150"
                       height="60"
                       alt="manual-logo" />
                </div>
                }

                <div style={this.styles.paymentButtons}>
                  {isCardDeleted || !hasActivePayment ? (
                    <Button
                      onClick={onAddPayment}
                      style={{ margin: '8px 0' }}
                      type='primary'
                      shape='round'
                    >
                      Add Billing Method
                    </Button>
                  ) : activePaymentMethod.paymentMethodId !== 4 ? (
                    <Button
                      onClick={() => this.setState({ isDeleteModalVisible: true })}
                      style={{ margin: '8px 0' }}
                      type='danger'
                      shape='round'
                      ghost
                    >
                      Delete Billing Method
                    </Button>
                  ) :
                    <></>
                  }
                </div>

              </Spin>
            </div>
          </Col>
        </Row>
      </>
    );
  }
}
