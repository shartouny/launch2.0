import React, {Component} from 'react';
import {
  Modal,
  Button,
  Table,
  Avatar
} from 'antd';

export default class ForceSyncProductsModal extends Component {

  constructor(props) {
    super(props);
  }

  render() {

    const {
      onOk,
      visible,
      onCancel,
      forceRowSelection,
      previouslySyncedProductsData,
    } = this.props

    const columns = [
      {
        title: '',
        width: 72,
        dataIndex: 'mainImageThumbUrl',
        key: 'mainImageThumbUrl',
        render: (value, record) => {
          return (
            <div>
              <Avatar
                style={{ background: 'none' }}
                shape="square"
                size={64}
                icon={value ? <img src={value} /> : ''}
              />
            </div>
          );
        },
      },
      {
        title: 'Title',
        dataIndex: 'name',
        key: 'name',
      },
      {
        title: 'Stores',
        dataIndex: 'store',
        key: 'store',
      },
    ];

    return (
      <Modal
        title='Send to store'
        visible={visible}
        centered
        onCancel={onCancel}
        width="50%"
        footer={[
          <Button
            key={1}
            onClick={onCancel}>
            Cancel
          </Button>,
          <Button key={2} type="primary" onClick={onOk}>
            Send
          </Button>,
        ]}>
        {previouslySyncedProductsData.length > 1 ? 'These products' : 'This product' } already exist, send anyway?

        <Table
          style={{marginTop: '25px'}}
          rowKey="id"
          columns={columns}
          dataSource={previouslySyncedProductsData}
          pagination={false}
          scroll={{ y: 400 }}
          rowSelection={forceRowSelection}
        />
      </Modal>
    );
  }
}
