import React, {Component} from 'react';
import { Row, Col, Table } from 'antd';
import ProductCard from '../../Components/Cards/ProductCard';

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

export default class Dashboard extends Component {
  render() {
    return (
        <div>
          <Row gutter={ {xs: 8, md: 24} }>
            <Col xs={24} md={16}>
              <h1>Latest Orders</h1>
              <Table columns={columns} dataSource={data} size="middle" />
              <h1>My Products</h1>
              <Row gutter={ {xs: 8, md: 24} }>

              </Row>
            </Col>
            <Col xs={24} md={8}>
              <h1>Latest News</h1>
            </Col>
          </Row>
        </div>
    );
  }
}