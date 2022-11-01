import React, {Component} from 'react';
import { Form, Icon, Pagination, Avatar, Input, Button, Row, Col, Typography, Modal, Table, message } from 'antd/lib/index';
import {PushpinFilled, PlusSquareOutlined} from '@ant-design/icons';
import {Select, Spin} from 'antd';
import qs from 'qs';
import axios from 'axios';
import {displayErrors} from "../../../utils/errorHandler";
import Search from 'antd/lib/input/Search';
import VariantMapper from '../../Components/Modals/VariantMapperEnhanced';

const { Option } = Select;

const { Title } = Typography;

class ManualOrderForm extends Component {

  constructor(props) {
    super(props);
    this.state = {
      addressInput: "",
      isModalVisible: false,
      isProductsLoading: false,
      initialProducts: [],
      selectedProducts: [],
      selectedRowKeys: [],
      shippingCost: "",
      countryCode: "",
      discount: 0,
      tax: 0,
      totalCost: 0,
      total: 0,
      queryDisplay: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query || "",
      currentPage: 0,
      pageSize: 0,
      query: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).query || null,
      page: qs.parse(this.props.location.search, { ignoreQueryPrefix: true }).page || 1,
      products: [],
      form: {
        name: "",
        address1: "",
        address2: "",
        platformStoreName: "",
        city: "",
        state: "",
        phone: "",
        zip: "",
        country: ""
      },
      addresses: [],
      filteredAddresses: [],
      isLoadingAddresses: false,
      isLoadingTeelaunchVariants: false,
      isSubmitting: false,
      shouldShowAddressModal: false,
      countries: [],
      formattedProductVariants: [],
      onSubmit: val => {
        this.setState({isSubmitting: true})

        if (!this.state.selectedProducts.length) {
          message.error('You cannot create an order without adding line items');
          this.setState({isSubmitting: false})
          return;
        }

        try {
          const executed = this.formatProductVariants(val);
          if (!executed) {
            throw new Error('Something went wrong when placing the order');
          }

        } catch(e) {
          message.error(e);
        }
      },
      selectedProduct:null
    }
  }

  placeOrder = val => {
    const {formattedProductVariants, totalCost} = this.state;

    val.orderNumber = this.state.form.orderNumber;

    axios.post('/orders', {
      ...val,
      total: totalCost,
      platformStoreName: 'teelaunch-store',
      platform_data: JSON.stringify({
        details: val,
        products: formattedProductVariants
      })
    })
      .then(() => this.props.history.push('/orders'))
      .catch((error) => {
        this.setState({isSubmitting: false});
        message.error(error)
      })
      .finally(() => this.setState({isSubmitting: false}))
  }

  formatProductVariants = (val) => {
    const artFiles = [];

    const formattedProductVariants = this.state.selectedProducts.map(variant => {
      if (variant.stageFiles) {
        variant.stageFiles.forEach(({accountImageId, id}) => artFiles.push({id, accountImageId}));
      }
      return {
        product_id: variant.productId,
        variant_id: variant.id,
        quantity: variant.quantity,
        sku: variant.blankVariant.sku,
        price: parseFloat(variant.blankVariant.price) * parseFloat(variant.quantity),
        title: variant.name,
        image_url: variant.blankVariant.blankCategoryId === 10 ? variant.blankVariant.thumbnail : variant.thumb,
        art_files: artFiles,
        properties: variant.properties,
        file_name: variant.blankVariant.fileName
      }
    })

    this.setState({formattedProductVariants}, () => this.placeOrder(val));
  }

  componentDidMount() {
    this.getCountries();
  };

  handleQuantityChange = (e, record) => {
    const selectedProducts = [...this.state.selectedProducts];
    const variant = selectedProducts.find(prod => prod.index === Number(record.index));
    const index = selectedProducts.indexOf(variant);

    selectedProducts[index].quantity = e.target.value;

    this.setState({selectedProducts}, this.getOrderCost);
  }

  handlePersonalizationChange = (e, record) => {
    const selectedProducts = [...this.state.selectedProducts];
    const variant = selectedProducts.find(prod => prod.index === Number(record.index));
    const index = selectedProducts.indexOf(variant);

    selectedProducts[index].properties = {
      'custom_text': e.target.value
    };

    this.setState({selectedProducts}, this.getOrderCost);
  }

  onDeleteHandler = record => {
    const selectedProducts = [...this.state.selectedProducts];
    const variant = selectedProducts.find(prod => prod.index === Number(record.index));
    const index = selectedProducts.indexOf(variant);

    selectedProducts.splice(index, 1);

    if(selectedProducts.length>0)
      this.setState({selectedProducts}, this.getOrderCost);
    else
      this.setState({
        selectedProducts,
        totalCost:0,
        shippingCost: 0,
        discount: 0,
        tax: 0,
      })
  }

  getCountries = () => {
    axios.get(`countries`).then(res => {
      const { data } = res.data;
      this.setState({ countries: data });
    }).catch(error => displayErrors(error))
  };

  getAddresses = () => {
    this.setState({isLoadingAddresses: true});
    axios.get('account/addresses')
      .then(res => {
        const { data } = res.data;
        const addresses = data.map(data => {
          return {
            ...data,
            key: data.id
          }
        })
        this.setState({
          addresses,
          filteredAddresses: JSON.parse(JSON.stringify(addresses))
        })
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({isLoadingAddresses: false}))
  }

  attachAddress = address => {
    const { form } = this.state;
    this.setState({shouldShowAddressModal: false})

    // Update State
    form.name = `${address.first_name} ${address.last_name}`;
    form.address1 = address.address1;
    form.address2 = address.address2;
    form.city = address.city;
    form.state = address.state;
    form.zip = address.zip;
    form.country = address.country;

    this.setState({form})

    // Update UI
    this.props.form.setFieldsValue({
      name: `${address.first_name} ${address.last_name}`,
      address1: address.address1,
      address2: address.address2,
      city: address.city,
      state: address.state,
      zip: address.zip,
      country: address.country
    })
  }

  onAddressSearch = () => {
    const filteredAddresses = this.state.addresses.filter(
      address => address.first_name.toLowerCase().includes(this.state.addressInput.toLowerCase())
    );

    this.setState({filteredAddresses});
  }

  onSelectCustomer = () => {
    this.getAddresses();
    this.setState({shouldShowAddressModal: true});
  }

  onAddItem = e => {
    e.preventDefault();
    this.setState({isModalVisible: true})
  }

  getSurcharge = (category, printFiles) => {
    let surcharge = 0;
    const isApparel = (category.toLowerCase() === 'apparel');
    if (isApparel && (printFiles.length) > 1) {
      surcharge = 5;
    }
    return surcharge;
  };

  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        this.state.onSubmit(values);
      }
    });
  };

  getOrderCost = () => {
    if (this.state.selectedProducts.length && !!this.state.countryCode) {
      let data = {
        selectedProducts: this.state.selectedProducts,
        countryCode: this.state.countryCode
      };

      axios.post('/orders/cost', data)
        .then(response => {
          this.setState({
            totalCost: response.data.subTotal,
            shippingCost: response.data.shippingTotal,
            discount: response.data.discountTotal,
            tax: response.data.tax,
          });


        })
        .catch(err => console.log(err))
    }
  }

  onChangeCountry = countryCode => {
    this.setState({countryCode}, this.getOrderCost)
  }

  onSelectVariant = (e) => {
    let selectedProducts = [...this.state.selectedProducts];
    e.index = selectedProducts.length;
    selectedProducts.push(e);
    this.setState({selectedProducts}, () => {
      this.getOrderCost()
    })
    return
  }

  handleCancel = () => { this.setState({ isModalVisible: false }) }

  render() {
    const {
      countries,
      filteredAddresses,
      shouldShowAddressModal,
      isLoadingAddresses,
      addressInput,
      isModalVisible,
      isSubmitting,
      products,
      total,
      pageSize,
      currentPage,
      selectedProducts,
      totalCost,
      isProductsLoading,
    } = this.state;

    const { getFieldDecorator } = this.props.form;

    const renderTotalCost = () => {
      return this.state.totalCost > 0 && <div style={{
        color: '#2B3377',
        marginLeft: 'auto',
        width: 'fit-content'}}>
        <p style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '10px'
        }}>
          <span>Sub Total</span>
          <span style={{
            fontWeight: '100'
          }}>${(parseFloat(this.state.totalCost)).toFixed(2)}</span>
        </p>
        <p style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '10px'
        }}>
          <span>Discount</span>
          <span style={{
            fontWeight: '100'
          }}>${parseFloat(this.state.discount).toFixed(2)}</span>
        </p>
        <p style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          gap: '10px'
        }}>
          <span>Shipping</span>
          <span style={{
            fontWeight: '100'
          }}>${parseFloat(this.state.shippingCost).toFixed(2)}</span>
        </p>
        <p style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          gap: '10px'
        }}>
          <span>Tax</span>
          <span style={{
            fontWeight: '100'
          }}>${parseFloat(this.state.tax).toFixed(2)}</span>
        </p>
        <p style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          fontWeight: 'bold',
          gap: '10px'
        }}>
          <span>Total</span>
          <span>${(parseFloat(this.state.totalCost) + parseFloat(this.state.shippingCost) + parseFloat(this.state.tax) - parseFloat(this.state.discount)).toFixed(2)}</span>
        </p>
      </div>
    }

    const selectedProductsColumns = [
      {
        title: "Line Item",
        dataIndex: "thumbnail",
        key: "thumbnail",
        render: (value, record) => {
          return (
            <div className="line-item__selected">
              <Avatar src={record.blankVariant.blankCategoryId === 10 ? record.blankVariant.thumbnail : record.thumb} size={64}></Avatar>
              <div className="">
                <p>{record.name}</p>
                <p>{record['blankVariant']['sku']}</p>
                {record['blankVariant']['blankCategoryId'] === 10 &&
                  <>
                    <Input type="text"
                           placeholder={"Enter Personalization Text Here"}
                           onChange={e => this.handlePersonalizationChange(e, record)}
                           style={{width: '260px'}} />
                  </>
                }
                <p></p>
              </div>
            </div>
          )
        }
      },
      {
        title: "Art Files",
        dataIndex: "artFiles",
        align: "center",
        key: "art",
        render: (value, record) => {
          return value?.map((artFile,index) =>
            <span key={index}>
              <a href={artFile.fileUrl} download>
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
              <div style={{ position: "relative", top: "-8px" }}>
                {artFile.blankStageLocation && artFile.blankStageLocation.shortName}
              </div>
            </span>
          )
        }
      },
      {
        title: "Price",
        dataIndex: "price",
        key: "price",
        render: (_, record) => <>${ parseFloat(record['blankVariant']['price']).toFixed(2) }</>
      },
      {
        title: "Qty",
        dataIndex: "quantity",
        key: "quantity",
        render: (_, record) => {
          return <>
            <Input type="number"
              min={1}
              defaultValue={1}
              onChange={e => this.handleQuantityChange(e, record)}
              style={{width: '100px'}} />
          </>
        }
      },
      {
        title: "Total",
        key: "total",
        render: (_, {blankVariant, quantity}) => {
          return <>${ (parseFloat(blankVariant.price) * parseFloat(quantity)).toFixed(2) }</>
        }
      },
      {
        title: "Action",
        key: "action",
        render: (_, record) => {
          return <>
            <Icon type="minus-circle"
              theme="filled"
              onClick={e => this.onDeleteHandler(record)}
              style={{
                fontSize: '1.5em',
                color: '#2B3377',
                cursor: 'pointer'
              }} />
          </>
        }
      }
    ];

    const columns = [
      {
        title: 'Addresses',
        key: 'address',
        dataIndex: 'first_name',
        render: (_, {id, first_name, last_name, address1, city, country}) => {
          return <>
            <div key={id} className="address-row">
              <PushpinFilled className="address-row__icon" />
              <div className="address-row__info">
                <p>{first_name} {last_name ?? ''}</p>
                <p>
                  <span>{city}</span>/{country}
                </p>
                <p>{address1}</p>
              </div>
            </div>
          </>
        }
      },
      {
        key: 'attach',
        render: (_, record) => <Button key={record.id} onClick={e => this.attachAddress(record)}>Attach</Button>
      }
    ];
    return (
      <div id="manual-order">
        {isModalVisible &&
          <VariantMapper
            title = "Add Products"
            properties={['sku', 'optionValues', 'teelaunchPrice', 'retailPrice', 'profit']}
            onSelectVariant={(productVariant)=>this.onSelectVariant(productVariant)}
            visible={isModalVisible}
            handleCancel={this.handleCancel}
            isManualOrder={true}
          />
        }
          <div className="header">
            <Title level={1}>Create Manual Order</Title>
            <div>
              <Button type="default"
                className="add-item-btn"
                onClick={this.onAddItem}>
                Add Item
              </Button>
              <Button onClick={this.handleSubmit}
                loading={isSubmitting}
                type="primary">Place Order</Button>
            </div>
          </div>
          <Row gutter={ {xs: 8, md: 24, lg: 32} }>
            <Col xs={24}
              md={8}
              className="address-form-container">
                <div className="manual-order-form">
          {/* <Modal visible={shouldShowAddressModal}
            footer={false}
            title="Select Customer"
            onCancel={() => this.setState({shouldShowAddressModal: false})}>
              <Search placeholder="Search by name"
                style={{marginBottom: '10px'}}
                onChange={e => this.setState({addressInput: e.target.value})}
                onSearch={() => this.onAddressSearch()}
                enterButton
                value={addressInput} />
              <Table dataSource={filteredAddresses}
                columns={columns}
                loading={isLoadingAddresses} />
          </Modal> */}
                <Form layout={'vertical'}>
                  <Title level={2} style={{marginBottom: '15px'}}>Details</Title>
                  <Form.Item label="Email">
                    {getFieldDecorator('email', {
                      rules: [
                        { required: true, message: 'Please input customer email' }
                      ]
                    })(
                      <Input
                        type="text"
                        placeholder="Email"
                      />,
                    )}
                  </Form.Item>
                  <Form.Item label="Phone">
                    {getFieldDecorator('phone')(
                      <Input
                        type="text"
                        placeholder="Phone"
                      />,
                    )}
                  </Form.Item>
                  {/* Keep code for later use */}
                  {/* {process.env.MIX_APP_ENV !== 'local' ? (
                    <Form.Item label={this.platformStoreInputLabel()}>
                      {getFieldDecorator('platformStoreName', {
                        rules: [{ required: true, message: 'Please select platform store' }],
                        initialValue: null
                      })(
                        this.platformStoreOptions()
                      )}
                    </Form.Item>
                  ) : null} */}
                  {/* Keep code for later use */}

                  <div style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    marginBottom: '15px'
                  }}>
                    <Title level={2}>Shipping Address</Title>
                    {/* <Button type="default"
                      onClick={this.onSelectCustomer}>Select Customer</Button> */}
                  </div>
                  <Form.Item label="Name">
                    {getFieldDecorator('name', {
                      rules: [{ required: true, message: 'Please input the name' }],
                    })(
                      <Input
                        type="text"
                        placeholder="Name"
                      />,
                    )}
                  </Form.Item>
                  <Form.Item label="Address 1">
                    {getFieldDecorator('address1', {
                      rules: [{ required: true, message: 'Please input your Street Address' }],
                    })(
                      <Input
                        type="text"
                        placeholder="Address 1"
                      />,
                    )}
                  </Form.Item>
                  <Form.Item label="Address 2">
                    {getFieldDecorator('address2', {
                      rules: [{ required: false, message: 'Please input your Street Address' }],
                    })(
                      <Input
                        type="text"
                        placeholder="Address 2"
                      />,
                    )}
                  </Form.Item>
                  <Form.Item label="City">
                    {getFieldDecorator('city', {
                      rules: [{ required: true, message: 'Please input your City' }]
                    })(
                      <Input
                        type="text"
                        placeholder="City"
                      />,
                    )}
                  </Form.Item>

                    <Row gutter={16}>
                      <Col span={12}>
                        <Form.Item label="State">
                          {getFieldDecorator('state', {
                            rules: [{ required: false, message: 'Please input your State' }]
                          })(
                            <Input
                              type="text"
                              placeholder="State"
                            />,
                          )}
                        </Form.Item>
                      </Col>
                      <Col key="8" span={12}>
                        <Form.Item label="Zip">
                          {getFieldDecorator('zip', {
                            rules: [{ required: true, message: 'Please input your Postal Code' }]
                          })(
                            <Input
                              type="text"
                              placeholder="Zip"
                            />,
                          )}
                        </Form.Item>
                      </Col>
                    </Row>
                    <Row gutter={16}>
                      <Col span={24}>
                        <Form.Item label="Country">
                          {getFieldDecorator('country', {
                            rules: [{ required: true, message: 'Please input your Country' }]
                          })(
                            <Select name="country"
                                    placeholder="Select a Country"
                                    showSearch
                                    onChange={this.onChangeCountry}
                                    onSearch={this.onChange}
                                    optionFilterProp="children"
                                    filterOption={(input, option) =>
                                      option.props.children.toLowerCase().indexOf(input.toLowerCase()) >= 0
                                    }>
                              {countries.map((country, index) => <Option key={index} value={country.code}>{country.name}</Option>)}
                            </Select>,
                          )}
                        </Form.Item>
                      </Col>
                    </Row>
                </Form>
            </div>
            </Col>
            <Col xs={24} md={16} >
              <Table columns={selectedProductsColumns}
                dataSource={selectedProducts}
                locale={{
                  emptyText: 'There are no selected line items'
                }}
                size="middle" />
              <p>{renderTotalCost()}</p>
            </Col>
          </Row>
        </div>
    );
  }
}

export default Form.create({ name: 'manual_order_form' })(ManualOrderForm);
