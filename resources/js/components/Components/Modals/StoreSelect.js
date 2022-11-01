import React, { Component } from "react";
import { Modal, Button, Row, Col, Checkbox } from "antd";
import axios from "axios";

export default class StoreSelect extends Component {
  /**
   * @param props
   */
  constructor(props) {
    super(props);

    this.state = {
      storeCollection: []
    };
  }

  getStoreCollection() {
    axios
      .get("/platforms")
      .then(res => {
        if (res.data) {
          const stores = res.data.data;
          this.setState({
            storeCollection: stores
          });
        }
      })
      .catch(error => displayErrors(error));
  }

  storeName = (platform, store) => {
    const { name } = store;
    let storeName = name;
    if (platform.name === "Shopify") {
      storeName = name.split('.')[0];
    }
    return storeName;
  };

  getStoreModalContent = (defaultChecked = false) => {
    const { storeCollection } = this.state;

    return (
      <>
        {storeCollection.map((platform, val) => (
          <div
            key={platform.id}
            style={val > 0 ? { marginTop: "10px" } : { marginTop: "-5px" }}
          >

            {platform.name === 'Rutter' ?
              (
                <>
                  {
                    Object.keys(platform.stores).length ?
                      Object.keys(platform.stores).map((store) => (
                      <>
                        <h3>{store.charAt(0).toUpperCase() + store.slice(1)}</h3>
                        {platform.stores[store].map((integrations) => (
                          <Row key={integrations.id}>
                            <Col>
                              <Checkbox
                                defaultChecked={defaultChecked}
                                key={integrations.id}
                                name={String(integrations.id)}
                                onChange={this.props.handleChange}
                              >
                                {this.storeName(platform, integrations)}
                              </Checkbox>
                            </Col>
                          </Row>
                          ))}
                      </>
                    ))
                    : ""}
                </>
              )
              :
              (
                <>
                  {platform.stores.length > 0 && <h3>{platform.name}</h3>}
                  {platform.stores.length
                    ? platform.stores.map(store => (
                      <Row key={store.id}>
                        <Col>
                          <Checkbox
                            defaultChecked={defaultChecked}
                            key={store.id}
                            name={String(store.id)}
                            onChange={this.props.handleChange}
                          >
                            {this.storeName(platform, store)}
                          </Checkbox>
                        </Col>
                      </Row>
                    ))
                    : ""}
                </>
              )
            }
          </div>
        ))}
      </>
    );
  };

  getStoreModalFooter = () => {
    return [
      <Button key="back" onClick={this.props.onCancel}>
        Cancel
      </Button>,
      <Button key="start" type="primary" onClick={this.props.onOk}>
        Continue
      </Button>
    ];
  };

  componentDidMount() {
    this.getStoreCollection();
  }

  /**
   *
   * @returns {JSX.Element}
   */
  render() {
    const { visible, onCancel, onOk, defaultChecked } = this.props;
    return (
      <>
        <Modal
          title="Select Stores"
          visible={visible}
          onCancel={onCancel}
          onOk={onOk}
          footer={this.getStoreModalFooter()}
          width="30%"
        >
          {this.getStoreModalContent(defaultChecked)}
        </Modal>
      </>
    );
  }
}
