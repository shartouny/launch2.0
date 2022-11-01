import React, { Component } from 'react';
import { Row, Col, Typography, message, Form, Switch, Input } from 'antd';
import AccountInfoForm from '../../Components/Forms/AccountInformationForm';
import ShippingLabelForm from '../../Components/Forms/ShippingLabelDetailsForm';
import PackingSlipForm from '../../Components/Forms/PackingSlipDetailsForm';
import axios from "axios";
import PremiumCanvasDetailsForm from "../../Components/Forms/PremiumCanvasDetailsForm";
import AccountSettingsDeleteModal from "../../Components/Modals/AccountSettingsDeleteModal";
import { displayErrors } from '../../../utils/errorHandler';
import AddressForm from "../../Components/Forms/AddressForm";
import BillingSettingsForm from "../../Components/Forms/BillingSettingsForm";
import ChangePasswordForm from "../../Components/Forms/ChangePasswordForm";
import ChangeAccountEmailForm from "../../Components/Forms/ChangeAccountEmailForm";
import DeveloperSettingsForm from "../../Components/Forms/DeveloperSettingsForm";

const { Title } = Typography;

export default class AccountSettings extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);

    this.state = {
      accountId: '',
      token: '',
      account: [],
      accountInfo: [],
      accountInfoExists: false,
      shippingLabel: [],
      shippingLabelExists: false,
      packingSlip: [],
      packingSlipExists: false,
      packingSlipLogo: [],
      packingSlipLogoExists: false,
      premiumCanvasCardFront: [],
      premiumCanvasCardFrontExists: false,
      premiumCanvasCardBack: [],
      premiumCanvasCardBackExists: false,
      premiumCanvasBoxSticker: [],
      premiumCanvasBoxStickerExists: false,
      premiumCanvasBackLogo: [],
      premiumCanvasBackLogoExists: false,
      showModalVisibility: false,
      idToDelete: '',
      isOrderHold: false,
      settings: [],
      countries: [],
      dailyMaxChargeValue: 0,
      dailyMaxChargeEnabled: false,
      userInfo: [],
      isChangePassword: false,
      authenticated: false,
      isNotTeelaunch: false
    };
  };

  /**
   *
   */
  componentDidMount() {
    this.fetchIndex();
    this.getCountries();
  };

  getCountries = () => {
    axios.get(`countries`).then(res => {
      const { data } = res.data;
      this.setState({ countries: data });
    }).catch(error => displayErrors(error))
      .finally();
  };

  /**
   *
   */
  fetchIndex() {
    axios.get(`/account`)
      .then(res => {
        const { data } = res.data;

        let shippingLabelData;
        let shippingLabelDataExists;
        let packingSlipData;
        let packingSlipDataExists;
        let accountInfoData;
        let accountInfoDataExists;
        let packingSlipLogoData;
        let packingSlipLogoDataExists;
        let premiumCanvasCardFrontData;
        let premiumCanvasCardFrontDataExists;
        let premiumCanvasCardBackData;
        let premiumCanvasCardBackDataExists;
        let premiumCanvasBoxStickerData;
        let premiumCanvasBoxStickerDataExists;
        let premiumCanvasBackLogoData;
        let premiumCanvasBackLogoDataExists;

        if (data) {
          if (data.shippingLabel) {
            // shippingLabel
            if (data.shippingLabel.shippingAddressId !== null) {
              shippingLabelData = data.shippingLabel.shippingAddress;
              shippingLabelDataExists = true;
            } else {
              shippingLabelData = [];
              shippingLabelDataExists = false;
            }

            // packingSlip
            if (data.shippingLabel.email !== null) {
              packingSlipData = data.shippingLabel;
              packingSlipDataExists = true;
            } else {
              packingSlipData = [];
              packingSlipDataExists = false;
            }

            // accountInfo
            if (data.shippingLabel.billingAddressId !== null) {
              accountInfoData = data.shippingLabel.billingAddress;
              accountInfoData['vat'] = data.shippingLabel.vat ?? '';

              accountInfoDataExists = true;
            } else {
              accountInfoData = [];
              accountInfoDataExists = false;
            }

          } else {
            shippingLabelData = [];
            shippingLabelDataExists = false;
            packingSlipData = [];
            packingSlipDataExists = false;
            accountInfoData = [];
            accountInfoDataExists = false;
          }

          if (data.brandingImages) {
            data.brandingImages.map((brandingImage) => {
              // packingSlipLogo
              if (brandingImage.brandingImageType['name'] === 'Packing Slip Logo') {
                packingSlipLogoData = brandingImage;
                packingSlipLogoDataExists = true;
              }

              // premium canvas card front
              if (brandingImage.brandingImageType['name'] === 'Insert Card Front') {
                premiumCanvasCardFrontData = brandingImage;
                premiumCanvasCardFrontDataExists = true;
              }

              // premium canvas card back
              if (brandingImage.brandingImageType['name'] === 'Insert Card Back') {
                premiumCanvasCardBackData = brandingImage;
                premiumCanvasCardBackDataExists = true;
              }

              // premium canvas card back
              if (brandingImage.brandingImageType['name'] === 'Outside Box Sticker') {
                premiumCanvasBoxStickerData = brandingImage;
                premiumCanvasBoxStickerDataExists = true;
              }

              // premium canvas Back Logo
              if (brandingImage.brandingImageType['name'] === 'Canvas Back Logo') {
                premiumCanvasBackLogoData = brandingImage;
                premiumCanvasBackLogoDataExists = true;
              }
            });
          } else {
            packingSlipLogoData = [];
            packingSlipLogoDataExists = false;
            premiumCanvasCardFrontData = [];
            premiumCanvasCardFrontDataExists = false;
            premiumCanvasCardBackData = [];
            premiumCanvasCardBackDataExists = false;
            premiumCanvasBoxStickerData = [];
            premiumCanvasBoxStickerDataExists = false;
            premiumCanvasBackLogoData = [];
            premiumCanvasBackLogoDataExists = false;
          }

          this.setState({
            accountId: data.id,
            email: data.user.email,
            token: data.user.publicToken,
            account: data,
            userInfo: data.user,
            accountInfo: accountInfoData,
            accountInfoExists: accountInfoDataExists,
            shippingLabel: shippingLabelData,
            shippingLabelExists: shippingLabelDataExists,
            packingSlip: packingSlipData,
            packingSlipExists: packingSlipDataExists,
            packingSlipLogo: packingSlipLogoData,
            packingSlipLogoExists: packingSlipLogoDataExists,
            premiumCanvasCardFront: premiumCanvasCardFrontData,
            premiumCanvasCardFrontExists: premiumCanvasCardFrontDataExists,
            premiumCanvasCardBack: premiumCanvasCardBackData,
            premiumCanvasCardBackExists: premiumCanvasCardBackDataExists,
            premiumCanvasBoxSticker: premiumCanvasBoxStickerData,
            premiumCanvasBoxStickerExists: premiumCanvasBoxStickerDataExists,
            premiumCanvasBackLogo: premiumCanvasBackLogoData,
            premiumCanvasBackLogoExists: premiumCanvasBackLogoDataExists,
          });

          this.getAccountSettings();
        }
      })
      .catch(error => displayErrors(error));
  }

  /**
   *
   * @param childData
   */
  handleAccountInfo = (childData) => {
    this.setState({ accountInfo: childData });
    this.onCreateAccountInfo(childData);
  };

  /**
   *
   * @param childData
   */
  handleDailyMaxCharge = (childData) => {
    this.setState({ dailyMaxChargeValue: childData['dailyMaxChargeValue'] });
    this.setState({ dailyMaxChargeEnabled: childData['dailyMaxChargeEnabled'] });
    this.onCreateDailyMaxCharge(childData);
  };

  /**
   *
   * @param childData
   */
  handleDailyMaxChargeStatusChange = (data) => {
    this.setState({ dailyMaxChargeEnabled: data });
  };

  /**
   *
   * @param childData
   */
  handleShippingLabel = (childData) => {
    this.setState({ shippingLabel: childData });
    this.onCreateShippingLabel(childData);
  };
  /**
   *
   * @param childData
   */
  handlePackingSlip = (childData) => {
    this.setState({ packingSlip: childData });
    this.onCreatePackingSlip(childData);
  };
  /**
   *
   * @param childData
   */
  handlePremiumCanvas = (childData) => {
      this.onCreatePremiumCanvas(childData);
  };
  /**
   *
   * @param id
   */
  handlePackingSlipDeleteModal = (id) => {
    // set state visible to true
    this.setState({
      showModalVisibility: true,
      idToDelete: id,
    });
    // this will then trigger the confirmation modal
  };
  /**
   *
   * @param id
   */
  handlePremiumCanvasDeleteModal = (id) => {
    // set state visible to true
    this.setState({
      showModalVisibility: true,
      idToDelete: id,
    });
    // this will then trigger the confirmation modal
  };
  /**
   *
   * @param data
   */
  handleCancel = (data) => {
    // set state visible to false
    this.setState({ showModalVisibility: false });
  };
  /**
   *
   * @param data
   */
  handleDelete = (data) => {
    // set state visible to false
    this.setState({ showModalVisibility: false });
    this.onDeleteImage(data);
  };
  /**
   *
   * @param data
   */
  onCreateAccountInfo = (data) => {
    axios
      .post('/account/address', {
        accountInfo: data,
      })
      .then(res => {
        if (res.status === 200) {
          message.success('Account Info Saved!');
        }
      })
      .catch(error => displayErrors(error));
  };

  /**
   *
   * @param data
   */
  onCreateDailyMaxCharge = (data) => {
    axios
      .post('/account/settings', {
        key: 'daily_max_charge_enabled',
        value: data['dailyMaxChargeEnabled'],
      })
      .then(res => {
        if (res.status === 200) {
          axios
            .post('/account/settings', {
              key: 'daily_max_charge',
              value: data['dailyMaxChargeValue'],
            })
            .then(res => {
              if (res.status === 200) {
                message.success('Billing Settings Saved!');
              }
            })
            .catch(error => displayErrors(error));
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   *
   * @param data
   */
  onCreateShippingLabel = (data) => {
    axios
      .post('/account/shipping-label', {
        shippingLabel: data,
      })
      .then(res => {
        if (res.status === 200) {
          message.success('Shipping Label Details Saved!');
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   * @param data
   */
  onUpdateShippingLabel = (data) => {
    const {
      accountId,
    } = this.state;

    axios
      .put(`/account/shipping-label/${accountId}`, {
        shippingLabel: data,
      })
      .then(res => {
        if (res.status === 200) {
          message.success('Shipping Label Details Updated!');
          this.fetchIndex();
          this.setState({
            shippingLabelExists: true,
          });
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   *
   * @param data
   */
  onCreatePackingSlip = (data) => {
    axios
      .post('/account/packing-slip', data)
      .then(res => {
        if (res.status === 200) {
          message.success('Packing Slip Details Saved!');
          this.fetchIndex();
          this.setState({
            packingSlipExists: true,
            packingSlipLogoExists: true,
          });
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   *
   * @param data
   */
  onCreatePremiumCanvas = (data) => {
    axios
      .post('/account/premium-canvas', data)
      .then(res => {
        if (res.status === 200) {
          const { data } = res.data;
          message.success('Premium Canvas Saved!', data);
          this.fetchIndex();
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   * @param id
   */
  onDeleteImage = (id) => {
    axios.delete(`/account/account-branding-images/${id}`)
      .then(res => {
        if (res.status === 200) {
          message.success('Image deleted!');
          this.fetchIndex();
        }
      }).catch(error => displayErrors(error))
  };
  /**
   *
   */
  getAccountSettings = () => {
    axios
      .get('/account/settings')
      .then(res => {
        if (res.status === 200) {
          const { data } = res.data;

          const orderHoldSetting = data.find(s => s.key === 'order_hold');
          const orderIgnoreSetting = data.find(s => s.key === 'ignore_not_teelaunch_order')
          const dailyMaxChargeValueSetting = data.find(s => s.key === 'daily_max_charge');
          const dailyMaxChargeEnabledSetting = data.find(s => s.key === 'daily_max_charge_enabled');

          this.setState({
            settings: data,
            isOrderHold: orderHoldSetting ? orderHoldSetting.value === '1' : false,
            isNotTeelaunch: orderIgnoreSetting ? orderIgnoreSetting.value === '1' : false,
            dailyMaxChargeValue: dailyMaxChargeValueSetting ? dailyMaxChargeValueSetting.value : 0,
            dailyMaxChargeEnabled: dailyMaxChargeEnabledSetting ? dailyMaxChargeEnabledSetting.value !== '0' : false
          });
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   * @param checked
   */
  onUpdateOrderHold = (checked) => {

    const {
      settings,
    } = this.state;

    const orderHoldSetting = settings.find(s => s.key === 'order_hold');

    axios
      .post(`/account/settings`, {
        key: 'order_hold',
        value: checked,
      })
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;

          if (data) {
            message.success('Order hold updated');
          }
        }
      })
      .catch(error => {
        displayErrors(error);
        this.setState({ isOrderHold: !checked });
      });
  };

  /**
   * @param checked
   */
  onUpdateIgnoreNotTeelaunch = (checked) => {

    const {
      settings,
    } = this.state;

    const orderIgnoreSetting = settings.find(s => s.key === 'ignore_not_teelaunch_order');

    axios
      .post(`/account/settings`, {
        key: 'ignore_not_teelaunch_order',
        value: checked,
      })
      .then(res => {
        if (res.status === 200 || res.status === 201) {
          const { data } = res.data;

          if (data) {
            message.success('Order ignore updated');
          }
        }
      })
      .catch(error => {
        displayErrors(error);
        this.setState({ isNotTeelaunch: !checked });
      });
  };
  /**
   * @param checked
   */
  onOrderHoldToggle = (checked) => {
    this.setState({
      isOrderHold: checked,
    });

    this.onUpdateOrderHold(checked);
  };

  onOrderNotTeelaunchToggle = (checked) => {
    this.setState({isNotTeelaunch: checked})

    this.onUpdateIgnoreNotTeelaunch(checked)
  }

  handleGenerateToken = () => {
    axios
      .get('/account/generateToken')
      .then(response => {
        const { data } = response;

        this.setState({
          token : data.token
        })
        message.success('API token generated successfully!');

      })
      .catch(error => displayErrors(error));
  }

  handleRevokeToken = () => {
    axios
      .get('/account/revokeToken')
      .then(response => {
        const { data } = response;

        this.setState({
          token : ''
        })
        message.success('API token revoked successfully! You can no longer use it');

      })
      .catch(error => displayErrors(error));
  }

  render() {
    return (
      <div style={{ marginBottom: '75px' }}>
        <Row>
          <Col>
            <Title>Account Settings</Title>
          </Col>
        </Row>
        <Row>
          <Col
            sm={24}
            md={{ span: 10 }}
            lg={{ span: 7 }}
            className="settings-container"
          >
            <Title level={2}>Account Info</Title>
            <AccountInfoForm
              handleAccountInfo={this.handleAccountInfo}
              userInfo={this.state.userInfo}
              accountInfo={this.state.accountInfo}
              countries={this.state.countries}
              vat={this.state.vat || ''}
              company={this.state.account.name || ''}
              firstName={this.state.userInfo.firstName}
              lastName={this.state.userInfo.lastName}
              phoneNumber={this.state.phoneNumber}
            />
          </Col>
          <Col
            sm={24}
            md={{ span: 10, offset: 2 }}
            lg={{ span: 7, offset: 1 }}
            className="settings-container"
          >
            <Title level={2}>Return Label Details</Title>
            <ShippingLabelForm
              handleShippingLabel={this.handleShippingLabel}
              shippingLabel={this.state.shippingLabel}
              countries={this.state.countries}
              company={this.state.account.name || ''}
            />
          </Col>
          <Col
            sm={24}
            md={{ span: 10 }}
            lg={{ span: 8, offset: 1 }}
            className="settings-container"
            style={{ paddingBottom: 130 }}
          >
            <Title level={2}>Packing Slip Details</Title>
            <PackingSlipForm
              handlePackingSlip={this.handlePackingSlip}
              packingSlip={this.state.packingSlip}
              packingSlipLogo={this.state.packingSlipLogo}
              handlePackingSlipDeleteModal={this.handlePackingSlipDeleteModal}
            />
          </Col>
        </Row>
        <Row>
          <Col className="settings-container">
            <Title level={2}>Billing Settings</Title>
            <BillingSettingsForm
              handleDailyMaxCharge={this.handleDailyMaxCharge}
              dailyMaxChargeValue={this.state.dailyMaxChargeValue}
              dailyMaxChargeEnabled={this.state.dailyMaxChargeEnabled}
              dailyMaxChargeStatusChange={this.handleDailyMaxChargeStatusChange}
            />
          </Col>
        </Row>
        <Row>
          <Col
            xs={24}
            sm={24}
            md={24}
            className="settings-container"
          >
            <div style={{ flexDirection: 'row', justifyContent: 'center' }}>
              <Title level={2}>Premium Canvas Branding</Title>
              <PremiumCanvasDetailsForm
                handlePremiumCanvas={this.handlePremiumCanvas}
                premiumCanvasCardFront={this.state.premiumCanvasCardFront}
                premiumCanvasCardBack={this.state.premiumCanvasCardBack}
                premiumCanvasBoxSticker={this.state.premiumCanvasBoxSticker}
                premiumCanvasBackLogo={this.state.premiumCanvasBackLogo}
                handlePremiumCanvasDeleteModal={this.handlePremiumCanvasDeleteModal}
              />
            </div>
          </Col>
        </Row>
        <Row>
          <Col className="settings-container">
            <Title level={2}>Order Processing</Title>
            <Row style={{ marginBottom: '20px'}}>
              <Col>
                <div style={{ display: 'flex', alignItems: 'center' }}>
                  <div style={{ fontSize: '22px', paddingLeft: '10px', paddingRight: '10px', width:'35%' }}>
                    Order Hold
                  </div>
                  <Switch size='small' style={{ minWidth: '40px' }} onChange={this.onOrderHoldToggle} checked={this.state.isOrderHold}/>
                </div>
                <div style={{ fontSize: '14px', paddingLeft: '10px' }}>
                  Hold new orders for review and approval of design before allowing processing.
                </div>
              </Col>
            </Row>

            <Row style={{ marginBottom: '20px'}}>
              <Col>
                <div style={{ display: 'flex', alignItems: 'center' }}>
                  <div style={{ fontSize: '22px', paddingLeft: '10px', paddingRight: '10px', width:'35%' }}>
                    Ignore all products fulfilled by other vendors
                  </div>
                  <Switch size='small' style={{ minWidth: '40px' }} onChange={this.onOrderNotTeelaunchToggle} checked={this.state.isNotTeelaunch}/>
                </div>
                <div style={{ fontSize: '14px', paddingLeft: '10px' }}>
                  The system will automatically ignore any products not created in the teelaunch app or linked to a teelaunch product.
                </div>
              </Col>
            </Row>
          </Col>
        </Row>
        <Row>
          <Col className="settings-container">
            <Title level={2}>Developer Settings</Title>
            <DeveloperSettingsForm
              publicToken={this.state.token}
              handleRevokeToken={this.handleRevokeToken}
              handleGenerateToken={this.handleGenerateToken}
            />
          </Col>
        </Row>

        <Row>
          <Col className="settings-container">
            <div style={{ flexDirection: 'row', justifyContent: 'center' }}>
              <Title level={2}>Change Password</Title>
             <ChangePasswordForm/>
            </div>
          </Col>
        </Row>
        <Row>
          <Col className="settings-container">
            <div style={{ flexDirection: 'row', justifyContent: 'center' }}>
              <Title level={2}>Change Account Email</Title>
             <ChangeAccountEmailForm/>
            </div>
          </Col>
        </Row>

        <AccountSettingsDeleteModal
          handleDelete={this.handleDelete}
          handleCancel={this.handleCancel}
          showModalVisibility={this.state.showModalVisibility}
          idToDelete={this.state.idToDelete}
        />
      </div>
    );
  }
}
