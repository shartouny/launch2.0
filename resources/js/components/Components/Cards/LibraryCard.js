import React, {Component} from 'react';
import {Button, Card, Typography} from 'antd/lib/index';
import {Icon} from "antd";

const {Meta} = Card;
const {Paragraph} = Typography;

/**
 *
 */
export default class LibraryCard extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };

  renderImageSize = (imageSize) => {
    const sizeInMb = (Number(imageSize) / 1000000).toFixed(2);
    return `${sizeInMb} MB`;
  };

  /**
   *
   * @returns {*}
   */
  render() {
    const {
      image,
      onImageSelection,
      onDeleteImage,
      selectedImage,
    } = this.props;

    return (
      <div style={
        (selectedImage && image.id === selectedImage.id)
          ? {border: '2px solid #444FE5'}
          : {border: '2px solid transparent'}}
      >
        <Card
          hoverable
          style={{width: "100%"}}
          cover={<div style={{
            height: '200px',
            width: '200px',
            maxWidth:'100%',
            backgroundImage: `url(${image.thumbUrl})`,
            backgroundSize: 'contain',
            backgroundPosition: 'center',
            backgroundRepeat: 'no-repeat',
            position:'relative',
            margin:"auto"
          }}/>}
          onClick={() => onImageSelection(image)}
          extra={
            <Button
              style={{padding: '4px 10px', marginTop:13, border: 0, boxShadow:'none'}}
              onClick={(e) => {
                e.stopPropagation();
                onDeleteImage(image.id);
              }}
            >
              <Icon type="close"/>
            </Button>
          }
          headStyle={{
            margin: 0,
            padding: 0,
            minHeight: '35px',
            height: '35px',
            display: 'flex',
            justifyContent: 'flex-end',
            position:'relative',
            zIndex:1,
            border:0
          }}
        >
          <div style={{fontSize: '0.8rem', height: '60px'}}>
            <Meta
              title={<span style={{fontSize: '0.8rem'}}>{image.fileName}</span>}
              style={{margin: 0}}
            />
            <Paragraph style={{marginBottom: 0}}
                       ellipsis={{rows: 1}}>Dimensions: {image.width} x {image.height}</Paragraph>
            <Paragraph style={{marginBottom: 0}}
                       ellipsis={{rows: 1}}>Size: {this.renderImageSize(image.size)}</Paragraph>
            {/*<Paragraph style={{marginBottom: 0}} ellipsis={{rows: 1}}>{image.fileName}</Paragraph>*/}
            {/*<Paragraph style={{marginBottom: 0}} ellipsis={{rows: 1}}>Created: {image.createdAt}</Paragraph>*/}

          </div>
        </Card>
      </div>
    )
  }
};
