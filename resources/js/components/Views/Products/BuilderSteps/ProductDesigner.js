import React, { Component } from "react";
import ReactHtmlParser from "react-html-parser";
import axios from "axios";
import {
  Col,
  Row,
  Tabs,
  Drawer,
  Divider,
  message,
  Select
} from "antd";
import {
  CloseOutlined
} from '@ant-design/icons';
import _ from 'lodash';
import { Button, Tag, Typography } from "antd/lib/index";
import ImageUploader from "../../../Components/UploadModal/ImageUploader";
import ProductStage from "../../../Components/ProductStage/ProductStage";
import { displayErrors } from "../../../../utils/errorHandler";
import DesignGuide from "../../../../../images/design-guide.svg";
import {Link} from "react-router-dom";
import Studio from "../../Studio/Studio";
const { TabPane } = Tabs;
const { CheckableTag } = Tag;
const { Title } = Typography;
const { Option } = Select;


/**
 *
 */
export default class ProductDesigner extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    const { products, mockupsLocations, blankStageLocationIds } = props.data;
    const firstStageData = this.getFirstStageData(products);
    const { image = products[0].image } = firstStageData;
    let createType = 0;
    if (firstStageData.createTypes.length) {
      createType = firstStageData.createTypes[0];
    }
    this.state = {
      drawerVisible: false,
      products,
      mockupsLocations,
      blankStageLocationIds,
      selectedProduct: products[0],
      activeLocationTab: firstStageData.location.id,
      activeCreateTypeTab: createType.id,
      activeStageTab: products[0].stageGroups[0].id,
      selectedStageGroup: products[0].stageGroups[0],
      selectedStageTemplateImage: image,
      selectedStage: firstStageData,
      selectedOptions: [],
      options: products[0].options,
      savedData: [],
      selectedLocationId: {},
      selectedOffsetId: {},
      uploadedImages: [],
      uploaded: {},
      currentPage: 0,
      total: 0,
      pageSize: 0,
      isLoadingImages: false,
      page: 1,
      query: "",
      unionOptions: this.getUnionOptions(products),
      selectedStageValues: this.props.selectedStageValues,
      activeProductOptionsTab: products[0].id,
      selectedProducts: products,
      stagesId: [],
      closeUploaderModel: false,
      showIframe: false,
      studioRequirements: ''
    };

    this.styles = {
      templateStyles: {
        maxHeight: "500px",
        width: "100%",
        position: "relative",
        maxWidth: "500px",
        minWidth: "500px",
        zIndex: 1
      },
      thumbnailContainer: {
        display: "inline-block",
        justifyContent: "center",
        top: "500px" // height of the template image is 500.
      },
      thumbnails: {
        display: "inline-block",
        backgroundSize: "cover",
        backgroundRepeat: "no-repeat",
        width: "75px",
        height: "75px",
        padding: 8
      }
    };
  }

  /**
   *
   */

  componentDidMount() {
    const studioURL = process.env.MIX_STUDIO_URL
    const receive = (evt) => {
      if (evt.origin == studioURL) {
        this.onUploadImage(evt.data, (status) => {
          if(status === 'done'){
            this.setState({showIframe: false})
          }
        })
      }
    }
    window.addEventListener('message', receive, false);
    this.props.historySetup()
    this.getStages()
    const portrait = 'portrait'
    const landscape = 'landscape'
    const both = 'both'
    const hide = 'hide'
    const studioRequirement = {
      width: this.state.selectedStage.createTypes[0]?.imageRequirement?.storeWidthMax,
      height: this.state.selectedStage.createTypes[0]?.imageRequirement?.storeHeightMax,
      stage: this.state.selectedStage.location.fullName === 'Front Vertical' || this.state.selectedStage.location.fullName === 'Front Horizontal' ?
        both : hide,
      printBleed:this.state.selectedStage?.image.imageUrl
    }
    const hashRequirements = btoa(JSON.stringify(studioRequirement))
    this.setState({
      studioRequirements: hashRequirements
    })
  }



  getStages() {
    let newStage =  this.props.data.selectedProduct.stageGroups.map(value => (
      value.name == 'Horizontal' || value.name == 'Vertical' ?
        value.stages[0].id : null
    ))
    this.setState({stagesId: [...newStage]})
  }

  /**
   *
   * @param {{}} data
   * @returns {{}}
   * TODO what to display if no image is found?
   */
  getFirstStageData(data) {
    const { stageGroups = [] } = data[0];

    if (!stageGroups.length) {
      return data;
    }
    const firstStage = stageGroups[0];
    if (firstStage.stages && firstStage.stages.length) {
      return firstStage.stages[0];
    }
    return data;
  }

  /**
   *
   * @param {{}} data
   * @returns {[]}
   * TODO just for display.
   */
  getUnionOptions = data => {
    const finalOptionIds = [];
    const unionOptions = [];
    const products = [...data];
    /**
     * Grab each unique option
     */
    products.forEach(product => {
      product.options.forEach(option => {
        if (!finalOptionIds.includes(option.id)) {
          unionOptions.push(option);
          finalOptionIds.push(option.id);
        } else {
          //Merge other products options
          let uoIndex = unionOptions.findIndex(uo => uo.id === option.id);
          for (let i = 0; i < option.values.length; i++) {
            let optionValue = option.values[i];
            let uoValIndex = unionOptions[uoIndex].values.findIndex(
              uoVal => uoVal.name === optionValue.name
            );
            if (uoValIndex === -1) {
              //TODO: This is altering the first products option values
              //unionOptions[uoIndex].values.push(optionValue);
            }
          }
        }
      });
    });
    return unionOptions;
  };
  /**
   *
   * @param {{}} file
   */
  onUploadImageToServer = (file) => {
    let formData = new FormData();
    const acceptedTypes = [
      "image/png",
      "image/jpg",
      "image/jpeg",
      "image/octet-stream"
      // 'application/pdf',
    ];

    if (!acceptedTypes.includes(file.type)) {
      message.error("File type not supported");
      throw "File type not supported";
    }

    formData.append("image", file);
    const createType = this.state.selectedStage.createTypes[0];

    //const {uploadedImages} = this.state;

    return axios
      .post("/account-images", formData, {
        params: {
          blankStageId: createType.blankStageId,
          createTypeId: createType.createTypeId
        },
        headers: {
          "Content-Type": "multipart/form-data"
        }
      })
      .then(res => {
        if (res.status === 201) {
          const { data } = res;
          if (data) {
            this.setState({ uploaded: data.data });
            return data.data;
          }
        }
        return false;
      })
      .catch(error => {
        displayErrors(error);
        this.setState({closeUploaderModel: false});
        return false;
      });
  };
  /**
   *
   * @param {string} activeKey
   */
  onCreateTypeTabClicked = activeKey => {
    this.setState({ activeCreateTypeTab: activeKey });
  };

  /**
   *
   * @param {{}} stageGroups
   * @param {string} activeKey
   */
  onStageTabClicked(stageGroups, activeKey) {
    const { selectedProduct, selectedStageGroup } = this.state;
    this.setState({ activeLocationTab: activeKey }, () => {
      const selectedStage = this.findSelectedTab(stageGroups);

      //selectedStageGroup.stages.blankStageLocationId === selectedStage.blankStageLocationId
      const productStage = selectedStageGroup.stages.find(stage => {
        return (
          Number(stage.blankStageLocationId) ===
          Number(selectedStage.blankStageLocationId)
        );
      });

      const selectedStageTemplateImage = productStage && productStage.stage
        ? productStage.stage.image
        : selectedStage.image;
      //selectedProduct.stageGroups where name === selectedStageGroup.name? where stage.name === selectedStage.name
      this.setState({
        selectedStageTemplateImage: selectedStageTemplateImage,
        selectedStage: selectedStage,
        imageRequirements: selectedStage.createTypes[0].imageRequirement
      });
    });
  }

  onProductOptionsTabClicked(productId) {
    const { selectedStageValues, products } = this.state;
    const selectedProduct = products.find(
      product => Number(product.id) === Number(productId)
    );

    const newSelectedStage = selectedProduct.stageGroups[0].stages[0];

    let selectedStageGroup = {};
    const selectedProductSelectedStageValues = selectedStageValues.find(
      ssv => ssv.blankId === selectedProduct.id
    );

    if (selectedProductSelectedStageValues) {
      selectedStageGroup = selectedProductSelectedStageValues.stageGroups[0];
    }

    const activeLocationTab = newSelectedStage.location.id;
    const activeStageTab = products[0].stageGroups[0].id; //Use first product as Stage Tabs are coming from first product only

    this.setState({
      activeProductOptionsTab: productId,
      selectedProduct,
      selectedStage: newSelectedStage,
      selectedStageGroup,
      activeLocationTab,
      activeStageTab
    });
    this.props.setSelectedProduct(selectedProduct);
  }

  /**
   *
   * @param {string} activeKey
   */
  onStageGroupTabClicked(activeKey) {
    const { products, selectedStageValues } = this.state;

    products.forEach(product => {
      product.stageGroups.forEach(stageGroup => {
        if (stageGroup.id === Number(activeKey)) {
          const firstStage = stageGroup.stages[0];

          /**
           * No locations found, because no locations set on this stage.
           */
          if (!firstStage) {
            message.error(
              "This stage has no locations, please set one in admin"
            );
            return;
          }

          this.setState({
            selectedStage: firstStage,
            activeStageTab: stageGroup.id,
            selectedStageGroup: stageGroup,
            activeLocationTab: firstStage.location.id
          });
        }
      });
    });
  }

  /**
   *
   * @param {{}} stageGroups
   * @returns {{}}
   */
  findSelectedTab(stageGroups) {
    const { stages = [] } = stageGroups;
    const { activeLocationTab } = this.state;
    let activeLocation = {};

    stages.forEach(stage => {
      if (String(stage.location.id) === activeLocationTab) {
        activeLocation = stage;
      }
    });
    return activeLocation;
  }

  /**
   *
   * @param {boolean} selected
   * @param {{}} value
   */
  onOptionChanged(selected, value) {
    const { options, productInfo } = this.state;
    const selectedOption = [];
    const newProductInfo = { ...productInfo };

    options.forEach(option => {
      option.values.forEach(val => {
        if (val.id === value.id) {
          selectedOption.push(val.id);
        }
      });
    });

    /**
     * If selected is new assign a new object to product info.
     */
    if (!newProductInfo.selectedOptions) {
      Object.assign(newProductInfo, { selectedOptions: selectedOption });
      this.setState({ productInfo: newProductInfo });
      return;
    }

    /**
     * If selected has already been added, remove it.
     */
    if (newProductInfo.selectedOptions.includes(...selectedOption)) {
      const index = newProductInfo.selectedOptions.indexOf(...selectedOption);
      newProductInfo.selectedOptions.splice(index, 1);
      this.setState({ productInfo: newProductInfo });
      return;
    }

    Object.assign(newProductInfo, {
      selectedOptions: [...newProductInfo.selectedOptions, ...selectedOption]
    });
    this.setState({ productInfo: newProductInfo });
  }

  /**
   *
   * @param {int} value
   */
  onArtPlacementChange = value => {
    const {
      selectedStage,
      products,
      selectedLocationId,
      selectedStageValues,
      selectedStageGroup
    } = this.state;
    const newProducts = [...products];
    const newLocations = { ...selectedLocationId };
    //let newSelectedStageValues = [...selectedStageValues];

    const name = selectedStageGroup.id.toString();
    const target = {
      name,
      value
    };

    newProducts.forEach(product => {
      product.stageGroups.forEach(stages => {
        stages.stages.forEach(stage => {
          if (stage.id === selectedStage.id) {
            Object.assign(stage, { [target.name]: target.value });
          }
        });
      });
    });

    if (!newLocations[target.name]) {
      Object.assign(newLocations, { [target.name]: target.value });
    } else {
      newLocations[target.name] = target.value;
    }

    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.location.id === selectedStage.location.id) {
            stage.blankStageLocationSubId = target.value;

            // Set first offset on stage location change
            const stageSLS = stage.stage.subLocationSettings.find(
              sls => sls.blankStageLocationSubId === target.value
            );
            stage.blankStageLocationSubOffsetId =
              stageSLS && stageSLS.offsets.length
                ? stageSLS.offsets[0].blankStageLocationSubOffsetId
                : null;
          }
        })
      )
    );
    this.props.onSelectedStageValuesChanged(selectedStageValues);

    this.setState({
      products: newProducts,
      selectedLocationId: newLocations
      // selectedStageValues: selectedStageValues
    });

  };

  onArtOffsetChange = (value, selLocId) => {
    const {
      selectedStage,
      products,
      selectedLocationId,
      selectedStageValues,
      selectedStageGroup,
      activeStage
    } = this.state;
    const newProducts = [...products];
    const newLocations = { ...selectedLocationId };
    //let newSelectedStageValues = [...selectedStageValues];

    const name = "selectedOffsetId"; //selectedLocationId.toString();
    const target = {
      name,
      value
    };

    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.location.id === selectedStage.location.id) {
            stage.blankStageLocationSubOffsetId = target.value;
          }
        })
      )
    );

    this.props.onSelectedStageValuesChanged(selectedStageValues);

    this.setState({
      //products: newProducts,
      //selectedLocationId: newLocations,
      //selectedOffsetId:
      selectedStageValues: selectedStageValues
    });
  };
  /**
   *
   * @param {[]} image
   */
  onSelectedImage = image => {
    let { products, selectedStage, selectedStageValues } = this.state;
    const newProducts = [...products];
    let selectedStageName = null;
    let selectedStageLocation = null
    newProducts.forEach(product => {
      product.stageGroups.forEach(stage => {
        if (stage.stages && stage.stages.length) {
          stage.stages.forEach(item => {
            if (stage.name == 'Horizontal' || stage.name == 'Vertical') {
              if (image.height > image.width && stage.name == 'Vertical') {
                this.onStageGroupTabClicked((stage.id).toString());
                Object.assign(item, { artwork: image });
                selectedStage = item;
                selectedStageName = item.location.shortName;
                selectedStageLocation = stage
              }
              else if (image.width > image.height && stage.name == 'Horizontal') {
                this.onStageGroupTabClicked((stage.id).toString());
                Object.assign(item, { artwork: image });
                selectedStage = item;
                selectedStageName = item.location.shortName;
                selectedStageLocation = stage
              } else if (image.width == image.height && stage.name == 'Horizontal') {
                this.onStageGroupTabClicked((stage.id).toString());
                Object.assign(item, { artwork: image });
                selectedStage = item;
                selectedStageName = item.location.shortName;
                selectedStageLocation = stage
              }
            }
            else {
              if (item.id === selectedStage.id) {
                Object.assign(item, { artwork: image });
                selectedStageName = item.location.shortName;
              }
            }
          });
        }
      });
    });

    // Update all matching stage names
    newProducts.forEach(product => {
      product.stageGroups.forEach(stage => {
        if (stage.stages && stage.stages.length) {
          stage.stages.forEach(item => {
            if (item.location.shortName === selectedStageName) {
              if (stage.name == 'Horizontal' || stage.name == 'Vertical'){
                this.onRemoveArtwork()
              }
              Object.assign(item, { artwork: image });
            }
          });
        }
      });
    });

    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.name == 'Horizontal' || stage.name == 'Vertical') {
            if (selectedStageLocation.id !== stageGroup.id && stage.image != null) {
              stage.image = null
              stage.imageId = null;
            } else {
              stage.image = image
              stage.imageId = image.id;
            }
          } else {
            if (stage.location.id === selectedStage.location.id) {
              stage.image = image
              stage.imageId = image.id;
            }
          }
        })
      )
    );
    this.props.onSelectedStageValuesChanged(selectedStageValues);

    //MockupsPicker
    let mockupsLocations = this.state.mockupsLocations ?? [];
    let blankStageLocationIds = this.state.blankStageLocationIds ?? [];
    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.location.id === selectedStage.location.id) {
            mockupsLocations.indexOf(stageGroup.id) === -1 && mockupsLocations.push(stageGroup.id);

            blankStageLocationIds.indexOf(stage.id) === -1 && blankStageLocationIds.push(stage.id)
          }
        })
      )
    );
    this.setState({
      products: newProducts,
      mockupsLocations: mockupsLocations,
      blankStageLocationIds: blankStageLocationIds
    });
  };

  /**
   *
   */
  onRemoveArtwork() {
    const { products, selectedStage, selectedStageValues, mockupsLocations} = this.state;
    const newProductInfo = [...products];

    let selectedStageName = null;
    newProductInfo.forEach(product => {
      product.stageGroups.forEach(stage => {
        if (stage.stages && stage.stages.length) {
          stage.stages.forEach(item => {
            if (item.id === selectedStage.id) {
              delete item.artwork;
              selectedStageName = item.location.shortName;
            }
          });
        }
      });
    });

    // Update all matching stage names
    newProductInfo.forEach(product => {
      product.stageGroups.forEach(stage => {
        if (stage.stages && stage.stages.length) {
          stage.stages.forEach(item => {
            if (item.location.shortName === selectedStageName) {
              delete item.artwork;
            }
          });
        }
      });
    });

    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.location.id === selectedStage.location.id) {
            stage.image = null;
            stage.imageId = null;
          }
        })
      )
    );

    this.props.onSelectedStageValuesChanged(selectedStageValues);

    //MockupsPicker
    let newMockupsLocations = mockupsLocations
    let newBlankStageLocationIds = this.state.blankStageLocationIds
    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup =>
        stageGroup.stages.forEach(stage => {
          if (stage.location.id === selectedStage.location.id) {
            let index = newMockupsLocations.indexOf(stageGroup.id)
            let locationId = newBlankStageLocationIds.indexOf(stage.blankStageLocationId)
            newMockupsLocations.splice(index, 1)
            newBlankStageLocationIds.splice(index, 1)
          }
        })
      )
    );
    this.setState({
      products: newProductInfo,
      mockupsLocations: newMockupsLocations,
      blankStageLocationIds: newBlankStageLocationIds
    });

  }

  /**
   *
   * @param {{}} value
   * @returns {*}
   */
  renderColorSwatch(value) {
    return (
      <span
        key={value.id}
        className="swatch-block"
        style={{ background: value.hexCode }}
      />
    );
  }

  /**
   *
   * @param value
   * @returns {boolean}
   */
  isChecked = value => {
    let checked = false;
    const { selectedOptions } = this.props.data;
    if (selectedOptions.length) {
      selectedOptions.forEach(option => {
        if (option === value.id) {
          checked = true;
        }
      });
    }
    return checked;
  };

  /**
   *
   * @param {{}} value
   * @returns {*}
   */
  renderCheckableFlag(value) {
    const { onSelectedOption } = this.props;

    return (
      <CheckableTag
        key={value.id}
        checked={this.isChecked(value)}
        className="blank-option-checkable-flag"
        onChange={isSelected => onSelectedOption(isSelected, value)}
      >
        {value.hexCode && this.renderColorSwatch(value)}

        <span title={value.name} style={{overflow: "hidden", whiteSpace: "nowrap", textOverflow: "ellipsis", width: "75%", height: "16px", display: "inline-block"}}>
          {value.name}
        </span>

        {!this.isChecked(value) && (
          <span style={{ width: 25, display: "inline-block" }} />
        )}
      </CheckableTag>
    );
  }

  getActiveStage = () => {

    let activeStage = {};
    const {
      selectedStageGroup,
      selectedStageValues,
      selectedStage
    } = this.state;

    selectedStageValues.forEach(product => {
      const stageGroup = product.stageGroups.find(
        sg => sg.id === selectedStageGroup.id
      );

      if (stageGroup) {
        const stage = stageGroup.stages.find(s => s.id === selectedStage.id);

        if (stage) {
          activeStage = stage;
        }
      }
    });

    return activeStage;
  };

  /**
   *
   * @param {{}} subLocationSettings
   * @returns {null|*}
   */
  renderArtPlacement({ subLocationSettings }) {
    if (!subLocationSettings.length) {
      return null;
    }

    const {
      selectedStageGroup,
      selectedLocationId,
      selectedStageValues,
      selectedStage
    } = this.state;
    const firstLocation = this.selectFirstArtworkPlacement(subLocationSettings);

    let activeStage = this.getActiveStage();
    let value = (activeStage && activeStage.blankStageLocationSubId) || null;

    return (
      <div style={subLocationSettings.length === 1 ? { display: "none" } : {}}>
        <Title level={2}>Art Placement</Title>
        <Select
          name={selectedStageGroup.id.toString()}
          onChange={this.onArtPlacementChange}
          value={value}
        >
          {subLocationSettings.map(locations => (
            <Option
              key={locations.subLocation.id}
              value={locations.subLocation.id}
            >
              {locations.subLocation.name}
            </Option>
          ))}
        </Select>

        {/*<Radio.Group*/}
        {/*  name={selectedStageGroup.id.toString()}*/}
        {/*  onChange={this.onArtPlacementChange}*/}
        {/*  value={value}*/}
        {/*>*/}
        {/*  {subLocationSettings.map(locations => (*/}
        {/*    <div key={locations.subLocation.id}>*/}
        {/*      <Radio*/}
        {/*        value={locations.subLocation.id}*/}
        {/*      >*/}
        {/*        {locations.subLocation.name}*/}
        {/*      </Radio>*/}
        {/*      <br/>*/}
        {/*    </div>*/}
        {/*  ))}*/}
        {/*</Radio.Group>*/}
      </div>
    );
  }

  /**
   *
   * @param {{}} subLocationSettings
   * @returns {*}
   */
  renderOffsets({ subLocationSettings }) {
    if (!subLocationSettings.length) {
      return null;
    }

    //Note: selectedLocationId is object map of stage id to location id
    const {
      selectedLocationId,
      selectedOffsetId,
      selectedStageValues,
      selectedStageGroup,
      selectedStage
    } = this.state;
    let selectedProductLocationId = null;
    for (let prop in selectedLocationId) {
      selectedProductLocationId = selectedLocationId[prop];
      break;
    }

    let selectedLocation = subLocationSettings.find(
      location => location.blankStageLocationSubId === selectedProductLocationId
    );
    const firstLocation = subLocationSettings[0];

    /**
     * No selected offsets, and none on the first location.
     */
    if (!selectedOffsetId && !firstLocation.offsets.length) {
      return null;
    }

    /**
     * If no location found (none selected) get the first one.
     */
    if (!selectedLocation) {
      selectedLocation = firstLocation;
    }

    /**
     * Get the offsets by id, or first selected location.
     */
    if (!selectedLocation) {
      return null;
    }

    /**
     * If no offsets are found on selected offset or first return
     */
    if (!selectedLocation.offsets.length) {
      return null;
    }

    let activeStage = this.getActiveStage();
    let value = activeStage.blankStageLocationSubOffsetId || null;

    return (
      <div
        key={selectedLocation.id}
        style={selectedLocation.offsets.length <= 1 ? { display: "none" } : {}}
      >
        <Title level={2}>Distance From Top</Title>
        <Select
          name={"selectedOffsetId"}
          onChange={this.onArtOffsetChange}
          value={value}
        >
          {selectedLocation.offsets.map(offset => (
            <Option
              key={offset.blankStageLocationSubOffsetId}
              value={offset.blankStageLocationSubOffsetId}
            >
              {offset.subOffset.name}
            </Option>
          ))}
        </Select>
      </div>
    );
  }

  /**
   *
   * @param {[]} options
   * @returns {*}
   */
  renderOptions = (options,product) => {
    const { data } = this.props;
    const {isAllOptionsSelected} = data;
    return options.map((option, index) => {
      const optionTextDisplay =
        !isAllOptionsSelected || !isAllOptionsSelected[option.name]
          ? "Select All"
          : "Deselect All";
      return (
        <div key={option.id}>
          {index > 0 && <Divider />}
          <Row type="flex" justify="space-between">
            <Col>
              <Title level={2}>{option.name}</Title>
            </Col>
            <Col>
              <Button
                type="link"
                onClick={event =>
                  this.selectAllProductOptionValues(event, option)
                }
              >
                {optionTextDisplay} {option.name}s
              </Button>
            </Col>
          </Row>
          {option.values && option.values.length
            ? option.values.map(value => {
              return this.renderCheckableFlag(value);
            })
            : null}
        </div>
      );
    });
  };

  renderVariantImage = (variantImageUrl, variantImage2Url) => {
    const { products, selectedStageGroup } = this.state;
    let firstProduct = products[0];
    let selectedIndex = 0;

    if (firstProduct && firstProduct.stageGroups) {
      for (let i = 0; i < firstProduct.stageGroups.length; i++) {
        if (firstProduct.stageGroups[i].id === selectedStageGroup.id) {
          selectedIndex = i;
        }
      }
    }

    if (selectedStageGroup.sort > 1 || selectedIndex > 0) {
      if (variantImage2Url) {
        return (
          <img
            src={variantImage2Url}
            style={{
              ...this.styles.templateStyles,
              position: "absolute",
              zIndex: 0
            }}
            alt="variant image 2"
            className="responsive"
          />
        );
      }
    }

    if (variantImageUrl) {
      return (
        <img
          src={variantImageUrl}
          style={{
            ...this.styles.templateStyles,
            position: this.state.selectedProduct.categoryDisplay !== 'Monogram' ? 'absolute' : 'initial',
            zIndex: 0
          }}
          alt="variant image 1"
          className="responsive"
        />
      );
    }
  };
  /**
   *
   * @param {number} imageId
   * @returns {Promise<void | any>}
   */
  onDeleteImageServer = imageId => {
    const updatedImages = [...this.state.uploadedImages];
    const imageFound = updatedImages.find(image => image.id === imageId);
    const index = updatedImages.indexOf(imageFound);

    return axios
      .delete(`/account-images/${imageId}`)
      .then(res => {
        if (res.status === 200) {
          // updatedImages.splice(index, 1);
          // this.setState({
          //   uploadedImages: updatedImages,
          // })
          this.getImages();
        }
      })
      .catch(error => message.error(error))
      .finally(() => {});
  };
  /**
   *
   * @param {{}} file
   * @returns {Promise<void | any>}
   */

  onUploadImage= ({file}, callback) => {
    const { uploadedImages, products } = this.state;
    this.setState({closeUploaderModel: true})
    const newProduct = [...products]
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.addEventListener('load', event => {
      this.setState({closeUploaderModel: true})
      const _loadedImageUrl = event.target.result;
      const image = document.createElement('img');
      image.src = _loadedImageUrl;
      image.addEventListener('load', () => {
        const { width, height } = image;
        // set image width and height to your state here
        newProduct.map(product => {
          product.stageGroups.map(stage => {
            if (stage.name == 'Vertical' || stage.name == 'Horizontal') {
              if (stage.stages && stage.stages.length) {
                stage.stages.map(item => {
                  if (height > width && stage.name == 'Vertical') {
                    this.state.selectedStage.createTypes[0].blankStageId = item.id
                    return this.onUploadImageToServer(file)
                      .then(file => {
                        if (file) {
                          this.getImages(1, "").then(() => this.onSelectedImage(file));
                          this.setState({closeUploaderModel: false});
                          callback('done');
                          return true
                        }
                        return false;
                      })
                      .catch(error => {
                        displayErrors(error);
                        return false;
                      })
                      .finally(() => {
                        this.setState({
                          uploading: false,
                          file: {}
                        });
                      });
                  }
                  else if (width > height && stage.name == 'Horizontal') {
                    this.state.selectedStage.createTypes[0].blankStageId = item.id
                    return this.onUploadImageToServer(file)
                      .then(file => {
                        if (file) {
                          this.getImages(1, "").then(() => this.onSelectedImage(file));
                          this.setState({closeUploaderModel: false});
                          callback('done');
                          return true;
                        }
                        return false;
                      })
                      .catch(error => {
                        displayErrors(error);
                        return false;
                      })
                      .finally(() => {
                        this.setState({
                          uploading: false,
                          file: {}
                        });
                      });
                  }
                  else if (height == width && stage.name == 'Horizontal') {
                    this.state.selectedStage.createTypes[0].blankStageId = item.id
                    return this.onUploadImageToServer(file)
                      .then(file => {
                        if (file) {
                          this.getImages(1, "").then(() => this.onSelectedImage(file));
                          this.setState({closeUploaderModel: false});
                          callback('done');
                          return true;
                        }
                        return false;
                      })
                      .catch(error => {
                        displayErrors(error);
                        return false;
                      })
                      .finally(() => {
                        this.setState({
                          uploading: false,
                          file: {}
                        });
                      });
                  }
                })
              }
            }
            else {
              if (stage.stages && stage.stages.length) {
                stage.stages.map(item => {
                  if (item.id === this.state.selectedStage.id) {
                    return this.onUploadImageToServer(file)
                      .then(file => {
                        if (file) {
                          this.getImages(1, "").then(() => this.onSelectedImage(file));
                          this.setState({closeUploaderModel: false})
                          callback('done');
                          return true;
                        }
                        return false;
                      })
                      .catch(error => {
                        displayErrors(error);
                        return false;
                      })
                      .finally(() => {
                        this.setState({
                          uploading: false,
                          file: {}
                        });
                      });
                  }
                })
              }
            }
          })
        })
      });
    })
  };

  /**
   * @param {int|null} page
   * @param {string|null} query
   * @returns {Promise<void | any>}
   */
  getImages = (page = null, query = null) => {
    const { selectedStage } = this.state;

    if (page) {
      this.setState({ page: page });
    } else {
      page = this.state.page;
    }

    if (query) {
      this.setState({ query: query });
    } else {
      query = this.state.query;
    }

    /**
     * TODO we want to check the stage(s) first for createTypes
     * if there isn't any hide the tab. If there isn't any overall
     * redirect.
     */
    if (!selectedStage.createTypes.length) {
      message.error("You must select a create type");
      return;
    }

    this.setState({ isLoadingImages: true });
    const createType = selectedStage.createTypes[0];
    return axios
      .get(`/account-images`, {
        params: {
          page: page,
          file_name: query,
          blankStageId: this.state.stagesId[0] !== null ? this.state.stagesId : createType.blankStageId,
          createTypeId: createType.createTypeId
        }
      })
      .then(res => {
        const { data, meta } = res.data;
        if (data) {
          this.setState({
            uploadedImages: data,
            currentPage: meta.current_page,
            total: meta.total,
            pageSize: meta.per_page
          });
        }
      })
      .catch(error => displayErrors(error))
      .finally(() => this.setState({ isLoadingImages: false }));
  };

  /**
   *
   * @param {{}} subLocationSettings
   * @returns {number|*}
   */
  selectFirstArtworkPlacement(subLocationSettings) {
    if (!subLocationSettings[0].subLocation) {
      return 0;
    }

    return subLocationSettings[0].subLocation.id;
  }

  /**
   *
   * @param {{}} event
   */
  onSelectAllOptions = event => {
    const isDeselect = "Deselect";

    if (event.target.textContent.includes(isDeselect)) {
      this.props.onDeselectAll();
      return;
    }

    this.props.onSelectedOption(true, null, true);
  };

  /**
   *
   * @param event
   * @param option
   */
  setOption = (event, option) => {
    const isDeselect = "Deselect";

    if (event.target.textContent.includes(isDeselect)) {
      this.props.unsetOption(option);
      return;
    }

    this.props.setOption(option);
  };

  selectAllProductOptionValues = (event, option) => {
    const isDeselect = "Deselect";


    if (event.target.textContent.includes(isDeselect)) {
      this.props.deselectAllProductOptionValues(
        option,
        option.laravelThroughKey
      );
      return;
    }

    this.props.selectAllProductOptionValues(
      option,
      option.laravelThroughKey
    );
    return;
  };

  selectAllProductOptionValuesMount = (event, option) => {
    this.props.selectedProductHistoryOptions()
    return;

  };

  /**
   *
   * @returns {*}
   */
  render() {
    const { styles } = this;

    const {
      selectedStage,
      selectedStageTemplateImage,
      drawerVisible,
      products,
      selectedLocationId,
      selectedOffsetId,
      uploadedImages,
      uploaded,
      currentPage,
      total,
      pageSize,
      unionOptions,
      selectedStageGroup,
      selectedStageValues,
      selectedProduct,
      selectedProducts,
      studioRequirements
    } = this.state;
    let { picturedProduct, variantImageUrl, variantImage2Url } = this.props;

    const firstProduct = products[0];

    const selectedCreateType = selectedStage.createTypes.length ? selectedStage.createTypes[0] : [];
    const imageRequirements = selectedStage.createTypes.length ? selectedStage.createTypes[0].imageRequirement : [];
    const imageTypes = selectedCreateType.imageTypes ? selectedCreateType.imageTypes.map(it => it.imageType) : [];
    const showStudioButton = window.location.hash.toLowerCase().includes('studio');

    const selectedStageValue = selectedStageValues
      .find(ssv => ssv.blankId === selectedProduct.id)
      .stageGroups.find(sg => sg.name === selectedStageGroup.name)
      .stages.find(
        s => s.location.shortName === selectedStage.location.shortName
      );

    //TODO: The ProductDesigner was created without batching products in mind,
    // we need to revisit the methods in here and refactor them to account for editing multiple products.
    // Until then these vars with the number 2 will contain product specific data.
    const selectedLocationId2 = selectedStageValue
      ? selectedStageValue.blankStageLocationSubId
      : null;
    const selectedOffsetId2 = selectedStageValue
      ? selectedStageValue.blankStageLocationSubOffsetId
      : null;

    const selectedStage2 = products
      .find(product => product.id === selectedProduct.id)
      .stageGroups.find(sg => sg.name === selectedStageGroup.name)
      .stages.find(
        s => s.location.shortName === selectedStage.location.shortName
      );

    const hideTabs = () => {
      let hide = false;
      firstProduct.stageGroups.map((item) => {
        if ((item.name == 'Vertical' || item.name == 'Horizontal')) {
          hide = true;
        }
      })
      return hide
    }

    return (
      <div>
        <Row>
          <Col xs={24} md={24} lg={12}>
            <Title>{selectedProduct.name}</Title>
            <div className="product-description">
              {ReactHtmlParser(selectedProduct.description)}
            </div>
            <>
              { selectedProduct.categoryDisplay !== 'Monogram' && (

                <>
                  <Tabs
                    defaultActiveKey="0"
                    activeKey={String(this.state.activeStageTab)}
                    onTabClick={this.onStageGroupTabClicked.bind(this)}
                    style={firstProduct.stageGroups.length === 1 ? {} : {}}
                    tabBarStyle={hideTabs() ? {display: 'none'} : {}}
                  >
                    {firstProduct.stageGroups.map(item => (
                      <TabPane
                        tab={firstProduct.stageGroups.length > 1 ? item.name : null}
                        key={String(item.id)}
                        style={hideTabs() ? {display: 'none'} : {}}
                      >
                        <Tabs
                          defaultActiveKey="0"
                          activeKey={String(this.state.activeLocationTab)}
                          onTabClick={this.onStageTabClicked.bind(this, item)}
                          style={item.stages.length === 1 ? { display: "none" } : {}}
                        >
                          {item.stages.map(stage => (
                            <TabPane
                              tab={stage.location.shortName}
                              key={String(stage.location.id)}
                            >
                              <Tabs
                                defaultActiveKey="0"
                                activeKey={String(this.state.activeCreateTypeTab)}
                                onTabClick={this.onCreateTypeTabClicked}
                                style={
                                  stage.createTypes.length === 1
                                    ? { display: "none" }
                                    : {}
                                }
                              >
                                {stage.createTypes.map(type => (
                                  <TabPane
                                    tab={type.createType.name}
                                    key={String(type.id)}
                                  />
                                ))}
                              </Tabs>
                            </TabPane>
                          ))}
                        </Tabs>
                      </TabPane>
                    ))}
                  </Tabs>

                  <ImageUploader
                    onUploadImage={this.onUploadImage}
                    onSelectedImages={this.onSelectedImage}
                    onDeleteImageServer={this.onDeleteImageServer}
                    imageRequirements={imageRequirements}
                    imageTypes={imageTypes}
                    selectedCreateType={selectedCreateType}
                    selectedStage={selectedStage}
                    getImages={this.getImages}
                    uploadedImages={uploadedImages}
                    uploaded={uploaded}
                    currentPage={currentPage}
                    total={total}
                    pageSize={pageSize}
                    onDeleteImage={() => this.setState({ confirmModal: true })}
                    handleUpload={() => {}}
                    isLoadingImages={this.state.isLoadingImages}
                    isHide={hideTabs()}
                    closeUploaderModel={this.state.closeUploaderModel}
                  />
                  <div style={{display: 'flex', marginLeft: '2px', marginBottom: '10px'}}>
                    {/*<Link to={Studio}>*/}
                    {
                      showStudioButton &&
                      <Button style={{marginLeft: '10px', marginRight: '10px'}} type="primary" shape="circle"size={'default'} onClick={() => this.setState({showIframe: true})} >Use Studio</Button>
                    }
                    {/*</Link>*/}
                  </div>
                </>
              )}

              {selectedStage.artwork ? (
                <>
                  <div
                    key={selectedStage.artwork.id}
                    className="designer-batch-thumbnail"
                    style={{ margin: "0 10px", border: "1px solid #ccc" }}
                  >
                    <Button
                      onClick={this.onRemoveArtwork.bind(this)}
                      style={{ border: 0, boxShadow: "none", float: "right" }}
                    >
                      X
                    </Button>
                    <div>
                      <img
                        src={selectedStage.artwork.thumbUrl}
                        alt={selectedStage.artwork.fileName}
                        style={{ clear: "both" }}
                      />
                    </div>
                    <div
                      style={{
                        padding: "0 4px",
                        whiteSpace: "nowrap",
                        overflow: "hidden",
                        textOverflow: "ellipsis"
                      }}
                    >
                      {selectedStage.artwork.fileName}
                    </div>
                  </div>
                </>
              ) : null}
              <Divider
                style={
                  selectedStage && selectedStage.subLocationSettings.length > 1
                    ? { display: "inherit" }
                    : { display: "none" }
                }
              />

              <div className="art-placement-container">
                {selectedStage && this.renderArtPlacement(selectedStage)}
                {selectedStage && this.renderOffsets(selectedStage)}
              </div>
            </>
            {this.state.products.length > 1 ? (
              <>
                <Divider />
                <Tabs
                  defaultActiveKey="0"
                  activeKey={String(this.state.activeProductOptionsTab)}
                  onTabClick={this.onProductOptionsTabClicked.bind(this)}
                >
                  {this.state.products.map(product => (
                    <TabPane tab={product.name} key={String(product.id)}>
                      {this.renderOptions(product.options,product)}
                    </TabPane>
                  ))}
                </Tabs>
              </>
            ) : unionOptions.length ? (
              <>
                <Divider />

                {this.renderOptions(unionOptions)}
              </>
            ) : null}
          </Col>
          <Col xs={24} md={24} lg={12} className="product-preview-container">
            <Drawer
              title={selectedProduct.name}
              placement="right"
              closable={false}
              onClose={() => {
                this.setState({ drawerVisible: false });
              }}
              visible={drawerVisible}
              width="40%"
            >
              {ReactHtmlParser(selectedProduct.bestPractices)}
            </Drawer>
            <Row style={{ maxWidth: "100%" }}>
              <Col style={{ textAlign: "center" }}>
                <div style={{ display: "inline-block", position: "relative" }}>
                  {/*Variant Image*/}
                  {this.renderVariantImage(variantImageUrl, variantImage2Url)}

                  {selectedProduct.categoryDisplay !== 'Monogram' && selectedStage && selectedStage.image ? (
                    <>
                      {/*Template Image*/}
                      <div
                        style={{
                          display: "inline-block",
                          position: "relative"
                        }}
                      >
                        <img
                          src={selectedStage2.image.fileUrl}
                          alt={selectedStage2.image.description}
                          className="responsive"
                          style={{ ...styles.templateStyles, maxHeight:"500px" }}
                        />

                        {/*Selected Art Image*/}
                        {selectedStage.artwork ? (
                          <ProductStage
                            product={selectedProduct}
                            artwork={selectedStage.artwork}
                            selectedStage={selectedStage2}
                            selectedLocationId={selectedLocationId2}
                            selectedOffsetId={selectedOffsetId2}
                          />
                        ) : null}
                      </div>
                    </>
                  ) : null}

                  <Title
                    style={{
                      textAlign: "center",
                      fontSize: 32,
                      marginBottom: 0
                    }}
                  >
                    PREVIEW
                  </Title>
                </div>
                <div style={{ textAlign: "center" }}>
                  <img
                    src={DesignGuide}
                    style={{ cursor: "pointer", maxWidth: "96%" }}
                    alt={"Design Guide"}
                    onClick={() => this.setState({ drawerVisible: true })}
                  />
                </div>
              </Col>
            </Row>
          </Col>
        </Row>
        {
          this.state.showIframe &&
          <div style={{
            position: 'fixed',
            display: 'flex',
            width: '100%',
            height: '100%',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(0,0,0,0.5)',
            zIndex: 2000,
            justifyContent: 'center',
            alignItems: 'center'
          }}>
            <div
              style={{
                position: 'fixed',
                right: 20,
                top: 75,
                cursor: 'pointer'
              }}
              onClick={
                () => this.setState({showIframe: false})
              }
            >
              <CloseOutlined
                style={{
                  fontSize: 25
                }}
              />
            </div>
            <div style={{backgroundColor: 'white', width: '80%', height: '80%', position: 'fixed'}}>
              <Studio requirements={this.state.studioRequirements} />
            </div>
          </div>
        }
      </div>
    );
  }
}
