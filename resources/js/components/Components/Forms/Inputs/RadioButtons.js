import React, {Component} from 'react';
import {Radio, Typography, Divider} from 'antd/lib/index';

const {Title} = Typography;

const radioStyle = {
  display: 'block',
  height: '30px',
  lineHeight: '30px',
};
export default class RadioButtons extends Component {
  constructor(props) {
    super(props);
    this.state= {
      value: this.props.options[0].value,
    };
    this.props.parentCallback(this.state.value);
  }

  onChange = e => {
    this.setState({
      value: e.target.value,
    });
    this.props.parentCallback(e.target.value);
  };

  render() {
    return (
        <div>
          <Title level={2}>{this.props.title}</Title>
          <Radio.Group onChange={this.onChange} value={this.state.value}>
            {this.props.options.map((field, key) => {
              return(
                <Radio style={radioStyle} value={field.value} key={key}>
                  {field.title}
                </Radio>
              )
            })}
          </Radio.Group>
          <Divider />
        </div>
    );
  }
}
