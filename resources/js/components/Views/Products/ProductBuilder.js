import React, {Component} from "react";
import {Redirect} from "react-router";
import {
  EditorState,
  convertToRaw,
  ContentState,
  convertFromHTML
} from "draft-js";
import draftToHtml from "draftjs-to-html";
import axios from "axios";
import {Col, Row, Steps, Button, message, Tag} from "antd";
import {Spin} from "antd/lib/index";

import {displayErrors} from "../../../utils/errorHandler";

import Designer from "./BuilderSteps/ProductDesigner";
import Details from "./BuilderSteps/ProductDetails";
import Publish from "./BuilderSteps/ProductPublish";
import {getVariantHistory, saveVariantHistory} from "../../../utils/history";

const {Step} = Steps;

/**
 *
 */
export default class ProductBuilder extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    const {location} = props;
    this.state = {
      current: 0,
      blank: location.state ? location.state.blank : {'id': this.props.match.params.id},
      batchProcess: location.state ? location.state.batchProcess : '',
      selectedStores: location.state ? location.state.selectedStores : [],
      storeCollection: location.state ? location.state.storeCollection : [],
      selectedBatches: location.state ? location.state.selectedBatches : this.props.match.params.id,
      isLoadingProduct: true,
      isCreatingProduct: false,
      isAllSelected: false,
      isAllOptionsSelected: [],
      variantImageUrl: "",
      options: [],
      title: "",
      orderHold: false,
      description: EditorState.createEmpty(),
      tags: [],
      uploadedImages: [],
      unionCreateTypes: [],
      selectedOptions: [],
      selectedOptionValues: [],
      validOptions: [],
      products: [],
      mockupsLocations: [],
      blankStageLocationIds: [],
      mockupIndex: [],
      productId: null,
      picturedProduct: {},
      selectedProduct: {}
    };
  }

  /**
   *
   */
  componentDidMount() {
    const {blank, selectedBatches} = this.state;
    let batchList = "";

    if (selectedBatches && selectedBatches.length) {
      batchList = `?ids=${selectedBatches
        .toString()
        .split(",")
        .join()}`;
    }

    axios
      .get(`/blanks${batchList ? batchList : "/" + blank.id}`)
      .then(res => {
        const {data} = res.data;
        if (!data) {
          Promise.reject("Product not found");
          return;
        }
        this.setupInitialState(data);
      })
      .catch(error => displayErrors(error));
  }

  /**
   *
   * @param {{}|[]} data
   */
  setupInitialState = data => {
    let modifiedData = data;
    let commonCreateTypes = [];

    if (!Array.isArray(data)) {
      modifiedData = [data];
    }

    const allStageGroups = this.getAllStageGroups(modifiedData);

    if (modifiedData.length > 1) {
      commonCreateTypes = this.unionLocations(modifiedData);
    }

    const storeDescription = modifiedData[0].descriptionStoreDefault;
    const formattedDescription = storeDescription
      ? EditorState.createWithContent(
        ContentState.createFromBlockArray(convertFromHTML(storeDescription))
      )
      : "";

    const selectedStageValues = modifiedData.map(product => {
      return {
        blankId: product.id,
        stageGroups: product.stageGroups.map(stageGroup => {
          return {
            id: stageGroup.id,
            name: stageGroup.name,
            stages: stageGroup.stages.map(stage => {
              return {
                stage: stage,
                location: stage.location,
                id: stage.id,
                blankId: product.id,
                blankStageId: stage.id,
                blankStageGroupId: stage.blankStageGroupId,
                blankStageLocationId: stage.blankStageLocationId,
                blankStageLocationSubId: stage.subLocationSettings[0]
                  ? stage.subLocationSettings[0].blankStageLocationSubId
                  : null,
                blankStageLocationSubOffsetId:
                  stage.subLocationSettings[0] &&
                  stage.subLocationSettings[0].offsets.length
                    ? stage.subLocationSettings[0].offsets[0]
                      .blankStageLocationSubOffsetId
                    : null,
                imageId: null,
                image: null,
                createTypeId: stage.createTypes[0].createTypeId
              };
            })
          };
        })
      };
    });

    this.setState(
      {
        products: modifiedData,
        selectedProduct: modifiedData[0],
        isLoadingProduct: false,
        options: modifiedData[0].options,
        description: formattedDescription,
        unionCreateTypes: commonCreateTypes,
        hasAtLeastOneCreateType: this.hasAtLeastOneCreateType(allStageGroups),
        selectedStageValues: selectedStageValues
      },
      () => this.loadVariantImage()
    );
  };

  onSelectedStageValuesChanged = selectedStageValues => {
    this.setState({selectedStageValues: selectedStageValues});
  };

  /**
   *
   * @param {Number} value
   */
  onVariantRemove = value => {
    const {validOptions} = this.state;
    const newOptions = [...validOptions];

    const index = newOptions.indexOf(
      newOptions.find(option => option.id === value)
    );

    newOptions.splice(index, 1);
    this.setState({validOptions: newOptions});
  };

  setSelectedMockup = value => {
    let newArr = [];
    value.map((result) => (
      newArr.push(result.blank_psd_id)
    ))
    this.setState({mockupIndex: newArr})
  }

  onVariantBatchRemove = selectedIds => {
    const {validOptions} = this.state;
    const newOptions = validOptions.filter(newOption => {
      const found = selectedIds.find(selectedId => selectedId === newOption.id);
      if (typeof found === "undefined") {
        return newOption;
      }
    });
    this.setState({validOptions: newOptions});
  };

  /**
   *
   */
  isVariantCountOverHundred = () => {
    const {
      validOptions,
      storeCollection = [],
      selectedStores = []
    } = this.state;
    let isExceeded = false;

    //Temporary limit
    isExceeded = validOptions.length > 100;

    if (!isExceeded) {
      storeCollection.forEach(store => {
        if (
          store.name === "Shopify" &&
          selectedStores.includes(String(store.id)) &&
          validOptions.length > 100
        ) {
          isExceeded = true;
        }
      });
    }

    return isExceeded;
  };
  /**
   *
   * @param {[]} products
   */
  getAllStageGroups = products => {
    const stageGroups = [];

    products.forEach(product => {
      product.stageGroups.forEach(stageGroup => {
        stageGroups.push(stageGroup);
      });
    });

    return stageGroups;
  };
  /**
   *
   * @param {[]} stageGroups
   * @returns {boolean}
   */
  hasAtLeastOneCreateType = stageGroups => {
    const createTypes = [];

    stageGroups.forEach(group => {
      group.stages.forEach(stage => {
        createTypes.push(Boolean(stage.createTypes.length));
      });
    });

    const hasAtLeastOneCreateType = !createTypes.every(type => !type);
    if (!hasAtLeastOneCreateType) {
      displayErrors("Product is currently unavailable");
    }

    return hasAtLeastOneCreateType;
  };
  /**
   *
   * @param {[]} products
   */
  unionLocations = products => {
    /**
     * Build up data structure.
     */
    const stageLocations = this.restructureLocations(products);

    /**
     * Get unique stages based on different selected products
     */
    const uniqueStages = this.getUniqueStagesByName(products, stageLocations);

    /**
     * Common Create types based on different selected products.
     */
    return this.getCommonCreateTypes(uniqueStages);
  };
  /**
   *
   * @param {[]} products
   */
  restructureLocations = products => {
    const allStageByLocations = {};

    products.forEach(product => {
      product.stageGroups.forEach(group => {
        group.stages.forEach(stage => {
          if (allStageByLocations[product.id]) {
            allStageByLocations[product.id].locations.push(
              stage.location.fullName.toLowerCase()
            );
          } else {
            Object.assign(allStageByLocations, {
              [product.id]: {
                name: group.name.toLowerCase(),
                locations: [stage.location.fullName.toLowerCase()]
              }
            });
          }
        });
      });
    });

    return allStageByLocations;
  };
  /**
   *
   * @param {[]} products
   * @param {{}} stageLocations
   */
  getUniqueStagesByName = (products, stageLocations) => {
    const allLocations = {};

    products.forEach(product => {
      product.stageGroups.forEach(group => {
        Object.keys(stageLocations).forEach(id => {
          if (Number(id) !== product.id) {
            if (group.name.toLowerCase() === stageLocations[id].name) {
              group.stages.forEach(stage => {
                if (allLocations[stage.location.fullName.toLowerCase()]) {
                  allLocations[
                    stage.location.fullName.toLowerCase()
                    ].stages.push(stage);
                } else {
                  Object.assign(allLocations, {
                    [stage.location.fullName.toLowerCase()]: {
                      stages: [stage]
                    }
                  });
                }
              });
            }
          }
        });
      });
    });

    return allLocations;
  };
  /**
   *
   * @param {{}} uniqueStages
   */
  getCommonCreateTypes = uniqueStages => {
    const matchedCreateTypes = [];
    const unionCreateTypes = [];

    Object.keys(uniqueStages).forEach(stages => {
      if (uniqueStages[stages].stages.length > 1) {
        uniqueStages[stages].stages.forEach(stage => {
          matchedCreateTypes.push(...stage.createTypes);
        });
      }
    });

    matchedCreateTypes.forEach(createTypes => {
      unionCreateTypes.push(createTypes);
    });

    return unionCreateTypes;
  };
  /**
   *
   * @param {string} value
   */
  onEditorStateChange = value => {
    this.setState({description: value});
  };
  /**
   *
   * @param {[]} event
   */
  onTagChange = event => {
    this.setState({tags: [...this.state.tags, event.target.value]});
  };
  /**
   *
   * @param {{}} removedTag
   */
  onTagClose = removedTag => {
    const tags = this.state.tags.filter(tag => tag !== removedTag);
    this.setState({tags});
  };
  /**
   *
   * @param {{}} event
   */
  onInputChange = event => {
    const {target} = event;

    this.setState({title: target.value});
  };
  /**
   *
   * @param {{}} checked
   */
  onOrderHoldToggle = checked => {
    this.setState({orderHold: checked});
  };
  /**
   *
   * @returns {{}}
   */
  getVariantsByProductId = () => {
    const {updatedVariants, validOptions} = this.state;
    const variants = {};

    updatedVariants.forEach(variant => {
      //Only send validOptions (selected variants)
      if (validOptions.findIndex(vo => vo.id === variant.id) !== -1) {
        if (variants[variant.blankId]) {
          variants[variant.blankId].push(variant);
        } else {
          variants[variant.blankId] = [variant];
        }
      }
    });

    return variants;
  };
  /**
   *
   * @param {string} description
   */
  createSingleProductFromBatch = description => {
    const {
      updatedVariants,
      validOptions,
      selectedStores,
      tags,
      title,
      orderHold,
      mockupIndex,
      selectedStageValues,
      products
    } = this.state;

    const images = this.getAllImageLocations(true);
    const variants = [];
    updatedVariants.forEach(variant => {
      //Only send validOptions (selected variants)
      if (validOptions.findIndex(vo => vo.id === variant.id) !== -1) {
        variants.push(variant);
      }
    });

    let stageFiles = this.getStageFiles();

    const postData = [
      {
        name: title,
        description,
        tags: [...tags],
        mockupIndex: mockupIndex,
        blankVariants: variants,
        stageFiles: stageFiles,
        orderHold: orderHold,
        platformStores: selectedStores.map(id => ({id}))
      }
    ];

    this.postData(postData);
  };
  /**
   *
   */
  onCreateProduct = async () => {
    const {
      updatedVariants,
      batchProcess,
      tags,
      title,
      orderHold,
      description,
      selectedStores,
      mockupIndex,
      selectedStageValues,
      products,
      selectedOptionValues,
    } = this.state;
    const descriptionValue = draftToHtml(
      convertToRaw(description.getCurrentContent())
    );
    const postData = [];

    if (this.state.isCreatingProduct) {
      return;
    }

    this.setState({isCreatingProduct: true});

    if (!title.length) {
      message.error("Product title required");
      this.setState({isCreatingProduct: false});
      return;
    }

    if (this.isVariantCountOverHundred()) {
      message.error("Please limit variants to a total of 100");
      this.setState({isCreatingProduct: false});
      return;
    }
    if (batchProcess === "one") {
      this.createSingleProductFromBatch(descriptionValue);
      this.setState({isCreatingProduct: false});
      return;
    }

    const images = this.getAllImageLocations(true);

    const variantsById = this.getVariantsByProductId();
    const variantPostData = Object.keys(variantsById).map(variant => {
      return variantsById[variant].map(values => {
        //delete values.blankId;
        return values;
      });
    });

    let stageFiles = this.getStageFiles();
    const blankId = products.map((value) => value.id)

    variantPostData.forEach(variants => {
      postData.push({
        name: title,
        description: descriptionValue,
        tags: [...tags],
        blankVariants: variants,
        orderHold: orderHold,
        mockupIndex: mockupIndex,
        stageFiles: stageFiles.filter(
          stageFile => stageFile.blankId === variants[0].blankId
        ),
        platformStores: selectedStores.map(id => ({id})),
        isMonogram: this.state.selectedProduct.categoryDisplay === 'Monogram'
      });
    });

    this.postData(postData);
  };

  getStageFiles = () => {
    const {selectedStageValues, products} = this.state;

    let stageFiles = [];
    selectedStageValues.forEach(product =>
      product.stageGroups.forEach(stageGroup => {
        stageGroup.stages.forEach(stage => {
          if (stage.imageId > 0) {
            let stageFile = {...stage};
            delete stageFile.stage;
            delete stageFile.location;
            delete stageFile.id;
            delete stageFile.image;
            stageFiles.push(stageFile);
          }
        });
      })
    );
    return stageFiles;
  };

  /**
   *
   * @param {[]} data
   */
  postData = data => {
    axios
      .post("/products", data)
      .then(res => {
        if (res.status === 200) {
          message.success("Product Created");
          this.setState({
            current: this.state.current + 1,
            productId: res.data.data.id
          });
        }
      })
      .catch(error => {
        displayErrors(error);
      })
      .finally(() => this.setState({isCreatingProduct: false}));
  };
  /**
   *
   */
  onDeselectAll = () => {
    this.setState({
      selectedOptions: [],
      selectedOptionValues: [],
      isAllSelected: false,
      isAllOptionsSelected: []
    });
  };
  /**
   *
   */
  isAllSelected = () => {
    const {
      products,
      selectedOptions,
      selectedOptionValues,
      selectedProduct
    } = this.state;
    let count = 0;
    let optionCounts = [];

    selectedProduct.options.forEach(option => {
      if (!optionCounts[option.name]) {
        optionCounts[option.name] = 0;
      }
      optionCounts[option.name] += option.values.length;
      count += option.values.length;
    });

    /**
     * On every option update, we need to be also updating the variant image to be displayed.
     */
    if (selectedProduct.useVariantImageAsTemplateBackground) {
      this.loadVariantImage();
    }

    const isAllOptionsSelected = [];
    selectedOptionValues[selectedProduct.id] =
      selectedOptionValues[selectedProduct.id] || [];

    for (let key of Object.keys(selectedOptionValues[selectedProduct.id])) {
      if (selectedOptionValues[selectedProduct.id][key].length) {
        isAllOptionsSelected[key] =
          selectedOptionValues[selectedProduct.id][key].length ===
          optionCounts[key];
      }
    }

    this.setState(
      {
        isAllSelected: selectedOptions.length === count,
        isAllOptionsSelected: isAllOptionsSelected
      },
      () => this.setValidVariants()
    );
  };
  /**
   *
   * @returns {string|undefined}
   */
  loadVariantImage = () => {
    const {selectedOptionValues, selectedProduct} = this.state;

    if (!selectedProduct.useVariantImageAsTemplateBackground) {
      return;
    }

    let picturedProduct = null;
    let variantImageOptions = selectedProduct.variantImageOptions;
    let variantImageOptionIds = variantImageOptions.map(
      vio => vio.blankOptionId
    );

    const imageProductOptions = selectedProduct.options.filter(option => {
      return variantImageOptionIds.findIndex(boi => boi === option.id) !== -1;
    });

    if (
      !imageProductOptions.length ||
      !Object.keys(selectedOptionValues).length ||
      selectedOptionValues[selectedProduct.id] === undefined
    ) {
      const targetVariant = selectedProduct.variants[0];
      if (targetVariant !== undefined) {
        this.setState({
          picturedProduct: selectedProduct,
          variantImageUrl: targetVariant.image
            ? targetVariant.image.fileUrl
            : null,
          variantImage2Url: targetVariant.image2
            ? targetVariant.image2.fileUrl
            : null
        });
      }
      //TODO: Can be silenced
      //console.error('Variant image is set, but no options found for the current value in admin select');
      return;
    }

    /**
     * Making sure I do have an option selected, otherwise don't continue.
     */
    if (Object.keys(selectedOptionValues).length === 0) {
      //TODO: Can be silenced
      console.error("Variant image is set, but no options selected");
      return;
    }

    let targetVariantOptionValues = {};

    //Find selectedValue for optionValue that is from the variantImageOptionValues (i.e. only "Color" option values determine variant image, so must ignore "Size")
    // We must first check if the selected product have color options select, else no need to load variant image.
    if (selectedOptionValues[selectedProduct.id]) {
      for (let optionName of Object.keys(
        selectedOptionValues[selectedProduct.id]
      )) {
        const selectedValues =
          selectedOptionValues[selectedProduct.id][optionName];

        let selectedValue = [];
        let i = selectedValues.length - 1;

        while (i >= 0 && selectedValue.length === 0) {
          const lastSelectedOption = selectedValues[i];
          selectedValue = imageProductOptions
            .map(productOption => {
              const filteredValues = productOption.values.filter(value => {
                return value.id === lastSelectedOption;
              });
              return filteredValues[0];
            })
            .filter(val => val);
          i--;
        }

        if (selectedValue.length) {
          targetVariantOptionValues[optionName] = selectedValue;
        }
      }
    }

    // /**
    //  * We get here when no selected value has been found, and variant image is set on admin.
    //  */
    // if (!selectedValues) {
    //   displayErrors('Not a valid selected value for displaying variant image.');
    //   return;
    // }

    //Iterate over variants for option
    // const variant = product.variants.find(variant => variant.optionValues.find(optionValue => optionValue.id === selectedValue.id));

    let targetVariant = null;
    selectedProduct.variants.find((variant, vIndex) => {
      const variantOptionValues = variant.optionValues
        .map((optionValue, optionValueIndex) => {
          let foundOptionValue = null;
          for (let key of Object.keys(targetVariantOptionValues)) {
            foundOptionValue = targetVariantOptionValues[key].find(
              targetOptionValue => targetOptionValue.name === optionValue.name
            );
            if (foundOptionValue) {
              return foundOptionValue;
            }
          }
        })
        .filter(val => val);

      const foundVariant =
        variantOptionValues.length ===
        Object.keys(targetVariantOptionValues).length;

      if (foundVariant) {
        picturedProduct = selectedProduct;
        targetVariant = variant;
      }
    });

    /**
     * This can come back as null, so we just do a minor check.
     */
    if (
      !targetVariant ||
      !targetVariant.image ||
      !targetVariant.image.fileUrl
    ) {
      //console.error('Variant image unavailable');
      return;
    }
    this.setState({
      picturedProduct: picturedProduct,
      variantImageUrl: targetVariant.thumbnail ? targetVariant.thumbnail : null,
      variantImage2Url: targetVariant.image2
        ? targetVariant.image2.fileUrl
        : null
    });
  };
  /**
   *setAllOptions
   */
  setAllOptions = () => {
    const {products} = this.state;
    const newProductInfo = [...products];
    const selectedOptions = [];
    const selectedOptionValues = [];

    newProductInfo.forEach(product => {
      product.options.forEach(option => {
        if (!selectedOptionValues[product.id][option.name]) {
          selectedOptionValues[product.id][option.name] = [];
        }
        option.values.forEach(val => {
          selectedOptions.push(val.id);
          selectedOptionValues[product.id][option.name].push(val.id);
        });
      });
    });

    this.setState({selectedOptions, selectedOptionValues}, () =>
      this.isAllSelected()
    );
  };

  /**
   *
   * @param {{}} targetOption
   */
  setOption = targetOption => {
    const {products, selectedOptions, selectedOptionValues} = this.state;
    const newProductInfo = [...products];
    const newSelectedOptions = [...selectedOptions];
    let newSelectedOptionValues = [];
    for (let key of Object.keys(selectedOptionValues)) {
      newSelectedOptionValues[key] = selectedOptionValues[key];
    }
    newProductInfo.forEach(product => {
      product.options.forEach(option => {
        if (targetOption.name === option.name) {
          if (!newSelectedOptionValues[product.id][option.name]) {
            newSelectedOptionValues[product.id][option.name] = [];
          }
          option.values.forEach(val => {
            if (newSelectedOptions.indexOf(val.id) === -1) {
              newSelectedOptions.push(val.id);
              newSelectedOptionValues[product.id][option.name].push(val.id);
            }
          });
        }
      });
    });

    this.setState(
      {
        selectedOptions: newSelectedOptions,
        selectedOptionValues: newSelectedOptionValues
      },
      () => this.isAllSelected()
    );
  };

  /**
   *
   * @param {{}} targetOption
   */
  unsetOption = targetOption => {
    const {products, selectedOptions, selectedOptionValues} = this.state;
    const newProductInfo = [...products];
    const newSelectedOptions = [...selectedOptions];
    let newSelectedOptionValues = [];
    for (let key of Object.keys(selectedOptionValues)) {
      newSelectedOptionValues[key] = selectedOptionValues[key];
    }

    newProductInfo.forEach(product => {
      product.options.forEach(option => {
        if (targetOption.name === option.name) {
          option.values.forEach(val => {
            let index = newSelectedOptions.indexOf(val.id);
            newSelectedOptions.splice(index, 1);

            index = newSelectedOptionValues[option.name].indexOf(val.id);
            newSelectedOptionValues[option.name].splice(index, 1);
          });
          if (!newSelectedOptionValues[option.name].length) {
            delete newSelectedOptionValues[option.name];
          }
        }
      });
    });

    this.setState(
      {
        selectedOptions: newSelectedOptions,
        selectedOptionValues: newSelectedOptionValues
      },
      () => this.isAllSelected()
    );
  };

  /**
   *
   * @param {{}} selected
   */
  onSelectedOptionBatch = selected => {
    const {products, selectedOptions, selectedOptionValues} = this.state;
    const {id} = selected;
    const newSelectedOptions = [...selectedOptions];
    let selectedOption = [];
    const newProducts = [...products];
    const optionNames = [];

    let newSelectedOptionValues = [];
    for (let key of Object.keys(selectedOptionValues)) {
      newSelectedOptionValues[key] = selectedOptionValues[key];
    }

    let selectedOptionValue = [];
    let optionName = null;

    newProducts.forEach(product => {
      product.options.forEach(option => {
        option.values.forEach(value => {
          if (value.id === id) {
            selectedOption.push(value.id);
            optionNames.push(value.name);

            optionName = option.name;
            if (!selectedOptionValue[optionName]) {
              selectedOptionValue[optionName] = [];
            }
            selectedOptionValue[optionName].push(value.id);
          }
        });
      });
    });

    /**
     * Handle batched products
     */
    if (products.length > 1) {
      const matchingOptions = this.getMatchingOptions(optionNames);
      selectedOption = [];
      matchingOptions.forEach(option => selectedOption.push(option.id));
      let matched = false;

      /**
       * Deselect all batch options
       */
      matchingOptions.forEach(option => {
        if (newSelectedOptions.includes(option.id)) {
          let index = newSelectedOptions.indexOf(option.id);
          newSelectedOptions.splice(index, 1);

          if (newSelectedOptionValues[optionName]) {
            index = newSelectedOptionValues[optionName].indexOf(id);
            newSelectedOptionValues[optionName].splice(index, 1);
            if (!newSelectedOptionValues[optionName].length) {
              delete newSelectedOptionValues[optionName];
            }
          }
          matched = true;
        }
      });

      /**
       * If we matched we must make sure to deselect
       * all values for that option.
       */
      if (matched) {
        this.setState(
          {
            selectedOptions: newSelectedOptions,
            selectedOptionValues: newSelectedOptionValues
          },
          () => this.isAllSelected()
        );
        return;
      }
    }

    /**
     * Deselect
     */
    if (newSelectedOptions.includes(id)) {
      let index = newSelectedOptions.indexOf(id);
      newSelectedOptions.splice(index, 1);

      if (newSelectedOptionValues[optionName]) {
        index = newSelectedOptionValues[optionName].indexOf(id);
        newSelectedOptionValues[optionName].splice(index, 1);
        if (!newSelectedOptionValues[optionName].length) {
          delete newSelectedOptionValues[optionName];
        }
      }

      this.setState(
        {
          selectedOptions: newSelectedOptions,
          selectedOptionValues: newSelectedOptionValues
        },
        () => this.isAllSelected()
      );
      return;
    }

    //Merge objects
    for (let key of Object.keys(selectedOptionValue)) {
      if (!newSelectedOptionValues[key]) {
        newSelectedOptionValues[key] = selectedOptionValue[key];
      } else {
        newSelectedOptionValues[key] = [
          ...newSelectedOptionValues[key],
          ...selectedOptionValue[key]
        ];
      }
    }

    this.setState(
      {
        selectedOptions: [...newSelectedOptions, ...selectedOption],
        selectedOptionValues: newSelectedOptionValues
      },
      () => this.isAllSelected()
    );
  };
  /**
   *
   * @param {[]} options
   */
  getMatchingOptions = options => {
    const {products} = this.state;
    const newProducts = [...products];
    const matchingOptions = [];

    newProducts.forEach(product => {
      product.options.forEach(option => {
        option.values.forEach(value => {
          options.forEach(match => {
            if (match === value.name) {
              matchingOptions.push(value);
            }
          });
        });
      });
    });

    return matchingOptions;
  };
  /**
   *
   * @param {boolean} isSelected
   * @param {{}} value
   * @param {boolean} isAllSelected
   * @deprecated
   */
  onSelectedOption = (isSelected, value, isAllSelected = false) => {
    if (isAllSelected) {
      this.setAllOptions();
      return;
    }

    this.onSelectedProductOption(value);
    // this.onSelectedOptionBatch(value);
  };

  onSelectedProductOption = selected => {
    const {selectedOptions, selectedOptionValues, products} = this.state;

    products.forEach(product => {
      product.options.forEach(option => {
        if (selected.blankBlankOption.name === option.name) {
          selectedOptionValues[product.id] =
            selectedOptionValues[product.id] || [];
          selectedOptionValues[product.id][option.name] =
            selectedOptionValues[product.id][option.name] || [];
          option.values.forEach(val => {
            if (val.id === selected.id) {
              let index = selectedOptions.indexOf(val.id);
              if (index === -1) {
                selectedOptions.push(val.id);
                selectedOptionValues[product.id][option.name].push(val.id);
              } else {
                selectedOptions.splice(index, 1);
                index = selectedOptionValues[product.id][option.name].indexOf(
                  val.id
                );
                selectedOptionValues[product.id][option.name].splice(index, 1);
              }
            }
          });
        }
      });
    });

    this.setState(
      {
        selectedOptions: selectedOptions,
        selectedOptionValues: selectedOptionValues
      },
      () => this.isAllSelected()
    );
  };

  /**
   *
   * @param {{}} event
   */
  onInputChangePrice = event => {
    const {target} = event;
    this.setVariantPrice(target.name, target.value);
  };
  /**
   *
   * @param name
   * @param value
   */
  setVariantPrice = (name, value) => {
    const {updatedVariants} = this.state;
    const foundVariant = updatedVariants.find(
      variant => variant.id === Number(name)
    );

    if (!foundVariant.price) {
      Object.assign(foundVariant, {price: value});
    }

    foundVariant.price = value;

    // this.setState({ ...updatedVariants, foundVariant });
  };

  /**
   *
   * @param {[]} selectedVariants
   * @param {number} price
   */
  onUpdateSelectedVariantsPrice = (selectedVariants, price) => {
    const variants = this.state.validOptions;

    const updatedVariants = variants.map(variant => {
      if (
        selectedVariants.find(selectedId => {
          return Number(selectedId) === Number(variant.id);
        })
      ) {
        this.setVariantPrice(variant.id, price);
      }
      return variant;
    });

    this.setState({validOptions: updatedVariants});
  };

  /**
   * @TODO this won't work we need to change how image is attaching to state.
   */
  getAllImageLocations = (allProducts = false) => {
    const {products} = this.state;
    const uploadedImages = [];
    const firstProduct = products[0];
    const stageIndexes = [];

    const productsToProcess = allProducts ? products : [products[0]];

    productsToProcess.forEach((product, productIndex) => {
      product.stageGroups.forEach((stageGroup, stageGroupIndex) => {
        stageGroup.stages.forEach((stage, stageIndex) => {
          let firstProductStageIndex = stageIndexes.find(obj => {
            return (
              obj.stageGroupIndex === stageGroupIndex &&
              obj.stageIndex === stageIndex
            );
          });

          if (stage.artwork || firstProductStageIndex) {
            if (product.id === firstProduct.id) {
              stageIndexes.push({
                stageGroupIndex: stageGroupIndex,
                stageIndex: stageIndex,
                artworkId: stage.artwork.id
              });
            }

            const imageIds = {
              blankId: product.id,
              imageId: stage.artwork
                ? stage.artwork.id
                : firstProductStageIndex.artworkId,
              blankStageGroupId: stageGroup.id,
              blankStageId: stage.id,
              blankStageLocationId: stage.blankStageLocationId,
              blankStageLocationSubId: this.getBlankLocation(stage),
              createTypeId: stage.createTypes[0].createTypeId
            };

            /**
             * If location doesn't have an offset.
             */
            if (this.getBlankOffset(stage)) {
              Object.assign(imageIds, {
                blankStageLocationSubOffsetId: this.getBlankOffset(stage)
              });
            } else {
            }

            uploadedImages.push(imageIds);
          }
        });
      });
    });

    return uploadedImages;
  };
  /**
   *
   * @param {{}} stage
   * @returns {number|*}
   */
  getBlankLocation = stage => {
    /**
     * Get first locationID if none are selected.
     */
    if (!stage.selectedLocationId) {
      return stage.subLocationSettings[0].blankStageLocationSubId;
    }

    return stage.selectedLocationId;
  };
  /**
   *
   * @param {{}} stage
   */
  getBlankOffset = stage => {
    if (stage.subLocationSettings[0].offsets.length) {
      return stage.subLocationSettings[0].offsets[0]
        .blankStageLocationSubOffsetId;
    }

    return 0;
  };
  /**
   *
   */
  getProductDetails = () => {
    const {current, products} = this.state;
    const updatedVariants = [];

    if (!products.length) {
      return displayErrors("You must select at least 1 product");
    }

    /**
     * If only one variant is found, and no options are available
     * get all product(s) single variant. Skip any unnecessary processing.
     */

    const [singleVariants, singleVariantsIds] = this.getAllSingleVariants();

    // if (this.hasNoOptionsWithOneVariant()) {
    //   const [singleVariants, singleVariantsIds] = this.getAllSingleVariants();
    //   return this.setState({
    //     updatedVariants: singleVariantsIds,
    //     current: current + 1,
    //     validOptions: singleVariants,
    //   });
    // }

    /**
     * Get matching variants.
     */
    const [optionNames, matchedVariants] = this.getMatchingVariants();

    /**
     * Filter matches (i.e. I selected black small I shouldn't see blue small).
     */
    const filteredVariants = this.filterVariantsByOption(
      optionNames,
      matchedVariants
    );

    /**
     * Merge the filteredVariants with the ones with matching names.
     */
    const mergedVariants = this.findMatchingVariants(filteredVariants);

    /**
     * Used for posting.
     */
    mergedVariants.forEach(variant => {
      updatedVariants.push({
        blankId: variant.blankId,
        id: variant.id,
        weightUnitId: 1
      });
    });

    singleVariantsIds.forEach(variant => updatedVariants.push(variant));
    singleVariants.forEach(variant => mergedVariants.push(variant));

    const hasSelectedProductVariant = this.validateProductSelectedVariant(
      mergedVariants
    );
    const validateSelectedImage = this.validateSelectedImage();

    /*if (!mergedVariants.length) {
      return displayErrors('You must select at least 1 variant');
    }*/

    if (!hasSelectedProductVariant || !mergedVariants.length) {
      return displayErrors("You must select at least 1 variant per product");
    }
    if (!validateSelectedImage && products[0].categoryDisplay !== 'Monogram') {
      return displayErrors("You must select an Artwork Image");
    }

    saveVariantHistory(this.state.selectedOptionValues)
    this.setState({
      current: current + 1,
      updatedVariants,
      validOptions: mergedVariants
    });
  };

  validateProductSelectedVariant = mergedVariants => {
    const {products} = this.state;
    let isValid = false;
    let mergedVariantsIds = [];

    if (mergedVariants.length) {
      mergedVariants.forEach(mergedVariant => {
        mergedVariantsIds.push(mergedVariant.id);
      });
    }

    products.forEach(product => {
      if (product.variants.length >= 1) {
        const variants = product.variants;
        let productHasValidVariantSelected = false;
        variants.forEach(variant => {
          if (mergedVariantsIds.includes(variant.id)) {
            productHasValidVariantSelected = true;
          }
        });
        isValid = productHasValidVariantSelected !== false;
      }
    });

    return isValid;
  };

  validateSelectedImage = () => {
    const {selectedStageValues} = this.state;

    let hasArtWorkSelected = false;

    selectedStageValues.some(product => {
      let stageGroups = product.stageGroups;
      if (stageGroups) {
        stageGroups.some(group => {
          let stages = group.stages;
          if (stages) {
            stages.some(stage => {
              if (stage.image !== null) {
                hasArtWorkSelected = true;
                return true;
              }
            });
          }
        });
      }
    });
    return hasArtWorkSelected;
  };

  setValidVariants() {
    /**
     * If only one variant is found, and no options are available
     * get all product(s) single variant. Skip any unnecessary processing.
     */

    const [singleVariants, singleVariantsIds] = this.getAllSingleVariants();

    /**
     * Get matching variants.
     */
    const [optionNames, matchedVariants] = this.getMatchingVariants();

    /**
     * Filter matches (i.e. I selected black small I shouldn't see blue small).
     */
    const filteredVariants = this.filterVariantsByOption(
      optionNames,
      matchedVariants
    );

    /**
     * Merge the filteredVariants with the ones with matching names.
     */
    const mergedVariants = this.findMatchingVariants(filteredVariants);

    singleVariants.forEach(variant => mergedVariants.push(variant));

    this.setState({
      validOptions: mergedVariants
    });
  }

  /**
   *
   * @returns {[[], []]}
   */
  getAllSingleVariants = () => {
    const {products} = this.state;
    const variantsIds = [];
    const variants = [];

    /**
     * Only one available variant per product.
     */
    products.forEach(product => {
      if (!product.options.length && product.variants.length === 1) {
        variants.push(product.variants[0]);
      }
    });

    variants.forEach(variant => {
      variantsIds.push({
        id: variant.id,
        blankId: variant.blankId,
        weightUnitId: 1
      });
    });

    return [variants, variantsIds];
  };
  /**
   *
   * @returns {boolean}
   */
  hasNoOptionsWithOneVariant = () => {
    const {products} = this.state;
    let hasNoOptions = true;

    products.forEach(product => {
      if (product.options.length && product.variants.length > 1) {
        hasNoOptions = false;
      }
    });

    return hasNoOptions;
  };
  /**
   *
   */
  getMatchingVariants = () => {
    const {products, selectedOptions} = this.state;
    const selectedVariants = [];
    const uniqueVariants = [];
    const optionNames = [];
    const uniqueOptions = {};
    const ids = [];

    products.forEach(product => {
      product.variants.forEach(variant => {
        variant.optionValues.forEach(option => {
          if (selectedOptions.includes(option.id)) {
            selectedVariants.push(variant);
            optionNames.push({[variant.id]: [option.name]});
          }
        });
      });
    });

    /**
     * We have to remove duplicates as the above may cause
     * black small and small black to be pushed.
     */
    selectedVariants.forEach(variant => {
      if (!ids.includes(variant.id)) {
        uniqueVariants.push(variant);
        ids.push(variant.id);
      }
    });

    /**
     * {
     *   [key_of_variant]: [option1, option2...],
     *   ...
     * }
     */
    optionNames.forEach(option => {
      if (!uniqueOptions[Object.keys(option)[0]]) {
        Object.assign(uniqueOptions, {
          [Object.keys(option)[0]]: Object.values(option)[0]
        });
      } else {
        uniqueOptions[Object.keys(option)[0]].push(...Object.values(option)[0]);
      }
    });

    return [uniqueOptions, uniqueVariants];
  };
  /**
   *
   * @param options
   * @param {{}} variants
   */
  filterVariantsByOption = (options, variants) => {
    const foundOptions = {};
    const validVariants = [];

    /**
     * Storing each option values as an array found off from
     * a variant so we can compare to selected.
     */
    variants.forEach(variant => {
      variant.optionValues.forEach(option => {
        if (!foundOptions[variant.id]) {
          Object.assign(foundOptions, {[variant.id]: [option.name]});
        } else {
          foundOptions[variant.id].push(option.name);
        }
      });
    });

    variants.forEach(variant => {
      if (this.isMatchedArray(foundOptions[variant.id], options[variant.id])) {
        validVariants.push(variant);
      }
    });

    return validVariants;
  };
  /**
   *
   * @param {[]} filteredVariants
   */
  findMatchingVariants = filteredVariants => {
    const {products} = this.state;
    const uniqueOptions = []; // finding the common option ids.
    const commonOptions = {};
    const newVariants = [];
    const final = [];
    const variantIds = [];

    products.forEach(product => {
      product.options.forEach(option => {
        uniqueOptions.push(option.id);
      });
    });

    /**
     * Get the duplicated options and store all the option names.
     * (skip the first product, we merged with the first).
     */
    const duplicateValues = uniqueOptions.filter(
      (value, index) =>
        uniqueOptions.indexOf(value) === index &&
        uniqueOptions.lastIndexOf(value) !== index
    );
    filteredVariants.forEach(filteredVariant => {
      filteredVariant.optionValues.forEach(option => {
        if (!commonOptions[filteredVariant.id]) {
          Object.assign(commonOptions, {
            [filteredVariant.id]: [option.name.toLowerCase()]
          });
        } else {
          commonOptions[filteredVariant.id].push(option.name.toLowerCase());
        }
      });
    });

    /**
     * If the length of the variants is smaller than the filtered variants
     * filter see if we have a matching name.
     */
    products.forEach(product => {
      product.variants.forEach(variant => {
        Object.values(commonOptions).forEach(option => {
          if (option.length > variant.optionValues.length) {
            variant.optionValues.forEach(optionVal => {
              option.forEach(val => {
                if (optionVal.name.toLowerCase() === val) {
                  newVariants.push(variant);
                }
              });
            });
          }
        });
      });
    });

    /**
     * Remove any duplicates this would be caused by having
     * ['s', 'black'], ['black', 's']
     */
    newVariants.forEach(variant => {
      if (!variantIds.includes(variant.id)) {
        final.push(variant);
        variantIds.push(variant.id);
      }
    });

    return [...filteredVariants, ...final];
  };
  /**
   *
   * @param {[]} arr
   * @param {[]} arr2
   * @returns {*}
   */
  isMatchedArray = (arr, arr2) => {
    return arr.every(i => arr2.includes(i));
  };
  /**
   *
   */
  onPrevStep = () => {
    const current = this.state.current - 1;
    if (current < 0) {
      this.props.history.push("/catalog");
    } else {
      this.setState({current});
    }
  };


  /**
   * Name: History-Selection
   * Role: the role of this function is to draw first time user start design
   * route example : "http://teelaunch-2.0.test/product-design/82"
   * this function works with the following technic
   * fist load all previous save item from local-storage segmented base on blank-id
   * example : {
      "82": [149, 150, 151, 152, 168],
      "83": [174, 181]
    }
   *the function start looping base on the product id in depth blank Id :
   * if she id of the blank is available inside the the history then draw selected and unselected base on it
   * if the id does not exsit follow the basic algorithm
   * 1-is Apparel them make by default all size (key used is name of options)items are selected
   * 2-is Wall Art then make all item all preselected
   * 3-do not make any selections
   */
  historySetup = () => {

    const history = getVariantHistory();
    const {products, selectedOptionValues} = this.state;
    let selectedOptions = []
    const newSelectedOptionValues = {};
    products?.forEach(product => {
      const historyIds = history[product.id];
      if (historyIds && historyIds.length > 0) {
        product.options.forEach(options => {
          newSelectedOptionValues[product.id] = newSelectedOptionValues[product.id] || [];
          newSelectedOptionValues[product.id][options.name] = newSelectedOptionValues[product.id][options.name] || [];
          options?.values?.forEach(option => {
            if (historyIds.includes(option.id)) {
              selectedOptions.push(option.id)
              newSelectedOptionValues[product.id][options.name]?.push(option.id);
            }
          })
        })
      } else {
        if (product.category.name == 'Apparel')
          product.options.forEach(options => {
            if (options.name == 'Size') {
              newSelectedOptionValues[product.id] = newSelectedOptionValues[product.id] || [];
              newSelectedOptionValues[product.id][options.name] = newSelectedOptionValues[product.id][options.name] || [];
              options?.values?.forEach(option => {
                selectedOptions.push(option.id)
                newSelectedOptionValues[product.id][options.name]?.push(option.id);
              })
            }
          })
        else if (product.category.name = 'Wall Art')
          product.options.forEach(options => {
            newSelectedOptionValues[product.id] = newSelectedOptionValues[product.id] || [];
            newSelectedOptionValues[product.id][options.name] = newSelectedOptionValues[product.id][options.name] || [];
            options?.values?.forEach(option => {
              selectedOptions.push(option.id)
              newSelectedOptionValues[product.id][options.name]?.push(option.id);
            })
          })
      }

    })
    this.setState({
      selectedOptions: selectedOptions,
      selectedOptionValues: newSelectedOptionValues
    }, () => this.isAllSelected())
  }


  selectAllProductOptionValues = (selectedOption, selectedProductId) => {
    const {
      products,
      selectedOptions,
      selectedOptionValues,
      selectedProduct
    } = this.state;
    const newSelectedOptionValues = {...selectedOptionValues};
    products.forEach(product => {
      if (selectedProductId && product.id !== selectedProductId) {
        return;
      }
      product.options.forEach(option => {
        if (product.categoryDisplay != "Wall Art") {
          if (selectedOption.id !== option.id) {
            return;
          }
        }
        newSelectedOptionValues[product.id] = newSelectedOptionValues[product.id] || [];
        newSelectedOptionValues[product.id][option.name] = newSelectedOptionValues[product.id][option.name] || [];
        option.values.forEach(val => {
          let index = selectedOptions.indexOf(val.id);
          if (index === -1) {
            selectedOptions.push(val.id);
            newSelectedOptionValues[product.id][option.name].push(val.id);
          }
        });
      });
    });
    this.setState(
      {
        selectedOptions: selectedOptions,
        selectedOptionValues: newSelectedOptionValues
      },
      () => this.isAllSelected()
    );
  };

  deselectAllProductOptionValues = (selectedOption, selectedProductId) => {
    const {
      products,
      selectedOptions,
      selectedOptionValues,
      selectedProduct
    } = this.state;

    products.forEach(product => {
      if (selectedProductId && product.id !== selectedProduct.id) {
        return;
      }
      product.options.forEach(option => {
        if (selectedOption.id !== option.id) {
          return;
        }
        option.values.forEach(val => {
          let index = selectedOptions.indexOf(val.id);
          if (index !== -1) {
            selectedOptions.splice(index, 1);
            index = selectedOptionValues[product.id][option.name].indexOf(
              val.id
            );
            selectedOptionValues[product.id][option.name].splice(index, 1);
          }
        });
      });
    });

    this.setState(
      {
        selectedOptions: selectedOptions,
        selectedOptionValues: selectedOptionValues
      },
      () => this.isAllSelected()
    );
  };

  setSelectedProduct = selectedProduct => {
    this.setState({selectedProduct: selectedProduct}, () => {
      this.isAllSelected();
      this.loadVariantImage();
    });
  };

  /**
   *
   * @returns {[]}
   * TODO might want to raise state up so we don't fetch the info again,
   * don't think its necessary right now.
   */
  getSteps() {
    const {blank} = this.state;

    return [
      {
        title: "Design",
        content: (
          <Designer
            blank={blank}
            data={this.state}
            onSelectedOption={this.onSelectedOption}
            onDeselectAll={this.onDeselectAll}
            isAllSelected={this.isAllSelected}
            setOption={this.setOption}
            unsetOption={this.unsetOption}
            picturedProduct={this.state.picturedProduct}
            variantImageUrl={this.state.variantImageUrl}
            variantImage2Url={this.state.variantImage2Url}
            selectedStageValues={this.state.selectedStageValues}
            onSelectedStageValuesChanged={this.onSelectedStageValuesChanged}
            selectAllProductOptionValues={this.selectAllProductOptionValues}
            historySetup={this.historySetup}
            deselectAllProductOptionValues={this.deselectAllProductOptionValues}
            setSelectedProduct={this.setSelectedProduct}
          />
        ),
        text: "Next",
        method: this.getProductDetails
      },
      {
        title: "Details",
        content: (
          <Details
            blank={blank}
            data={this.state}
            onOrderHoldToggle={this.onOrderHoldToggle}
            onInputChange={this.onInputChange}
            onInputChangePrice={this.onInputChangePrice}
            onEditorStateChange={this.onEditorStateChange}
            onVariantRemove={this.onVariantRemove}
            onVariantBatchRemove={this.onVariantBatchRemove}
            description={this.state.description}
            onTagChange={this.onTagChange}
            onTagClose={this.onTagClose}
            tags={this.state.tags}
            setVariantPrice={this.setVariantPrice}
            onUpdateSelectedVariantsPrice={this.onUpdateSelectedVariantsPrice}
            getStageFiles={this.getStageFiles}
            setSelectedMockup={this.setSelectedMockup}
          />
        ),
        text: "Create",
        method: this.onCreateProduct
      },
      {
        title: "Publish",
        content: (
          <Publish
            history={this.props.history}
            productId={this.state.productId}
            selectedProductName={this.state.selectedProduct.name}
          />
        ),
        method: () => {}
      }
    ];
  }

  /**
   *
   * @returns {*}
   */
  render() {
    const {current, isLoadingProduct, hasAtLeastOneCreateType} = this.state;
    const steps = this.getSteps();

    if (isLoadingProduct) return <Spin/>;

    if (!hasAtLeastOneCreateType) return <Redirect to={"/catalog"}/>;

    return (
      <div>
        <Row>
          <Col span={24}>
            <Steps current={current}>
              {steps.map(item => (
                <Step key={item.title} title={item.title}/>
              ))}
            </Steps>
            <div className="steps-content pt-3">{steps[current].content}</div>
          </Col>
        </Row>
        <div
          className="product-builder-footer"
          style={
            this.state.current <= 1 ? {display: "block"} : {display: "none"}
          }
        >
          <Row>
            <Col>
              <div
                className="steps-action"
                style={{
                  marginBottom: "0",
                  display: "flex",
                  justifyContent: "center",
                  width: "100%"
                }}
              >
                {current !== steps.length - 1 && (
                  <Button
                    onClick={() => {
                      this.onPrevStep();
                      window.scrollTo(0, 0);
                    }}
                  >
                    Previous
                  </Button>
                )}
                {current < steps.length - 1 && (
                  <Button
                    disabled={this.state.isCreatingProduct}
                    loading={this.state.isCreatingProduct}
                    type="primary"
                    onClick={() => {
                      steps[current].method();
                      window.scrollTo(0, 0);
                    }}
                  >
                    {steps[current].text}
                  </Button>
                )}
              </div>
            </Col>
          </Row>
        </div>
      </div>
    );
  }
}
