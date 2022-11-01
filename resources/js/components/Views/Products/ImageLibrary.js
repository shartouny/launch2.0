import React, {Component} from 'react';
import {Row, Col, Menu, AutoComplete, Icon, Input } from 'antd/lib/index';
import LibraryCard from "../../Components/Cards/LibraryCard";


const dataSource = ['12345', '23456', '34567'];
export default class ImageLibrary extends Component {

  buildLibrary = () => {
    let cards = []

    for (let i = 0; i < 9; i++) {
        cards.push(
            <Col xs={24} md={4} style={{marginTop: 15}} key={i}>
              <LibraryCard />
            </Col>
        )
      }
    return cards
  }
  render() {
    return (
      <div>
        <Row gutter={ {xs: 8, md: 24, lg: 32} }>
          <Col xs={24} md={4}>
            <AutoComplete className="certain-category-search" dataSource={dataSource} >
              <Input suffix={<Icon type="search" className="certain-category-icon" />} />
            </AutoComplete>
            <Menu
                onClick={this.handleClick}
                style={{ width: '100%' }}
                defaultSelectedKeys={['1']}
                mode="inline"
            >
                <Menu.Item key="1">All</Menu.Item>
                <Menu.Item key="2">Large</Menu.Item>
                <Menu.Item key="3">Medium</Menu.Item>
                <Menu.Item key="4">Small</Menu.Item>
            </Menu>
          </Col>
          <Col xs={24} md={19}>
            <Row gutter={16}>
              {this.buildLibrary()}
            </Row>
          </Col>
        </Row>
      </div>
    )
  }
};