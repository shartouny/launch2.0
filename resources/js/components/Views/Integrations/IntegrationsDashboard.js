import React, { Component } from "react";
import { Link } from "react-router-dom";
import {
  Col,
  Row,
  Typography,
  Button,
  Modal,
  Card,
  Avatar,
  Dropdown,
  Menu,
  Icon,
} from "antd";
import axios from "axios";
import { message } from "antd";
import { displayErrors } from "../../../utils/errorHandler";
import shopifyLogo from "../../Assets/shopify_logo.png";
import etsyLogo from "../../Assets/etsy_logo.svg";
import launchLogo from "../../Assets/launch-logo.svg";
import bigcommerceLogo from "../../Assets/rutter-favicon-bigcommerce.svg";
import prestashopLogo from "../../Assets/rutter-favicon-prestashop.svg";
import amazonLogo from "../../Assets/rutter-favicon-amazon.svg";
import magentoLogo from "../../Assets/rutter-favicon-magento.svg";
import squareSpaceLogo from "../../Assets/rutter-favicon-squarespace.svg";
import squareLogo from "../../Assets/rutter-favicon-square.svg";
import wixLogo from "../../Assets/rutter-favicon-wix.svg";
import ebayLogo from "../../Assets/rutter-favicon-ebay.svg";
import wooCommerceLogo from "../../Assets/rutter-favicon-woocommerce.svg";
import qs from "qs";
import {isShopifyApp} from "../../../utils/iframeRunning";
import StoreConfigurationForm from "../../Components/Forms/StoreConfigurationForm";
import RutterIntegration from "./RutterIntegration";


const { Title } = Typography;
const { Meta } = Card;

export default class IntegrationsDashboard extends Component {
  constructor(props) {
    super(props);


    this.state = {
      initPosition:1,
      isPayout:false,
      ModalText: "Content of the modal",
      visible: false,
      confirmLoading: false,
      allowEditCreditCard:false,
      stores: [],
      isRemoveConfirmationVisible: false,
      showPayment:false,
      targetStore: {},
      status:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .status || null,
      platform:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .platform || null,
      store:
        qs.parse(this.props.location.search, { ignoreQueryPrefix: true })
          .store || null,
      isEditConfigurationVisible: false,
      storeConfiguration: {
        logo: "",
        favIcon: "",
        primaryColor: "",
        secondaryColor: "",
      },
      warning:''
    };
  }

  componentDidMount() {
    this.fetchPlatformStores();
    if (this.state.status === "failed") {
      displayErrors(
        `Failed to connect to ${this.state.platform}, please try again or contact support`
      );
    }
    if (this.state.status === "success" && this.state.store) {
      message.success(`Store ${this.state.store} connected to teelaunch`);
    }
  }

  fetchPlatformStores = () => {
    axios
      .get("stores")
      .then(res => {
        const { data } = res.data;
        if (!data) {
          return displayErrors();
        }
        this.setState({ stores: data });

        if(this.props?.location?.search?.includes('?r='))
        {
          const platform_store_id_base_64 = this.props?.location?.search.replace('?r=','')
          const platform_store_id =  Buffer.from(platform_store_id_base_64,'base64').toString('ascii')
          axios({ url: "/launch/app/payout/confirm", baseURL: "", method: "POST" ,data:{platform_store_id}}).then(({data})=>{
            this.setState({...this.state,warning:''})
            this.editLaunchConfig({id:platform_store_id},3)
            this.props.history.push('/integrations')
          }).catch(e=>{
            this.setState({...this.state, warning:platform_store_id})
            this.editLaunchConfig({id:platform_store_id},3)
          })
        }else if(this.props?.location?.search?.includes('?d='))
        {
          const platform_store_id_base_64 = this.props?.location?.search.replace('?d=','')
          const platform_store_id =  Buffer.from(platform_store_id_base_64,'base64').toString('ascii')
          this.editLaunchConfig({id:platform_store_id},3)
          this.props.history.push('/integrations')
        }
      })
      .catch(err => displayErrors(err));
  };

  showModal = () => {
    this.setState({
      visible: true
    });
  };

  handleOk = () => {
    this.setState({
      ModalText: "The modal will be closed after two seconds",
      confirmLoading: true
    });
    setTimeout(() => {
      this.setState({
        visible: false,
        confirmLoading: false
      });
    }, 2000);
  };

  handleCancel = () => {
    this.setState({
      visible: false
    });
  };

  handleShopifyClick = () => {
    window.open("https://apps.shopify.com/teelaunch-1", "_blank");
  };

  handleLaunchClick = () => {
    axios({ url: "/launch/app/request-install", baseURL: "", method: "GET" })
      .then(res => {
        const { registerURL } = res.data;
        if (!registerURL) {
          return message.error("Failed to generate registration link");
        }
        window.open(registerURL, "_self");
      })
      .catch(err => {
        displayErrors(err);
      });
  };

