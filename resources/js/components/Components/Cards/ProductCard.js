import React, { Component } from 'react';
import ReactHtmlParser from 'react-html-parser';
import { Card, Icon, Tooltip, Drawer } from 'antd/lib/index';
import { Button } from "antd";

const { Meta } = Card;

/**
 *
 */
export default class ProductCard extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      visible: false,
      imageLoading: true
    }
  };

  /**
   *
   */
  showInfo = () => {
    this.setState({
      visible: true,
    });
  };
  /**
   *
   */
  onClose = () => {
    this.setState({
      visible: false,
    });
  };
  /**
   *
   */
  chooseStore = () => {
    const {
      chooseStore,
      product,
    } = this.props;

    chooseStore(product);
  };
  /**
   *
   * @returns {[*, *]}
   */
  showActions = () => {
    const {
      inBatch,
      batchState,
      batchTheme,
      product,
      batchProducts,
    } = this.props;

    return [
      <Tooltip title=""><Button type="link" style={{fontWeight:500, height:'100%'}} onClick={() => this.chooseStore()}>Start Design</Button></Tooltip>,
      <span style={{ display: 'flex', justifyContent: 'space-evenly'}}>
        {inBatch ? <Tooltip title="Batch Design">
        <Button onClick={(event) => batchProducts(event, product)}
                className={batchState ? "batch-design-btn in-batch" : "batch-design-btn"}
                style={{ border: 0 }}/>
        </Tooltip> : <span style={{width:36}}/>}
        <Tooltip title="Info">
          <Button onClick={this.showInfo} className="product-info-btn" style={{ border: 0 }}/>
        </Tooltip>
      </span>
    ];
  };

  /**
   *
   * @returns {*}
   */
  render() {
    const {
      product,
      desc,
    } = this.props;
    const { imageLoading } = this.state;
    const {
      name,
      thumbnail,
      description,
    } = product;

    return (
      <div>
        <Card
          className="product-card"
          style={{ width: '100%' }}
          actions={this.showActions()}
          cover={
            <>
              <img
                alt={name}
                src={thumbnail}
                onClick={this.chooseStore}
                className={imageLoading ? 'image' : 'image image-loaded'}
                onLoad={() => this.setState({ imageLoading: false })}
              />
              <div style={imageLoading ? { width: '100%', paddingTop: '100%' } : {
                width: '100%',
                paddingTop: '0',
                display: 'none'
              }}/>
            </>
          }
        >
          <Meta
            className="product-card-meta"
            title={name}
            description={desc}
            onClick={this.chooseStore}
            style={{ cursor: 'pointer' }}
          />
        </Card>
        <Drawer
          title={name}
          placement="right"
          closable={false}
          onClose={this.onClose}
          visible={this.state.visible}
          width="40%"
        >
          <p>{ReactHtmlParser(description)}</p>
        </Drawer>
      </div>
    )
  }
};
