import Cropper from 'react-easy-crop';
import React, { useState, useCallback } from 'react';
import {
  Button,
  Alert,
  Radio,
  Menu
} from 'antd';
import { RotateLeftOutlined, RotateRightOutlined } from '@ant-design/icons';

const ImageCropper = (props) => {
  const { img, imageRequirements, onComplete, imageTypes, isHide } = props;
  const isVerticalHorizontal = isHide;
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [rotation, setRotation] = useState(0);
  const [cropping, setCropping] = useState({ width: 0, height: 0, x: 0, y: 0 });
  const [switchLayout, setSwitchLayout] = useState(false);

  const onCropComplete = useCallback((croppedArea, croppedAreaPixels) => {
    setCropping(croppedAreaPixels);
  }, [])

  // set if image is too small
  const tooSmallPercent = 0.75;

  let outSize = {
    width: imageRequirements.storeWidthMin,
    height: imageRequirements.storeHeightMin
  };

  // handle if size is not set dimensions
  if(imageRequirements.storeWidthMin !== imageRequirements.storeWidthMax || imageRequirements.storeHeightMin !== imageRequirements.storeHeightMax){
    // scale to largest min dimension
    if(imageRequirements.storeWidthMin > imageRequirements.storeHeightMin){
      outSize = {
        width: imageRequirements.storeWidthMin,
        height: Math.min(Math.ceil(img.height * (imageRequirements.storeWidthMin / img.width)), imageRequirements.storeHeightMax)
      };
    } else {
      outSize = {
        width: Math.min(Math.ceil(img.width * (imageRequirements.storeHeightMin / img.height)), imageRequirements.storeWidthMax),
        height: imageRequirements.storeHeightMin
      };
    }
  }

  let aspect = outSize.width / outSize.height;

  // handle vertical horizontal
  if (switchLayout) {
    outSize = {
      width: outSize.height,
      height: outSize.width
    };
    aspect = outSize.width / outSize.height;
  }
  const isTooSmall = img.width < outSize.width * tooSmallPercent || img.height < outSize.height * tooSmallPercent

  const rotateRight = () => {
    switch (rotation) {
      case 0:
        setRotation(90);
        break;
      case 90:
        setRotation(180);
        break;
      case 180:
        setRotation(270);
        break;
      case 270:
        setRotation(0);
        break;
    }
  };

  const rotateLeft = () => {
    switch (rotation) {
      case 0:
        setRotation(270);
        break;
      case 90:
        setRotation(0);
        break;
      case 180:
        setRotation(90);
        break;
      case 270:
        setRotation(180);
        break;
    }
  };

  return (
    <>
      <div
        style={{
          position: 'relative',
          height: 'calc(100vh - 500px)'
        }}
      >
        <div>
          <Cropper
            image={img.src}
            crop={crop}
            zoom={zoom}
            rotation={rotation}
            aspect={aspect}
            onCropChange={setCrop}
            onCropComplete={onCropComplete}
            onZoomChange={() => {
              // do nothing
            }}
          />
        </div>
      </div>
      {
        isTooSmall ?
          <div>
            <Alert message="Selected image is too small which will cause the print to turn out poorly. We recommend using a higher resolution image." type="warning" showIcon />
          </div>
          :
          null
      }
      <div
        style={{
          marginTop: 20,
          textAlign: 'center',
          display: 'flex',
          width: '100%'
        }}
      >
        <div>
          {/* TODO add logic for rotation cropping of output image. This is just visual for now */}
          {/* <Button
            loading={false}
            type="secondary"
            onClick={rotateLeft}
          >
            <RotateLeftOutlined />
          </Button>

          <Button
            loading={false}
            type="secondary"
            onClick={rotateRight}
          >
            <RotateRightOutlined />
          </Button> */}
        </div>
        <div
          style={{
            flexGrow: 1
          }}
        >
          {
            isVerticalHorizontal ?
              <>
                <Menu 
                  onClick={(e) => {
                    const currentValue = outSize.width > outSize.height ? 'horiz' : 'vert';
                    const newValue = e.key;

                    if (currentValue != newValue) {
                      setSwitchLayout(!switchLayout);
                    }
                  }}
                  selectedKeys={[outSize.width > outSize.height ? 'horiz' : 'vert']} 
                  mode="horizontal"
                >
                  <Menu.Item key="vert">
                  Vertical
                  </Menu.Item>
                  <Menu.Item key="horiz">
                    Horizontal
                  </Menu.Item>
                </Menu>
                {/* <Radio.Group
                  value={outSize.width > outSize.height ? 'horiz' : 'vert'}
                  onChange={(e) => {
                    const currentValue = outSize.width > outSize.height ? 'horiz' : 'vert';
                    const newValue = e.target.value;

                    if (currentValue != newValue) {
                      setSwitchLayout(!switchLayout);
                    }
                  }}
                  size="small"
                >
                  <Radio.Button value="vert">Vertical</Radio.Button>
                  <Radio.Button value="horiz">Horizontal</Radio.Button>
                </Radio.Group> */}
              </>
              :
              null
          }

        </div>
        <div>
          <Button
            loading={false}
            type="primary"
            onClick={() => {
              // perform the cropping
              const canvas = document.createElement('canvas');
              canvas.width = outSize.width;
              canvas.height = outSize.height;
              const image = document.createElement('img');

              // determine output image type
              const imgTypeArr = imageTypes.map(i => i.mimeType);
              const imageType = imgTypeArr.includes(image.type) ? image.type : imgTypeArr[0];
              const fileNameWithoutExtension = img.name.split('.').slice(0, -1).join('.');

              image.onload = (e) => {
                URL.revokeObjectURL(img.src);
                // const scale = imageRequirements.storeWidthMin / cropping.width;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(
                  image,
                  cropping.x,
                  cropping.y,
                  cropping.width,
                  cropping.height,
                  0,
                  0,
                  canvas.width,
                  canvas.height
                );
                image.remove();
                // create canvas blob
                if (imageType === 'image/jpeg') {
                  canvas.toBlob((blob) => {
                    const fileOfBlob = new File([blob], fileNameWithoutExtension + '.jpg', {
                      type: 'image/jpeg'
                    });
                    canvas.remove();
                    onComplete(fileOfBlob);
                  }, 'image/jpeg', 0.95);
                } else {
                  canvas.toBlob((blob) => {
                    const fileOfBlob = new File([blob], fileNameWithoutExtension + '.png', {
                      type: 'image/png'
                    });
                    canvas.remove();
                    onComplete(fileOfBlob);
                  }, 'image/png');
                }
              };
              image.src = img.src;
            }}
          >
            <span>
              Accept
            </span>
          </Button>
        </div>
      </div>
    </>
  )
};
export default ImageCropper;