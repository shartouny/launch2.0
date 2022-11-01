import {useState} from "react";
import {HuePicker, AlphaPicker} from 'react-color'
import React from "react";

const MultiColorPicker = ({primaryColor, secondaryColor, handlePrimaryColorChange, handleSecondaryColorChange}) => {


  const [selectedColor, setSelectedColor] = useState("primary");


  const handleHueChange = (e) => {
    if (selectedColor == "primary") {
      handlePrimaryColorChange(e)
    } else {
      handleSecondaryColorChange(e)
    }
  }
  const handleAlphaChange = (e) => {
    if (selectedColor == "primary") {
      handlePrimaryColorChange(e)
    } else {
      handleSecondaryColorChange(e)
    }
  }
  const handleInputChange = (e) => {
    let value = e.target.value;
    const isHex = hexToRgbA(value);


    if (selectedColor == "primary") {
      if (value) {
        handlePrimaryColorChange({rgb: isHex.rgb, "hex": value}
        )
      } else
        handlePrimaryColorChange({rgb: isHex.rgb, "hex": value}
        )

    }
    else {
      if (value) {
        handleSecondaryColorChange(
          {rgb: isHex.rgb, "hex": value}
        )
      } else
        handleSecondaryColorChange(
          {rgb: isHex.rgb, "hex": value}
        )

    }


  }


  const hexToRgbA = (hex) => {
    var c;
    if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
      c = hex.substring(1).split('');
      if (c.length == 3) {
        c = [c[0], c[0], c[1], c[1], c[2], c[2]];
      }
      c = '0x' + c.join('');
      return {"rgb": {"r": (c >> 16) & 255, "g": (c >> 8) & 255, "b": c & 255, "a": 1}}
    }
    return false;
  }

  return (
    <>
      <div style={{
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",

      }}
      >
        <div style={{display: "flex"}}>
          <div style={{
            border: selectedColor == "secondary" ? '1px solid' : "",
            borderRadius: "25px",
            width: "2.5rem",
            height: "2.5rem",
            display: "flex",
            cursor: "pointer",
            justifyContent: "center",
            alignItems: "center",
            marginRight: "1px",
            marginLeft: "1px"
          }}

               onClick={(e) => setSelectedColor("secondary")}
          >
            <div
              style={{
                backgroundColor: `rgba(${secondaryColor?.rgb?.r},${secondaryColor?.rgb?.g},${secondaryColor?.rgb?.b},${secondaryColor?.rgb?.a})`,
                display: "flex",
                width: "1.5rem",
                height: "1.5rem",
                alignItems: "center",
                cursor: "pointer",
                justifyContent: "center",
                marginRight: "2px",
                marginLeft: "2px",
                borderRadius: "25px"
              }}></div>
          </div>

          <div style={{
            border: selectedColor == "primary" ? '1px solid' : "",
            borderRadius: "25px",
            width: "2.5rem",
            height: "2.5rem",
            display: "flex",
            cursor: "pointer",
            justifyContent: "center",
            alignItems: "center",
            marginRight: "1px",
            marginLeft: "1px"
          }}

               onClick={(e) => setSelectedColor("primary")}
          >
            <div
              style={{
                backgroundColor: `rgba(${primaryColor?.rgb?.r},${primaryColor?.rgb?.g},${primaryColor?.rgb?.b},${primaryColor?.rgb?.a})`,
                display: "flex",
                width: "1.5rem",
                height: "1.5rem",
                alignItems: "center",
                cursor: "pointer",
                justifyContent: "center",
                marginRight: "2px",
                marginLeft: "2px",
                borderRadius: "25px"
              }}></div>
          </div>
        </div>
        <div>
          <HuePicker
            className="my-2"
            color={selectedColor == "primary" ? primaryColor.rgb : secondaryColor.rgb}
            onChange={handleHueChange}
            onChangeComplete={handleHueChange}
          />
          <AlphaPicker
            className="my-2"
            color={selectedColor == "primary" ? primaryColor.rgb : secondaryColor.rgb}
            onChange={handleAlphaChange}
            onChangeComplete={handleAlphaChange}
          />

        </div>
        <div>
          <input
            placeholder="Hex Color"
            onChange={handleInputChange}
            type={"text"}
            value={selectedColor == "primary" ? primaryColor.hex : secondaryColor.hex}
            style={{
              borderRadius:"8px",
              textAlign:"center",
              paddingTop:"1px",
              paddingBottom:"1px",
              border:"1px solid #C4C4C4",
              outline:"none",
              width:"7rem"
            }}
          />
        </div>
      </div>
    </>
  )
}

export default MultiColorPicker;
