import React, {Component} from 'react';
import {
  Button,
  Modal,
} from 'antd/lib/index';

/**
 *
 */
export default class AccountSettingsDeleteModal extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      visible: false,
      confirmModal: false,
      showModalVisiblity: false,
    };
  };

  /**
   *
   */
  handleCancel = () => {
    this.setState({showModalVisibility: false});
    this.props.handleCancel(this.state.showModalVisibility);
  };
  /**
   *
   * @param {{}} id
   */
  onConfirmDeleteImage = (id) => {
    this.setState({showModalVisibility: false});
    this.props.handleDelete(id);
  };
  /**
   *
   * @returns {*}
   */
  render() {
    const {
      showModalVisibility,
      idToDelete,
    } = this.props;

    return (
      <div>
        <Modal
          title="Delete image"
          visible={showModalVisibility}
          centered
          content={<p>Confirm Image Delete</p>}
          footer={[
            <Button
              key={1}
              onClick={() => this.handleCancel()}
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type="danger"
              onClick={() => this.onConfirmDeleteImage(idToDelete)}
            >
              Delete
            </Button>,
          ]}
        >
        </Modal>
      </div>
    );
  }
}