  handleEtsyClick = () => {
    axios({ url: "/etsy/app/request-install", baseURL: "", method: "GET" })
      .then(res => {
        const { loginUrl } = res.data;
        if (!loginUrl) {
          return message.error("Failed to generate install link");
        }
        window.open(loginUrl, "_self");
      })
      .catch(err => {
        displayErrors(err);
      });
  };

  storeUrl = store => {
    const { url, platform } = store;
    let storeUrl = url;
    if (platform.name === "Etsy") {
      storeUrl = `https://www.etsy.com/your/shops/${store.name}`;
    }
    if (platform.name === "teelaunch") {
      storeUrl = `#`;
    }
    const httpsRegex = new RegExp(/^(https?:\/\/)/, "i");

    return "https://" + storeUrl.replace(httpsRegex, "");
  };

  storeName = store => {
    const { name, platform } = store;
    let storeName = name;
    if (platform.name === "Shopify") {
      storeName = name.split('.')[0];
    }
    return storeName;
  };

  viewProducts = () => {
    this.props.history.push(`/integrations/${props.store.id}/products`);
  };

  showRemoveModal = store => {
    this.setState({
      isRemoveConfirmationVisible: true,
      targetStore: store
    });
  };

  onConfirmRemoveIntegration = () => {
    const { targetStore } = this.state;
    axios
      .delete(`/stores/${targetStore.id}`)
      .then(() => {
        message.info(`Store ${targetStore.name} disconnected from teelaunch`);
        this.setState({ isRemoveConfirmationVisible: false });
        this.fetchPlatformStores();
      })
      .catch(error => displayErrors(error));
  };

  editLaunchConfig = (store,initPosition) => {
    axios({url: "/launch/app/" + store.id, baseURL: "", method: "GET"})
      .then(res => {
        const settings = res.data;
        const theme = JSON.parse(settings.filter(setting => setting.key === "theme")[0].value);

        if(!store.name )
          store.name = settings.filter(setting => setting.key === "store_name")[0]?.value

        this.setState({
          isEditConfigurationVisible: true,
          targetStore: store,
          initPosition:initPosition,
          storeConfiguration: {
            logo: settings.filter(setting => setting.key === "logo")[0].value,
            favIcon: settings.filter(setting => setting.key === "fav_icon")[0].value,
            card:settings.filter(setting => setting.key === "card")[0]?.value,
            exp_month:settings.filter(setting => setting.key === "exp_month")[0]?.value,
            exp_year:settings.filter(setting => setting.key === "exp_year")[0]?.value,
            current_period_start:settings.filter(setting => setting.key === "current_period_start")[0]?.value,
            current_period_end:settings.filter(setting => setting.key === "current_period_end")[0]?.value,
            platform_store_id:settings[0]['platform_store_id'],
            subscription_id:settings.filter(setting => setting.key === "subscription_id")[0]?.value,
            charges_enabled:settings.filter(setting => setting.key === "charges_enabled")[0]?.value,
            dashboard_url:settings.filter(setting => setting.key === "dashboard_url")[0]?.value,
            primaryColor: theme.primaryColor,
            secondaryColor: theme.secondaryColor,
          }
        });

      })
      .catch(err => {
        displayErrors(err);
      });
  };

  saveEditConfiguration = (data) => {
    const {targetStore} = this.state;

    axios({url: `/launch/app/${targetStore.id}`, baseURL: "", method: "POST", data: data})
      .then(() => {
        message.info(`Store ${targetStore.name} has been updated successfully`);
        this.setState({ isEditConfigurationVisible: false });
        this.fetchPlatformStores();
      })
      .catch(error => displayErrors(error));
  };

  onClickOpenDashboard = () => { window.open(this.state.storeConfiguration.dashboard_url, '_self') }

  handleChangePrimaryColor=(e)=>{
    this.setState(
      {
        ...this.state,
        storeConfiguration: {
          ...this.state.storeConfiguration,
          primaryColor: e
        }
      }
    )
  }

  handleChangeSecondaryColor=(e)=>{
    this.setState(
      {
        ...this.state,
        storeConfiguration: {
          ...this.state.storeConfiguration,
          secondaryColor: e
        }
      }
    )
  }

