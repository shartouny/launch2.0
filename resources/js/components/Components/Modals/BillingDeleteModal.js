import React, { Component } from 'react';
import {
  Button,
  Modal,
} from 'antd/lib/index';

/**
 *
 */
export default class BillingDeleteModal extends Component {
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
   */
  onConfirmDelete = () => {
    this.setState({showModalVisibility: false});
    this.props.handleDelete();
  };
  /**
   *
   * @returns {*}
   */
  render() {
    const {
      showModalVisibility,
      title,
      content,
      loading,
    } = this.props;

    return (
      <div>
        <Modal
          title={title}
          visible={showModalVisibility}
          centered
          content={content}
          onCancel={this.handleCancel}
          footer={[
            <Button
              key={1}
              onClick={this.handleCancel}
            >
              Cancel
            </Button>,
            <Button
              key={2}
              type='danger'
              onClick={this.onConfirmDelete}
              loading={loading}
            >
              Delete
            </Button>,
          ]}
        >
          {content}
        </Modal>
      </div>
    );
  }
}
