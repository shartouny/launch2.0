import React, {Component} from 'react';
import { Checkbox, Tooltip, Typography, Divider } from 'antd';

const {Title} = Typography;

export default class SwatchSelector extends Component {
  constructor(props) {
    super(props);
    this.handleClick = this.handleClick.bind(this);
    this.state = {active: 0};
  }
  onChange = e => {

  };

  handleClick = (key, e) => {

    this.setState({
      active: key,
    });
    this.props.parentCallback(key);
  };
  buildSwatches = (item) => {
    if(item.thumbnail){
      return (
          <img src={item.thumbnail} alt={item.title} />
      )
    } else {
      return ''
    }
  }

  render() {
    if(this.props.swatches) {
      return (
          <div>
            <Title level={2}>{this.props.title}</Title>
            <div>
              {this.props.swatches.map((item, key) => {
                return (
                    <div key={key} className="swatch-block">
                      <Tooltip title={item.label}>
                        <div key={key} onClick={this.handleClick.bind(this, key)} className={"swatch-thumb  " + (this.state.active == key ? 'active' : '')} style={{background: (item.thumbnail ? '' : item.color)}}>
                          {this.buildSwatches(item)}
                        </div>
                      </Tooltip>
                      <Checkbox onChange={this.onChange}></Checkbox>
                    </div>
                )
              })}
            </div>
            <Divider/>
          </div>
      )
    } else {
      return ''
    }
  }
}
