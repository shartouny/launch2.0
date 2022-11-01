import React, {Component} from "react";
import {Form, Button, Typography, Icon, Upload, Spin} from "antd/lib/index";
import {Col, Row, message, Tabs} from "antd";
import {
  CheckCircleTwoTone,
  CreditCardOutlined,
  DollarCircleOutlined,
  EditOutlined,
  ExclamationCircleOutlined
} from "@ant-design/icons";
const {TabPane} = Tabs;
import MultiColorPicker from "../Picker/MultiColorPicker";
import Text from "antd/es/typography/Text";
import {Elements,} from "@stripe/react-stripe-js";
import StripeCheckoutForm from "./StripeCheckoutForm";
import moment from "moment";
import {loadStripe} from "@stripe/stripe-js/pure";

const {Title} = Typography;

class StoreConfigurationForm extends Component {


  constructor(props) {
    super(props);

    console.log(props)
    this.state = {
      logo: [],
      favIcon: [],
      logoPreview: "",
      favIconPreview: "",

      isUpdating: false,
      success: false,
      isPayout:false

    };


  }




  /**
   *
   * @param e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {
      if (!err) {
        let formData = new FormData();
        if (values.logo !== undefined) {
          formData.append('logo', this.state.logo[0].originFileObj);
        } else {
          formData.append('logo', "");
        }
        if (values.favIcon !== undefined) {
          formData.append('favIcon', this.state.favIcon[0].originFileObj);
        } else {
          formData.append('favIcon', "");
        }
        formData.append('_method', 'PUT')

        formData.append('theme', JSON.stringify({
          primaryColor: {
            hex: this.props.storeConfiguration.primaryColor.hex,
            rgb: this.props.storeConfiguration.primaryColor.rgb
          },
          secondaryColor: {
            hex: this.props.storeConfiguration.secondaryColor.hex,
            rgb: this.props.storeConfiguration.secondaryColor.rgb
          }
        }))

        this.setState({logo: [], favIcon: [], logoPreview: "", favIconPreview: ""});
        this.props.submitForm(formData);
      }
    });
  };
  /**
   *
   * @param e
   */
  onNormFile = e => {
    if (Array.isArray(e)) {
      return e;
    }
    return e && e.file;
  };


  /**
   *
   * @param info
   */
  handleChangeLogo = info => {
    let logo = [...info.fileList];
    logo = logo.slice(-1);

    if (this.validateInputType(logo[0].type)) {
      this.setState({...this.state, logo});
      const reader = new FileReader();
      reader.addEventListener("load", () => {
        this.setState({...this.state, logoPreview: reader.result});
      });
      reader.readAsDataURL(logo[0].originFileObj);
    }


  };


  /**
   *
   * @param info
   */
  handleChangeFavIcon = info => {
    let favIcon = [...info.fileList];
    favIcon = favIcon.slice(-1);

    if (this.validateInputType(favIcon[0].type)) {
      this.setState({...this.state, favIcon});
      const reader = new FileReader();
      reader.addEventListener("load", () => {
        this.setState({...this.state, favIconPreview: reader.result});
      });
      reader.readAsDataURL(favIcon[0].originFileObj);
    }
  }

  validateInputType = fileType => {
    const isJPG = fileType === "image/jpeg";
    if (!isJPG) {
      message.error("You can only upload JPG file!");
      return false;
    }
    return true;
  }


  onEditPaymentPressed = () => this.setState({...this.state, isEditable: !this.state.isEditable})


  render() {

    const {storeConfiguration} = this.props;
    const {getFieldDecorator} = this.props.form;

    const StoreConfiguration = () => {
      return <Form onSubmit={this.handleSubmit} layout={"vertical"} encType="multipart/form-data">
        <h4>Store Logo</h4>
        <div>
          <div style={{
            flexDirection: "row-reverse",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center"
          }}>

            <div style={{width: "102px", height: "102px", borderRadius: 5, border: "1px solid #444FE5"}}>
              <img
                src={this.state.logoPreview === "" ? storeConfiguration.logo : this.state.logoPreview}
                alt={"logo"}
                style={{width: "100px", height: "100px"}}
              />
            </div>
            <Form.Item label="" extra="">
              {getFieldDecorator("logo", {
                valuePropName: "logo",
                getValueFromEvent: this.onNormFile,
                name: "logo",
                label: "logo",
              })(
                <Upload
                  name="logo"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleChangeLogo}
                  beforeUpload={(e) => false}
                >
                  <Button>
                    <Icon type="upload"/> Select File
                  </Button>
                  {
                    this.state.logo.length ? <div>{this.state.logo[0].originFileObj.name}</div> : ""
                  }
                </Upload>,
              )}
            </Form.Item>
          </div>
        </div>
        <h4>Store FavIcon</h4>
        <div>
          <div style={{
            flexDirection: "row-reverse",
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center"
          }}>
            <div style={{width: "102px", height: "102px", borderRadius: 5, border: "1px solid #444FE5"}}>
              <img
                src={this.state.favIconPreview === "" ? storeConfiguration.favIcon : this.state.favIconPreview}
                alt={"favIcon"}
                style={{width: "100px", height: "100"}}
              />
            </div>
            <Form.Item label="" extra="">
              {getFieldDecorator("favIcon", {
                valuePropName: "favIcon",
                getValueFromEvent: this.onNormFile,
                name: "favIcon",
                label: "favIcon",
              })(
                <Upload
                  name="logo"
                  multiple={false}
                  showUploadList={false}
                  listType="picture"
                  onChange={this.handleChangeFavIcon}
                  beforeUpload={(e) => false}
                >
                  <Button>
                    <Icon type="upload"/> Select File
                  </Button>
                  {
                    this.state.favIcon.length ? <div>{this.state.favIcon[0].originFileObj.name}</div> : ""
                  }
                </Upload>,
              )}
            </Form.Item>
          </div>
        </div>
        {/*Color Palette*/}
        <h4>Store Color Palette</h4>
        <div>
          <MultiColorPicker
            primaryColor={storeConfiguration.primaryColor}
            secondaryColor={storeConfiguration.secondaryColor}
            handlePrimaryColorChange={this.props.handleChangePrimaryColor}
            handleSecondaryColorChange={this.props.handleChangeSecondaryColor}
          />
        </div>
        <div style={{display: "flex", justifyContent: "flex-end",}}>
          <Form.Item>
            <Button type="primary" htmlType="submit">
              Confirm
            </Button>
          </Form.Item>
        </div>
      </Form>
    }

