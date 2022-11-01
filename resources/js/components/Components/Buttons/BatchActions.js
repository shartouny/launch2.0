import React, { Component } from 'react';

export default class BatchActions extends Component {
  constructor(props) {
    super(props);
  }
  componentDidMount() {

  }

  render() {
    const { hasSelected, selectedRowKeys } = this.props;
    return <div className="button-group">
      {hasSelected ? `${selectedRowKeys.length} selected ` : ''}
      {this.props.children}
    </div>
  }
}