  handlePressSubmitPayment=(stripe,elements)=> {

    axios({url: `/launch/app/payment/create`, baseURL: "", method: "POST", data: {platform_store_id: this.state.storeConfiguration.platform_store_id}}).then(({data}) => {
      return stripe.confirmCardSetup(data.client_secret, {
        payment_method: {card: elements}
      })
    })
      .then((response) => {
          return axios({url: `/launch/app/payment/update`, baseURL: "", method: "POST", data: {
              platform_store_id: this.state.storeConfiguration.platform_store_id,
              payment_method: response.setupIntent.payment_method
            }}).then(({data}) => {
              this.setState({...this.state,showPayment:false,storeConfiguration:{...this.state.storeConfiguration,...data}})
          })
        }
      )
      .catch((e) => {
        displayErrors('unable to proceed, please contact your card issuer')
        this.setState({...this.state,isPayout:false})
      })


  }

  handlePressCreateConnectAccount=()=>{

    this.setState({...this.state,isPayout:true})
      axios({url: `/launch/app/payout/create`, baseURL: "", method: "POST",data:{ platform_store_id: this.state.storeConfiguration.platform_store_id}})
        .then(({data:{account_link}}) =>{
          this.setState({...this.state,isPayout:false})
          window.open(account_link, '_self');
        }).catch(e=>{
          this.setState({...this.state, isPayout:false})
        displayErrors('unable to proceed, please try again.')
        console.log('error',e)
      })

  }

  handleCancelEditModal = (e)=> { this.setState({ isEditConfigurationVisible: false})}

  handleTabBarPressed = (initPosition) => {this.setState({...this.state,initPosition})}

  handleOpenChangePayment = () => { this.setState({...this.state,showPayment:!this.state.showPayment}) }