    const SubscriptionConfiguration = ({handleOpenChangePayment,handlePressSubmitPayment}) => {
      return <div style={{paddingBottom: 10}}>
        <Row style={{marginBottom: 40}}>
          <Row><Title level={2}>General</Title></Row>
          <Row>
            <Col span={12}>
              <Row style={{marginBottom: 5}}>
                <DollarCircleOutlined/> Fee
              </Row>
              <Row>
                <Text>$9.99/month</Text>
              </Row>
            </Col>


            <Col span={12}>
              <Row style={{marginBottom: 5}}>
                <Text>End date</Text>
              </Row>
              <Row>
                <Text>{moment(storeConfiguration.current_period_end).format("DD/MM/YYYY")}</Text>
              </Row>
            </Col>
          </Row>


        </Row>
        <Row>
          <Col span={12}><Title level={2}>Billing Information</Title></Col>
          <Col style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "flex-end",
            alignContent: "center",
            justifyItems: "center",

          }} span={12}>< EditOutlined onClick={handleOpenChangePayment}/> {this.state.success &&
          <CheckCircleTwoTone style={{marginLeft: 10}} twoToneColor="#52c41a"/>}
          </Col>
        </Row>
        <Row>
          <Col span={12}>
            <Row style={{marginBottom: 5}}>
              <CreditCardOutlined/> Card
            </Row>
            <Row>
              <Text>**** **** **** {storeConfiguration?.card}</Text>
            </Row>
          </Col>
          <Col span={12}>
            <Row style={{marginBottom: 5}}>
              <Text>Expiry</Text>
            </Row>
            <Row>
              <Text>{(storeConfiguration?.exp_month?.length > 1) ? storeConfiguration?.exp_month?.length : '0' + storeConfiguration?.exp_month}/{(storeConfiguration?.exp_year+'').substr(2,4)}</Text>
            </Row>
          </Col>
        </Row>

        {
          (this.props.showPayment) &&
          <StripeCheckoutForm  onSubmit={handlePressSubmitPayment } ></StripeCheckoutForm>
        }

      </div>
    }


    const PayoutConfiguration = ({onSubmitCreateConnectPressed,onClickOpenDashboard,charge_enabled,warning}) => {

      return <div>
        <Row>
          <Row><Title level={2}>General</Title></Row>
          {(warning) &&  <Row style={{marginBottom:8,color:'red'}}><ExclamationCircleOutlined /> unable to create your dashboard please try again.</Row>}
          <Row>
            {
              (charge_enabled && charge_enabled =='true') ?<div>Open <a onClick={onClickOpenDashboard}>stripe dashboard</a> to check your payout.</div>: <div >Please setup your stripe connect account <a onClick={onSubmitCreateConnectPressed}>here</a> to start accepting orders from this store.</div>
            }
          </Row>

        </Row>
        <Row style={{display:'flex',justifyContent:'center',}}>
          <div style={{visibility:(this.props.isPayout)?'visible':'hidden'}}>
            <Spin  spinning={true}></Spin>
          </div>

        </Row>

      </div>
    }
    const stripePromise = loadStripe(process.env.MIX_STRIPE_API_KEY);
    return (
      <Elements stripe={stripePromise}>
        <div>
          <Tabs defaultActiveKey={"1"} onTabClick={this.props.handleTabBarPressed} activeKey={""+this.props.initPosition} size={12}>
            <TabPane tab="Theme" key="1">
              <StoreConfiguration></StoreConfiguration>
            </TabPane>
            <TabPane tab="Subscription" key="2">
              <SubscriptionConfiguration handleOpenChangePayment={this.props.handleOpenChangePayment} showPayment={this.props.showPayment} handlePressSubmitPayment={this.props.handlePressSubmitPayment}></SubscriptionConfiguration>
            </TabPane>
            <TabPane tab="Payout" key="3">
              <PayoutConfiguration onSubmitCreateConnectPressed={this.props.handlePressCreateConnectAccount} charge_enabled={this.props.storeConfiguration.charges_enabled} onClickOpenDashboard={this.props.onClickOpenDashboard} warning={(this.props?.storeConfiguration?.platform_store_id?.toString()==this?.props?.warning)}></PayoutConfiguration>
            </TabPane>
          </Tabs>
        </div>

      </Elements>
    );
  }
}


export default Form.create({name: "store_configuration"})(StoreConfigurationForm);
