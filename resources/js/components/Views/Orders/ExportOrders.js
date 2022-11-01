import React, {Component} from 'react';
import { Row, Col, Table, Typography, DatePicker, Button } from 'antd';

const { Title } = Typography;
const { MonthPicker, RangePicker, WeekPicker } = DatePicker;

function onChange(date, dateString) {

}
const columns = [
  {
    title: 'Order Name',
    dataIndex: 'name',
  },
  {
    title: 'Order Number',
    dataIndex: 'number',
  },
  {
    title: 'Order Total',
    dataIndex: 'total',
  },
];
const data = [
  {
    key: '1',
    name: 'Order Name',
    number: 12345,
    total: '$00.00',
  },
  {
    key: '2',
    name: 'Order Name',
    number: 12345,
    total: '$00.00',
  },
  {
    key: '3',
    name: 'Order Name',
    number: 12345,
    total: '$00.00',
  },
  {
    key: '4',
    name: 'Order Name',
    number: 12345,
    total: '$00.00',
  }
];
export default class ExportOrders extends Component {

  render() {
    return (
        <div>
          <Row>
            <Col span={8} >
              <Title level={1}>Export Orders</Title>
              <RangePicker onChange={onChange} />

            </Col>
            <Col span={15} offset={1}>
              <Button type="primary">Export</Button>
              <Table columns={columns} dataSource={data} size="middle" />
            </Col>
          </Row>
        </div>
    );
  }
}