  render() {
    const {
      visible,
      confirmLoading,
      stores,
      isRemoveConfirmationVisible,
      isEditConfigurationVisible,
      targetStore,
      storeConfiguration
    } = this.state;


    const StoreCard = props => {
      let storeLogo = '';

      if(props.store.platform.name === 'Rutter'){
        switch (props.store.platformType){
          case 'Shopify':
            storeLogo = shopifyLogo;
            break;
          case 'Amazon':
            storeLogo = amazonLogo;
            break;
          case 'Ebay':
            storeLogo = ebayLogo;
            break;
          case 'Bigcommerce':
            storeLogo = bigcommerceLogo;
            break;
          case 'Magento':
            storeLogo = magentoLogo;
            break;
          case 'Prestashop':
            storeLogo = prestashopLogo;
            break;
          case 'Square':
            storeLogo = squareLogo;
            break;
          case 'Squarespace':
            storeLogo = squareSpaceLogo;
            break;
          case 'Wix':
            storeLogo = wixLogo;
            break;
          case 'Woocommerce':
            storeLogo = wooCommerceLogo;
            break;
        }

      }
      else{
        storeLogo = props.store.platform.logo;
      }

      return (
        <>
          <Card
            hoverable={true}
            style={{ margin: "16px auto", marginRight: 32, maxWidth: 800 }}
          >
            <Meta
              avatar={<Avatar src={storeLogo} />}
              title={
                <Row>
                  <Col sm={24} lg={12} style={{ overflow: 'hidden', textOverflow: 'ellipsis'}}>
                    {props.store && this.storeName(props.store)}
                  </Col>
                  <Col
                    sm={24}
                    lg={12}
                    className="button-group"
                    style={{ textAlign: "end" }}
                  >
                    <Button type="link">
                      <Dropdown
                        trigger={["click"]}
                        overlay={
                          <Menu>
                            <Menu.Item>
                              <Link
                                to={`/integrations/${props.store.id}/products?page=1`}
                              >
                                Edit Products
                              </Link>
                            </Menu.Item>

                            <Menu.Item>
                              <a
                                href={this.storeUrl(props.store)}
                                target="_blank"
                              >
                                Open {props.store.platform.name !== 'Rutter' ? props.store.platform.name : props.store.platformType}
                              </a>
                            </Menu.Item>

                            <Menu.Item>
                              <div
                                onClick={() =>
                                  this.showRemoveModal(props.store)
                                }
                              >
                                Disconnect Store
                              </div>
                            </Menu.Item>

                            {props.store.platform.name === "Launch" ? <Menu.Item>
                              <div
                                onClick={() => this.editLaunchConfig(props.store,1)}
                              >
                                Edit Store Configuration
                              </div>
                            </Menu.Item> : ""}
                          </Menu>
                        }
                      >
                        <div className="ant-dropdown-link">
                          Actions <Icon type="down" />
                        </div>
                      </Dropdown>
                    </Button>

                    <Link
                      to={`/integrations/${props.store.id}/products?page=1`}
                    >
                      <Button type="">Edit Products</Button>
                    </Link>
                  </Col>
                </Row>
              }
              description={
                <>
                  <Row>
                    <Col sm={24} lg={12}>
                      <a href={props.store.url} target="_blank">
                        {props.store.url}
                      </a>
                    </Col>
                  </Row>
                </>
              }
            />
          </Card>
        </>
      );
    };

    const showLaunch = window.location.hash.toLowerCase().includes('launch') || window.location.hash.toLowerCase().includes('all');

    return (
      <div>
        <Modal
          title="Disconnect store?"
          visible={isRemoveConfirmationVisible}
          centered
          content={<p>Confirm Disconnection of Store</p>}
          onCancel={() => this.setState({ isRemoveConfirmationVisible: false })}
          footer={[
            <Button
              key={1}
              onClick={() =>
                this.setState({ isRemoveConfirmationVisible: false })
              }
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={this.onConfirmRemoveIntegration}
            >
              Confirm
            </Button>
          ]}
        >
          All orders from store {targetStore.name} will fail to process
          including any pending fulfillments. You cannot undo this action.
        </Modal>

        {/*Edit Modal*/}
        <Modal
          title={`Editing ${targetStore.name} store Configuration`}
          visible={isEditConfigurationVisible}
          centered
          width={"40%"}
          content={<p>Confirm Store Configuration Edit </p>}
          onCancel={this.handleCancelEditModal}
          footer={null}
        >
          <StoreConfigurationForm
            warning={this.state.warning}
            isPayout={this.state.isPayout}
            initPosition={this.state.initPosition}
            showPayment={this.state.showPayment}
            submitForm={this.saveEditConfiguration}
            storeConfiguration={storeConfiguration}
            handleTabBarPressed={this.handleTabBarPressed}
            onClickOpenDashboard = {this.onClickOpenDashboard}
            handleChangePrimaryColor={this.handleChangePrimaryColor}
            handleChangeSecondaryColor={this.handleChangeSecondaryColor}
            handlePressSubmitPayment ={this.handlePressSubmitPayment}
            handleOpenChangePayment={this.handleOpenChangePayment}
            handlePressCreateConnectAccount={this.handlePressCreateConnectAccount}
          />
        </Modal>

        <Row>
          <Col sm={24} lg={12}>
            <Title>My Stores</Title>
          </Col>
          <Col sm={24} lg={12} style={{ textAlign: "right" }}>
            {!isShopifyApp() &&
              <Button type="primary" onClick={this.showModal}>
                Add New Store
              </Button>
            }
          </Col>
        </Row>
        <Row>
          <Col sm={24} md={24} lg={12}>
            {stores &&
              stores.map(store => <StoreCard key={store.id} store={store} />)}
          </Col>
        </Row>
        <Modal
          title="Select Store"
          visible={visible}
          width="60%"
          onOk={this.handleOk}
          confirmLoading={confirmLoading}
          onCancel={this.handleCancel}
          footer={[
            <Button key="back" onClick={this.handleCancel}>
              Cancel
            </Button>
          ]}
        >
          <Row type="flex" gutter={[16, 16]}>
            {/*<Col sm={12} md={8} lg={6}>*/}
            {/*  <Card hoverable={true}*/}
            {/*        style={{display: 'flex', height: "100%"}}*/}
            {/*        bodyStyle={{display: 'flex', justifyContent: 'center', alignItems: 'center'}}*/}
            {/*        onClick={this.handleShopifyClick}>*/}
            {/*    <img className="img-responsive"*/}
            {/*         src={shopifyLogo}/>*/}
            {/*  </Card>*/}
            {/*</Col>*/}
            <Col sm={12} md={8} lg={6}>
              <Card
                hoverable={true}
                style={{ display: "flex", height: "100%" }}
                bodyStyle={{
                  display: "flex",
                  justifyContent: "center",
                  alignItems: "center"
                }}
                onClick={this.handleEtsyClick}
              >
                <img className="img-responsive" src={etsyLogo} />
              </Card>
            </Col>

            <RutterIntegration/>

            {/*<Col sm={12} md={8} lg={6} style={{display: 'none'}}>*/}
            {/*  <Card hoverable={true}*/}
            {/*        style={{display: 'flex', height: "100%"}}*/}
            {/*        bodyStyle={{display: 'flex', width: '100%', alignItems: 'center'}}>*/}
            {/*    <Title style={{textAlign: 'center'}}>API</Title>*/}
            {/*  </Card>*/}
            {/*</Col>*/}

            {
              showLaunch ?
                <Col sm={12} md={8} lg={6}>
                  <Card
                    hoverable={true}
                    style={{ display: "flex", height: "100%" }}
                    bodyStyle={{
                      display: "flex",
                      justifyContent: "center",
                      alignItems: "center"
                    }}
                    onClick={this.handleLaunchClick}
                  >
                    <img className="img-responsive" src={launchLogo} />
                  </Card>
                </Col>
              :
              null
            }
          </Row>
        </Modal>
      </div>
    );
  }
}
