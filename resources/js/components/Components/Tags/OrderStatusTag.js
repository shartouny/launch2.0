import React, { Component } from "react";
import { Tag } from "antd";

/**
 *
 */
export default class OrderStatusTag extends Component {
  constructor(props) {
    super(props);
  }

  valueReadable = statusReadable => {
    const regex = new RegExp(/_/, "g");
    return statusReadable.replace(regex, " ");
  };

  colorFromId = statusId => {
    // const HOLD = 0;
    // const PENDING = 1;
    // const PROCESSING_PAYMENT = 20;
    // const PAID = 30;
    // const PROCESSING_PRODUCTION = 40;
    // const PRODUCTION = 50;
    // const PARTIAL_SHIPPED = 60;
    // const SHIPPED = 70;
    // const PROCESSING_FULFILLMENT = 80;
    // const FULFILLED = 90;
    // const CANCELLED = 100;
    // const IGNORED = 110;

    switch (statusId) {
      case 0:
        return "#ffcf00";
      case 5:
        return "#ffcf00";
      case 10:
        return "#ff8908";
      case 20:
        return "#ff8908";
      case 30:
        return "#41aa7f";
      case 40:
        return "#41aa7f";
      case 50:
        return "#1c8645";
      case 60:
        return "#429bd1";
      case 70:
        return "#3c66f1";
      case 80:
        return "#3c66f1";
      case 90:
        return "#ada9a9";
      case 2:
        return "#e62c2c";
      default:
        return "#ada9a9";
    }
  };

  render() {
    return (
      <Tag
        color={this.colorFromId(this.props.status)}
        style={{
          textAlign: "center",
          minWidth: 100,
          fontSize: 12,
          ...this.props.style
        }}
      >
        {this.valueReadable(this.props.statusReadable)}
      </Tag>
    );
  }
}
