import React, {Component} from 'react';
import {Tag, Typography, Divider} from 'antd/lib/index';

const {Title} = Typography;

const { CheckableTag } = Tag;

export default class ToggleOptions extends Component {
  constructor(props) {
    super(props);
  }

  state = {
    selectedTags: this.props.options,
  };

  handleChange(tag, checked) {
    const { selectedTags } = this.state;
    const nextSelectedTags = checked ? [...selectedTags, tag] : selectedTags.filter(t => t !== tag);
    this.setState({ selectedTags: nextSelectedTags });
  }
  render() {
    const { selectedTags } = this.state;
    if(this.props.options) {
      return (
          <div>
            <Title level={2}>{this.props.title}</Title>
            {this.props.options.map(tag => (
                <CheckableTag
                    key={tag}
                    checked={selectedTags.indexOf(tag) > -1}
                    onChange={checked => this.handleChange(tag, checked)}
                >
                  {tag}
                </CheckableTag>
            ))}
            <Divider/>
          </div>
      );
    } else {
      return ''
    }
  }
}