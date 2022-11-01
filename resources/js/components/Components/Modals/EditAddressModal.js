import { Alert, message, Modal, Tabs } from 'antd';
import React, {Component} from 'react';
import EditAddressForm from '../Forms/EditAddressForm';
import axios from 'axios';

export default class EditAddressModal extends Component {
  constructor(props) {
    super(props);
    this.state = {
      billingAddress: {},
      shippingAddress: {},
      showSuccessAlert: false,
      error: '',
      showErrorAlert: false
    }
  }

  editAddress = (id, data) => {
    axios.post(`/addresses/${id}`, {
      _method: 'PUT',
      ...data
    }).then(({data}) => {
      if (data.success) {
        const log = {
          id: data.log.id,
          orderId: data.log.order_id,
          message: data.log.message,
          messageType: data.log.message_type,
          updatedAt: data.log.updated_at,
          createdAt: data.log.created_at
        }

        this.props.onCloseModal();
        this.props.onUpdateLogs(log);
        message.success(data.message);
      }

      setTimeout(() => {
        this.setState({showSuccessAlert: false})
      }, 5000)

    }).catch(error => {
      this.setState({
        error: error.response.data.message,
        showErrorAlert: true
      }, () => {
        setTimeout(() => {
          this.setState({showErrorAlert: false})
        }, 5000)
      })
    })
  }

  render() {
    const {
      visible,
      onCloseModal,
      orderData,
      editShippingAddress
    } = this.props;
    const { shippingAddress } = orderData;
    const { showSuccessAlert, error, showErrorAlert } = this.state;

    return (
      <Modal visible={visible}
        footer={false}
        title="Edit Order Address"
        onCancel={onCloseModal}>
          {/* Success Alert  */}
          {showSuccessAlert && <Alert closable={true}
            message="Address updated successfully"
            type="success"
            showIcon />}
          {/* Error Alert */}
          {showErrorAlert && <Alert closable={true}
            type="error"
            showIcon
            message={error} />}
          <EditAddressForm
            editAddress={editShippingAddress}
            address={shippingAddress}
            onEdit={this.editAddress} />
      </Modal>
    )
  }
}
