import React, { Component, Fragment } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import {
  Col,
  Row,
  Tag,
  Card,
  Button,
  Modal,
  Checkbox,
  Spin,
  message
} from "antd";
import SideNav from "../../Components/SideNav/SideNav";
import ProductCard from "../../Components/Cards/ProductCard";
import { axiosConfig } from "../../../utils/axios";
import { displayErrors } from "../../../utils/errorHandler";
import Title from "antd/es/typography/Title";

import DesignTips from "../../../../images/design-tips.svg";
import StoreSelect from "../../Components/Modals/StoreSelect";

/**
 *
 */
export default class Catalog extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    this.state = {
      batchList: [],
      selectedBatchList: [],
      blankCollection: [],
      sideNavCategories: [],
      modalVisible: false,
      loading: true,
      selectedProduct: {},
      isLoadingSideNav: true,
      selected: 0,
      storeCollection: [],
      selectedStores: [],
      cachedCategories: {},
      batchProcess: ""
    };

    axiosConfig(axios, props.history);
  }

  /**
   *
   */
  componentDidMount() {
    axios
      .get(`/categories`)
      .then(res => {
        if (res.status === 200) {
          const { data } = res.data;
          /**
           * We get this as a flat object we must find all
           * children and children of children
           */
          this.reformatData(data);
          this.setState({
            isLoadingSideNav: false
          });
          this.getStoreCollection();
        }
      })
      .catch(error => displayErrors(error));
  }

  /**
   *
   * @param {{}} data
   */
  reformatData(data) {
    const newData = [...data];
    const parentModels = [];
    const childModels = [];
    const bachable = [];

    newData.forEach(item => {
      /**
       * All `blankCategoryParentId` will have an id set (i.e. not 0)
       * we then have to attach it to the corresponding parent id
       */
      if (item.blankCategoryParentId) {
        childModels.push(item);
      } else {
        /**
         * No parents
         */
        parentModels.push(item);
      }

      if (item.isBatchable) {
        bachable.push(item.id);
      }
    });

    this.setState({ batchList: bachable });
    /**
     * End any processing if there are no child categories.
     */
    if (!childModels.length && parentModels.length) {
      this.setState({ sideNavCategories: parentModels }, () =>
        this.getFirstCategories()
      );
      return;
    }

    this.attachChildModels(parentModels, childModels);
  }

  /**
   *
   * @param {[]} parentModels
   * @param {{}} childModels
   * TODO use recursion!
   */
  attachChildModels(parentModels, childModels) {
    const updatedModels = [];
    const ids = [];

    childModels.forEach(child => {
      parentModels.forEach(parent => {
        if (parent.id === child.blankCategoryParentId) {
          if (ids.includes(child.blankCategoryParentId)) {
            const found = updatedModels.find(
              model => model.id === child.blankCategoryParentId
            );
            found.children.push(child);
            return;
          }

          updatedModels.push({
            ...parent,
            children: [child]
          });

          ids.push(child.blankCategoryParentId);
        }
      });
    });

    /**
     * Nested children children O^3
     */
    const childIds = [];
    childModels.forEach(child => {
      if (!ids.includes(child.blankCategoryParentId)) {
        updatedModels.forEach(model => {
          if (model.children) {
            model.children.forEach(modelChild => {
              if (modelChild.id === child.blankCategoryParentId) {
                if (childIds.includes(modelChild.id)) {
                  modelChild.children.push(child);
                  return;
                }
                Object.assign(modelChild, { children: [child] });
                childIds.push(modelChild.id);
              }
            });
          }
        });
      }
    });

    /**
     * Nested children children children O^4
     */
    const childChildIds = [];
    childModels.forEach(child => {
      if (!childIds.includes(child.id)) {
        updatedModels.forEach(model => {
          if (model.children) {
            model.children.forEach(nestedChild => {
              if (nestedChild.children) {
                nestedChild.children.forEach(nestedNestedChild => {
                  if (nestedNestedChild.id === child.blankCategoryParentId) {
                    if (childChildIds.includes(nestedNestedChild.id)) {
                      nestedNestedChild.children.push(child);
                    }
                    Object.assign(nestedNestedChild, { children: [child] });
                    childChildIds.push(nestedNestedChild.id);
                  }
                });
              }
            });
          }
        });
      }
    });

    this.mergeModels(parentModels, updatedModels);
  }

  /**
   *
   * @param {[]} parentModels
   * @param {[]} updatedModels
   */
  mergeModels(parentModels, updatedModels) {
    const mergedModels = [];
    const childModels = [];
    const ids = [];

    parentModels.forEach(parent => {
      updatedModels.forEach(model => {
        if (parent.id === model.blankCategoryParentId) {
          childModels.push({
            ...parent,
            children: model
          });
        }
      });
    });

    if (!childModels.length && updatedModels.length) {
      childModels.push(...updatedModels);
    }

    parentModels.forEach(parent => {
      ids.push(parent.id);
      mergedModels.push(parent);
    });

    /**
     * Replace parent models with the nested categories.
     */
    childModels.forEach((child, val) => {
      if (ids.includes(child.id)) {
        const found = mergedModels.find(model => model.id === child.id);
        Object.assign(found, child);
        return;
      }
      mergedModels.push(child);
    });

    this.setState({ sideNavCategories: mergedModels }, () =>
      this.getFirstCategories()
    );
  }

  /**
   *
   */
  getStoreCollection() {
    axios
      .get("/platforms")
      .then(res => {
        if (res.data) {
          const stores = res.data.data;
          this.setState({
            storeCollection: stores,
            selectedStores: this.selectAllStores(stores)
          });
        }
      })
      .catch(error => displayErrors(error));
  }

  /**
   *
   */
  handleStoresSelected = () => {
    const {
      selectedProduct,
      selectedBatchList,
      selectedStores,
      storeCollection,
      batchProcess
    } = this.state;

    this.setState({ loading: true });

    this.props.history.push(
      `/product-design/${this.isBatchOrSingleProduct()}`,
      {
        blank: selectedProduct,
        selectedStores: selectedStores,
        storeCollection,
        selectedBatches: selectedBatchList,
        batchProcess
      }
    );
  };
  /**
   *
   */
  getFirstCategories = () => {
    const { sideNavCategories } = this.state;

    if (!sideNavCategories.length) {
      console.error("No categories to choose from");
      return;
    }

    const selected = sideNavCategories[0].id;
    axios
      .get(`/categories/${Number(selected)}/blanks`)
      .then(res => {
        if (res.data) {
          const { data } = res.data;
          this.setState({
            selected: String(sideNavCategories[0].id),
            blankCollection: data,
            cachedCategories: {
              [selected]: data
            },
            loading:false
          });
        }
      })
      .catch(error => displayErrors(error));
  };
  /**
   *
   * @param {{}} event
   */
  onSideNavClick = event => {
    const { key } = event;

    if (!Number(key)) {
      console.error("There must be a unique number key set on the nav");
      return;
    }

    /**
     * TODO set a loading state, for the newly selected category.
     */
    this.setState({ selected: String(key), loading:true});

    /**
     * Get category from state.
     */
    if (this.state.cachedCategories[key]) {
      this.setState({
        blankCollection: this.state.cachedCategories[key],
        selectedBatchList: [],
        loading:false
      });
      return;
    }

    axios
      .get(`/categories/${Number(key)}/blanks`)
      .then(res => {
        if (!res.data) {
          message.error("no category found");
          return;
        }

        this.setState(prevState => ({
          blankCollection: res.data.data,
          cachedCategories: {
            ...prevState.cachedCategories,
            [key]: res.data.data
          },
          selectedBatchList: [],
          loading:false
        }));
      })
      .catch(error => displayErrors(error));
  };
  /**
   *
   * @param {[]} stores
   * @returns {*[]|*}
   */
  selectAllStores = stores => {
    const selectedStores = [];

    if (!stores.length) {
      return [];
    }

    stores.forEach(platform => {
      if(platform.name === 'Rutter'){
        Object.keys(platform.stores).map((store) => {
          platform.stores[store].map((integrations) => {
              selectedStores.push(String(integrations.id));
            })
        });
      }
      else{
        platform.stores.forEach(store => {
          selectedStores.push(String(store.id));
        });
      }
    });

    return selectedStores;
  };

  /**
   *
   * @param {{}} event
   */
  onStoreChange = event => {
    const { checked, name } = event.target;
    const { selectedStores } = this.state;
    let newSelectedStores = [...selectedStores];

    if (checked) {
      newSelectedStores.push(name);
    } else {
      newSelectedStores = newSelectedStores.filter(id => id !== name);
    }

    this.setState(prevState => {
      return {
        ...prevState,
        selectedStores: newSelectedStores
      };
    });
  };

  /**
   *
   * @param {{}} e
   */
  handleCancel = e => {
    this.setState({
      modalVisible: false,
      selectedProduct: {}
    });
  };
  /**
   *
   * @returns {number}
   */
  getTotalStores = () => {
    const { storeCollection } = this.state;

    return storeCollection.reduce((accumulator, current) => {
      return (
        accumulator + parseInt(current.stores.length)
      );
    }, 0);
  };
  /**
   *
   * @param {{}} childData
   */
  chooseStore = childData => {
    const totalStores = this.getTotalStores();

    if (totalStores === 0) {
      return this.setState(
        {
          selectedProduct: childData
        },
        this.handleStoresSelected
      );
    }

    this.setState({
      modalVisible: true,
      selectedProduct: childData
    });
  };

  onStoreChange = event => {
    const { checked, name } = event.target;
    const { selectedStores } = this.state;
    let newSelectedStores = [...selectedStores];

    if (checked) {
      newSelectedStores.push(name);
    } else {
      newSelectedStores = newSelectedStores.filter(id => id !== name);
    }

    this.setState(prevState => {
      return {
        ...prevState,
        selectedStores: newSelectedStores
      };
    });
  };

  /**
   *
   * @param {string} batchProcess
   */
  chooseStoreBatch = batchProcess => {
    const totalStores = this.getTotalStores();

    if (totalStores === 0) {
      return this.setState(
        {
          selectedProduct: this.state.selectedBatchList,
          batchProcess
        },
        this.handleStoresSelected
      );
    }

    this.setState({
      modalVisible: true,
      selectedProduct: this.state.selectedBatchList,
      batchProcess
    });
  };
  /**
   *
   * @param event
   * @param {{}} product
   */
  batchProducts = (event, product) => {
    event.preventDefault();

    const { selectedBatchList } = this.state;

    if (!selectedBatchList.includes(product.id)) {
      if (selectedBatchList.length >= 6) {
        displayErrors("You may only batch up to 6 products at a time");
        return;
      }
      this.setState({ selectedBatchList: [...selectedBatchList, product.id] });
    } else {
      selectedBatchList.splice(selectedBatchList.indexOf(product.id), 1);
      this.setState({ selectedBatchList });
    }
  };
  /**
   *
   * @param {{}} product
   */
  removeBatch = product => {
    const newBatchList = [...this.state.selectedBatchList];

    newBatchList.map(item => {
      if (item === product.id) {
        newBatchList.splice(newBatchList.indexOf(item), 1);
      }
    });

    this.setState({ selectedBatchList: newBatchList });
  };
  /**
   *
   * @returns {string|*}
   */
  displayBatch = () => {
    const { selectedBatchList, blankCollection } = this.state;

    if (!selectedBatchList.length) {
      return <div style={{ height: 78 }} />;
    }

    return (
      <Card>
        {selectedBatchList.map(product => {
          const selectedProduct = blankCollection.find(
            item => item.id === product
          );

          if (!selectedProduct) {
            return null;
          }

          return (
            <Tag
              closable
              key={selectedProduct.id}
              onClose={e => {
                e.preventDefault();
                this.removeBatch(selectedProduct);
              }}
              style={{
                padding: "4px 8px",
                marginBottom: 8,
                fontSize: "1em"
              }}
            >
              {selectedProduct.name}
            </Tag>
          );
        })}
        <div style={{ marginTop: 16, float: "right" }}>
          <Button
            type="primary"
            style={{ marginLeft: "10px" }}
            onClick={() => this.chooseStoreBatch("one")}
          >
            Create As One Product
          </Button>
          <Button
            style={{ marginLeft: "10px" }}
            onClick={() => this.chooseStoreBatch("single")}
          >
            Create As Multiple Products
          </Button>
        </div>
      </Card>
    );
  };

  /**
   *
   * @returns {*}
   */
  getStoreModalContent = () => {
    const { storeCollection } = this.state;

    return (
      <>
        {storeCollection.map((platform, val) => (
          <div
            key={platform.id}
            style={val > 0 ? { marginTop: "10px" } : { marginTop: "-5px" }}
          >
            {platform.stores.length > 0 && <h3>{platform.name}</h3>}
            {platform.stores.length
              ? platform.stores.map(store => (
                  <Row key={store.id}>
                    <Col>
                      <Checkbox
                        defaultChecked={true}
                        key={store.id}
                        name={String(store.id)}
                        onChange={this.onStoreChange}
                      >
                        {store.name}
                      </Checkbox>
                    </Col>
                  </Row>
                ))
              : ""}
          </div>
        ))}
      </>
    );
  };

  getStoreModalFooter = () => {
    return [
      <Button key="back" onClick={this.handleCancel}>
        Cancel
      </Button>,
      <Button key="start" type="primary" onClick={this.handleStoresSelected}>
        Start
      </Button>
    ];
  };
  /**
   *
   * @returns {*}
   */
  isBatchOrSingleProduct = () => {
    const { selectedProduct } = this.state;

    /**
     * Selected product is a list of ids (batch process)
     */
    if (Array.isArray(selectedProduct)) {
      return selectedProduct;
    }

    return selectedProduct.id;
  };
  /**
   *
   * @returns {*[]}
   */

  /**
   *
   * @returns {*}
   */
  render() {
    const {
      blankCollection,
      sideNavCategories,
      isLoadingSideNav,
      selected,
      batchList,
      modalVisible,
      selectedBatchList
    } = this.state;
    const selectedNav = Number(selected);

    return (
      <Fragment>
        <Row gutter={{ xs: 8, md: 24, lg: 32 }}>
          <Col xs={24} md={4}>
            <SideNav
              isLoadingSideNav={isLoadingSideNav}
              menuItems={sideNavCategories}
              clickHandler={this.onSideNavClick}
              selected={selected}
            />
            <img
              src={DesignTips}
              style={{ cursor: "pointer", marginTop: 32, maxWidth: "90%" }}
              alt={"Design Tips and Tricks"}
              onClick={() => {
                window.open("https://blog.teelaunch.com/", "_blank");
              }}
            />
          </Col>
          <Col xs={24} md={19}>
            <Row gutter={16}>
              <Col span={24}>
                <Title>Create a Product</Title>
              </Col>
              <Col xs={24} md={24}>
                {this.displayBatch()}
              </Col>
              <Spin spinning={this.state.loading} >
                {
                  blankCollection.length && !this.state.loading &&
                  blankCollection.map(item => (
                    <Col
                      xs={24}
                      sm={24}
                      md={12}
                      lg={6}
                      style={{ marginTop: 15 }}
                      key={item.id}
                    >
                      {!this.state.loading &&
                      <ProductCard
                        product={item}
                        batchProducts={this.batchProducts}
                        chooseStore={this.chooseStore}
                        inBatch={batchList.includes(selectedNav) ? true : false}
                        batchState={selectedBatchList.includes(item.id)}
                        batchTheme={
                          selectedBatchList.includes(item.id)
                            ? "twoTone"
                            : "outlined"
                        }
                        // TODO we need tags if its not available we need to not display this.
                        // TODO there is a story in the admin section to set this up.
                        // desc={
                        //   [<Tag key={key}>${item.price}</Tag>,
                        //   item.tags.map((tag, index) => {
                        //   return (
                        //       <Tag key={index}>{tag}</Tag>
                        //   )
                        // })]}
                      />
                      }

                    </Col>
                  ))}
                </Spin>
            </Row>
          </Col>
        </Row>
        <StoreSelect
          visible={modalVisible}
          onOk={this.handleStoresSelected}
          handleChange={this.onStoreChange}
          onCancel={this.handleCancel}
          getStoreModalFooter={this.getStoreModalFooter}
          defaultChecked={true}
        />
      </Fragment>
    );
  }
}
