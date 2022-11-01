import React, { useState, useEffect } from 'react';
import processSign from './../../../utils/processSign';
import {Alert, Button, Menu} from "antd";

const ImageTrace = (props) => {
  const { img, onComplete, imageTypes } = props;

  const [src, setSrc] = useState(img.src);
  const [isSubmit, setIsSubmit] = useState(false);
  const [outUrl, setOutUrl] = useState(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const [showHangers, setShowHangers] = useState(true);
  const [loadingMessage, setLoadingMessage] = useState('Loading');
  const [loadingPercent, setLoadingPercent] = useState(0);

  const traceImage = (src) => {
    setOutUrl(null);
    if (src) {
      setLoadingMessage('Loading');
      setLoadingPercent(0);
      setIsProcessing(true);
      processSign(src, {
        showHangers: showHangers,
        trimWhiteSpace: true,
        // TODO get this from the product settings
        scaleToMaxDimensions:{
          width: 3600,
          height: 3600
        },
        statusCallback: (percent, message) => {
          setLoadingPercent(percent);
          setLoadingMessage(message);
        }
      }).then((url) => {
        setOutUrl(url);
        setIsProcessing(false);

        if (isSubmit && !showHangers){
          submitImage(url)
          setIsSubmit(false)
        }

      })
    }
  }

  const submitImage = (tracedUrl) => {
    if(tracedUrl){
      const canvas = document.createElement('canvas');
      // TODO eventually get these from blank options
      canvas.width = 3600;
      canvas.height = 3600;

      // canvas.width = img.width;
      // canvas.height = img.height;
      const image = document.createElement('img');

      // determine output image type
      const imgTypeArr = imageTypes.map(i => i.mimeType);
      const imageType = imgTypeArr.includes(image.type) ? image.type : imgTypeArr[0];
      const fileNameWithoutExtension = img.name.split('.').slice(0, -1).join('.');

      image.src = tracedUrl;
      image.onload = (e) => {
        URL.revokeObjectURL(tracedUrl);
        const ctx = canvas.getContext('2d');
        // center image
        const x = Math.floor((canvas.width - image.width) / 2);
        const y = Math.floor((canvas.height - image.height) / 2);
        ctx.drawImage(
          image,
          x,
          y
        );
        image.remove();
        // create canvas blob
        canvas.toBlob((blob) => {
          const fileOfBlob = new File([blob], fileNameWithoutExtension + '.png', {
            type: 'image/png'
          });
          canvas.remove();
          onComplete(fileOfBlob);
        }, 'image/png');
      };
    }
  }

  useEffect(() => {
    if (src) {
      traceImage(src)
    }
  }, [src, showHangers])

  return (
    <div style={{
        position: 'relative',
        height: 'calc(100vh - 300px)'
      }}>
      <div style={{ height: '100%' }}>
        {
            outUrl ?
              <div style={{ background: '#fff', height: '100%', width:'100%', textAlign: 'center' }}>
                <img style={{
                  height: 'auto',
                  width: 'auto',
                  maxHeight: '100%',
                  maxWidth: '100%',
                }} src={outUrl} />
              </div>
              :
              null
        }
      </div>
      <div style={{ marginTop:35, width: '100%' }}>
        <div>
          <div style={{ display: isProcessing ? 'none' : 'block' , textAlign: 'center'}}>

            <Button
              loading={false}
              type="primary"
              onClick={() => {
                if(showHangers){
                  setIsSubmit(true)
                  setShowHangers(false)
                }
                else{
                  submitImage(outUrl)
                }
              }}
            >
              <span>
                Accept
              </span>
            </Button>

            <Button
              style={{margin: '0 5px'}}
              onClick={() => setShowHangers(!showHangers)}
            >
              <span>
              {showHangers ? 'Remove Detachments' : 'Show Detachments'}
              </span>
            </Button>

          </div>
          <div style={{ display: isProcessing ? 'block' : 'none', width: 300, textAlign: 'center', margin: 'auto'}}>
            <div style={{width: '100%', border: '1px solid #e4e4e4', borderRadius: '40px' }}>
              <div style={{ width:`${loadingPercent}%`,  background:'#4454df', height: '10px', borderRadius: '40px' }}/>
            </div>
            <div style={{ padding: 5 , fontSize: '14px'}}>{loadingMessage}</div>
          </div>
        </div>
      </div>
    </div>
  );

};
export default ImageTrace;
