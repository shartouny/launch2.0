import React, { Component } from "react";
import { Editor } from "react-draft-wysiwyg";
import {
  Form,
  Input,
  Select,
  Icon,
  Tag,
  Avatar,
  Table,
  Col,
  Row,
  Button,
  Switch,
  Card,
  Checkbox,
  Tabs,
  message
} from 'antd';

const { Search } = Input;
const { TabPane } = Tabs;

import 'react-draft-wysiwyg/dist/react-draft-wysiwyg.css';
import BatchActions from '../Buttons/BatchActions';
import variantCustomize from '../../../utils/variantCustomize';

/**
 *
 */
class DesignerProductDetailsForm extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
    const {data} = this.props

    this.state = {
      weightUnits: [],
      tagInputValue: "",
      selectedRowKeys: [],
      sellPrice: 0,
      mockupsCounter: 0,
      selectedMockups: [],
      activeTab: 0,
      isNotMockup: false,
      selectedProducts: [],
      noMockup: [],
      hideCounter: false,
      stageFile: []
    };
  }

  /**
   *
   */
  componentDidMount() {
    const {data, setVariantPrice} = this.props;
    this.setVariantPrices();
    variantCustomize.variantPriceCustomize(data)
    // select all on load
    this.setState({
      selectedRowKeys: this.props.data.validOptions.map(item => item.id),
    });

    this.setState({activeTab: data.products[0].id})
    this.renderBlankMockups(data)

    data.validOptions.forEach(variant => {
      setVariantPrice(variant.id, variant.defaultPrice);
    });
  }

  componentWillUnmount() {
    const {data} = this.props;
    this.setVariantPrices();
    let imageID = []
    data.selectedStageValues[0].stageGroups.map((value, index) => {
      imageID.push(value.stages[0].imageId)
    })
    if (!imageID.includes(null) && imageID.length > 1) {
      data.validOptions = data.validOptions.map(variant => {
        variant.price = (Number(variant.price) - 5).toFixed(2);
        return variant;
      })
    }
  }

  renderBlankMockups(data) {
    data.products.map(product => {
      const selectedPsdMockups = {};

      if(product.category.id == 10){
         product.blankPsd.map(blankPsd => {
           if(data.selectedOptions.includes(blankPsd.blankOptionValueId)){
             if(!Object(selectedPsdMockups).hasOwnProperty(blankPsd.id)) {
               selectedPsdMockups[blankPsd.id] = blankPsd;
             }
           }
         })
       }
       else{
         product.blankPsd.map(blankPsd => {
           blankPsd.blankPsdLayer.map(psdLayer => {
             if(data.blankStageLocationIds.includes(psdLayer.blankStageId)){
               if(!Object(selectedPsdMockups).hasOwnProperty(blankPsd.id)) {
                 selectedPsdMockups[blankPsd.id] = blankPsd;
               }
             }
           })
         })
       }

       product.blankPsdUpdated = Object.values(selectedPsdMockups);
     })


    const filteredProduct = data.products.map((product) => (
        {
          productId: product.id,
          productName: product.name,
          blankPsd:
              product.blankPsdUpdated.filter((blankPsd) => {
                if (data.mockupsLocations.includes(blankPsd.blankStageGroupId)) {
                  if (data.options.length > 0) {
                    if (data.selectedOptions.includes(blankPsd.blankOptionValueId)) {
                      if (blankPsd.blankMockupImage) {
                        return (blankPsd.blankMockupImage)
                      }
                    }
                    else if (blankPsd.blankOptionValueId === null) {
                     if (blankPsd.blankMockupImage) {
                      return (blankPsd.blankMockupImage)
                     }
                    }
                  }
                  else {
                    if (blankPsd.blankMockupImage) {
                      return (blankPsd.blankMockupImage)
                    }
                  }
                }
                else{
                  if (blankPsd.blankMockupImage) {
                    return (blankPsd.blankMockupImage)
                  }
                }
              })
        }))
    this.setState({selectedProducts: [...filteredProduct]})
  }

  /**
   *
   */
  setVariantPrices = () => {
    const { setVariantPrice, data } = this.props;

    data.validOptions.forEach(variant => {
      setVariantPrice(variant.id, variant.defaultPrice);
    });
  };
  /**
   *
   * @param {{}} e
   */
  handleSubmit = e => {
    e.preventDefault();
    this.props.form.validateFields((err, values) => {});
  };
  /**
   *
   * @param {{}} event
   */
  onTagChange = event => {
    this.setState({ tagInputValue: event.target.value });
  };
  /**
   *
   * @param {{}} tag
   * @returns {*}
   */
  tagMap = tag => {
    const tagElem = (
      <Tag
        closable
        onClose={e => {
          e.preventDefault();
          this.props.onTagClose(tag);
        }}>
        {tag}
      </Tag>
    );
    return (
      <span key={tag} style={{ display: "inline-block" }}>
        {tagElem}
      </span>
    );
  };

  onSelectChange(selectedRowKeys) {
    this.setState({ selectedRowKeys });
  }

  /**
   *
   * @param {number} price
   */
  updateSelectedVariants(price) {
    const { selectedRowKeys } = this.state;
    const { onUpdateSelectedVariantsPrice } = this.props;
    onUpdateSelectedVariantsPrice(selectedRowKeys, price);

    const newPrices = [];
    this.props.data.updatedVariants.forEach(v => {
      newPrices[v.id] = v.price;
    });

    this.setState({ sellPrices: newPrices, selectedRowKeys: [] });
  }

  removeSelectedVariants() {
    const { selectedRowKeys } = this.state;
    const { onVariantBatchRemove } = this.props;
    onVariantBatchRemove(selectedRowKeys);
    this.setState({
      selectedRowKeys: [],
    });
  }

  /**
   *
   * @returns {*}
   */
  render() {
    const {
      tagInputValue,
      sellPrices,
      selectedRowKeys,
      sellPrice,
      newData,
    } = this.state;

    const {
      onOrderHoldToggle,
      onInputChange,
      onTagChange,
      onEditorStateChange,
      onVariantRemove,
      data,
      description,
      tags,
    } = this.props;
    const { getFieldDecorator } = this.props.form;

    let tagChild = (
      <span style={{ marginRight: 10 }}>To add tags please enter a tag:</span>
    );
    if (tags.length) {
      tagChild = tags.map(this.tagMap);
    }

    const rowSelection = {
      selectedRowKeys,
      onChange: this.onSelectChange.bind(this)
    };

    const hasSelected = selectedRowKeys.length > 0;

    /**
     *
     * @param {{}} event
     */
    const onInputChangePrice = event => {
      const { onInputChangePrice } = this.props;

      onInputChangePrice(event);

      this.setState({
        sellPrices: {
          ...this.state.sellPrices,
          [event.target.name]: event.target.value
        }
      });
    };

    const isSelectedMockup = (event) => {
      const { setSelectedMockup } = this.props;
      let action = event.target
        if (action.checked) {
          if (this.state.mockupsCounter <= 9) {
            this.setState({selectedMockups: [...this.state.selectedMockups , {productId: action.value.productId, mockupId: action.value.mockupId,
                  blank_option_value_id: action.value.blank_option_value_id, blank_psd_id: action.value.blank_psd_id}]},
              () => setSelectedMockup([...this.state.selectedMockups]))
            if (data.products.length > 1) {
              const newArr = this.state.selectedMockups.filter((value) =>
                value.productId == this.state.activeTab
              )
              if (data.batchProcess === 'one') {
                this.setState({mockupsCounter: this.state.selectedMockups.length + 1})
              } else {
                this.setState({mockupsCounter: newArr.length + 1})
              }
            } else {
              this.setState({mockupsCounter: this.state.selectedMockups.length + 1})
            }
        } else {
          const filteredArr = this.state.selectedMockups.filter((value) =>
            value.mockupId !== action.value.mockupId
          )
          const filteredTab = this.state.selectedMockups.filter((value) => {
            if (value.productId == this.state.activeTab) {
              return  value.mockupId !== action.value.mockupId
            }
          })
          this.setState({selectedMockups: [...filteredArr]})
          if (data.batchProcess === 'one') {
            this.setState({mockupsCounter: filteredArr.length})
          } else  {
            this.setState({mockupsCounter: filteredTab.length})
          }
          this.setState({disableCheckbox: true})
          message.info('Maximum of 10 mockups is allowed')
          setSelectedMockup(filteredArr)
        }
      } else {
          const filteredArr = this.state.selectedMockups.filter((value) =>
            value.mockupId !== action.value.mockupId
          )
          const filteredTab = this.state.selectedMockups.filter((value) => {
            if (value.productId == this.state.activeTab) {
              return  value.mockupId !== action.value.mockupId
            }
          })
          this.setState({selectedMockups: [...filteredArr]})
          if (data.batchProcess === 'one') {
            this.setState({mockupsCounter: filteredArr.length})
          } else  {
            this.setState({mockupsCounter: filteredTab.length})
          }
          setSelectedMockup(filteredArr)
        }
    }

    const onTabClick = (activeTab) => {
      const newFilter = this.state.selectedMockups.filter((value) => value.productId == activeTab)
      if (data.batchProcess === 'one') {
        this.setState({mockupsCounter: this.state.selectedMockups.length})
      } else {
        this.setState({mockupsCounter: newFilter.length})
        this.setState({activeTab: activeTab})
      }
    };

    // const optionColumns = data.options.map((option, index) => {
    //   //Add blankBlankOptionIds and optionNames to option prop so we can match correct option values as option order is not guaranteed
    //   data.options[index].blankBlankOptionIds = data.options[index].blankBlankOptionIds || [];
    //   data.options[index].optionNames = data.options[index].optionNames || [];
    //   option.values.forEach(val => {
    //     if (
    //       data.options[index].blankBlankOptionIds.indexOf(
    //         val.blankBlankOptionId,
    //       ) === -1
    //     ) {
    //       data.options[index].blankBlankOptionIds.push(val.blankBlankOptionId);
    //     }
    //     if (data.options[index].optionNames.indexOf(val.name) === -1) {
    //       data.options[index].optionNames.push(val.name);
    //     }
    //   });
    //
    //   let filters = [];
    //   data.validOptions.forEach(variant => {
    //     variant.optionValues.forEach(oVal => {
    //       if (
    //         data.options[index].blankBlankOptionIds.indexOf(
    //           oVal.blankBlankOptionId,
    //         ) > -1
    //       ) {
    //         if (
    //           filters.findIndex(filter => filter.value === oVal.name) === -1
    //         ) {
    //           filters.push({ value: oVal.name, text: oVal.name });
    //         }
    //       }
    //     });
    //   });
    //
    //   return {
    //     title: option.name,
    //     key: option.name,
    //     width: option.name === "Color" ? 120 : 80,
    //     render(value, variant) {
    //       let optionValue = variant.optionValues.find(
    //         optionValue =>
    //           data.options[index].blankBlankOptionIds.indexOf(
    //             optionValue.blankBlankOptionId,
    //           ) > -1,
    //       );
    //       if (!optionValue) {
    //         optionValue = variant.optionValues.find(optionValue => {
    //           return (
    //             data.options[index].optionNames.indexOf(optionValue.name) > -1
    //           );
    //         });
    //       }
    //       return optionValue ? optionValue.name : '';
    //     },
    //     filters: filters,
    //     filterMultiple: true,
    //     onFilter: (value, variant) => {
    //       return (
    //         variant.optionValues.findIndex(
    //           optionValue => optionValue.name === value,
    //         ) > -1
    //       );
    //     },
    //   };
    // });

    const optionColumns = data.products.map((product, index) => {
      return product.options.map((option, index) => {
        //Add blankBlankOptionIds and optionNames to option prop so we can match correct option values as option order is not guaranteed
        data.options[index].blankBlankOptionIds = data.options[index].blankBlankOptionIds || [];
        data.options[index].optionNames = data.options[index].optionNames || [];
        option.values.forEach(val => {
          if (data.options[index].blankBlankOptionIds.indexOf(val.blankBlankOptionId,) === -1) {
            data.options[index].blankBlankOptionIds.push(val.blankBlankOptionId);
          }
          if (data.options[index].optionNames.indexOf(val.name) === -1) {
            data.options[index].optionNames.push(val.name);
          }
        });

        let filters = [];
        data.validOptions.forEach(variant => {
          variant.optionValues.forEach(oVal => {
            if (data.options[index].blankBlankOptionIds.indexOf(oVal.blankBlankOptionId,) > -1) {
              if (filters.findIndex(filter => filter.value === oVal.name) === -1) {
                filters.push({ value: oVal.name, text: oVal.name });
              }
            }
          });
        });

        return {
          title: option.name,
          key: option.name,
          width: option.name === "Color" ? 120 : 80,
          render(value, variant) {
            let optionValue = variant.optionValues.find(
              optionValue =>
                data.options[index].blankBlankOptionIds.indexOf(
                  optionValue.blankBlankOptionId,
                ) > -1,
            );
            if (!optionValue) {
              optionValue = variant.optionValues.find(optionValue => {
                return (
                  data.options[index].optionNames.indexOf(optionValue.name) > -1
                );
              });
            }
            return optionValue ? optionValue.name : '';
          },
          filters: filters,
          filterMultiple: true,
          onFilter: (value, variant) => {
            return (
              variant.optionValues.findIndex(
                optionValue => optionValue.name === value,
              ) > -1
            );
          },
        };
      })
    });

    let variantColumns = [
      {
        title: "",
        key: "image",
        width: 42,
        render(value, variant) {
          return (
            <>
              <Avatar
                style={{ background: 'none' }}
                shape="square"
                size={32}
                icon={
                  <img
                    src={
                      variant && variant.thumbnail
                        ? variant.thumbnail
                        : variant.product.mainImageUrl
                    }
                  />
                }
              />
            </>
          );
        },
      },
      {
        title: "Product",
        key: "product",
        width: 140,
        render(value, variant) {
          return (
            <>
              <div>
                {data.products.map(product => {
                  if (variant.blankId === product.id) {
                    return product.name;
                  }
                })}
              </div>
            </>
          );
        },
        filters: data.products.map(product => {
          return { value: product.id, text: product.name };
        }),
        filterMultiple: true,
        onFilter: (value, variant) => {
          return variant.blankId === value;
        }
      },
      {
        title: "Sell Price",
        key: "sell price",
        width: 135,
        render(value, variant) {
          return (
            <>
              <Input
                name={variant.id}
                prefix="$"
                type="number"
                placeholder="0.00"
                onChange={onInputChangePrice}
                value={
                  sellPrices && typeof sellPrices[variant.id] !== 'undefined'
                    ? sellPrices[variant.id]
                    : variant.defaultPrice
                }
              />
            </>
          );
        },
      },
      {
        title: "Cost",
        key: "cost",
        width: 100,
        render(value, variant) {
          return <span>${variant.price}</span>;
        },
      },
      {
        title: "Profit",
        key: "profit",
        width: 100,
        render(value, variant) {
          return (
            <span>
              {sellPrices && sellPrices[variant.id]
                ? `$ ${parseFloat(
                    sellPrices[variant.id] - variant.price,
                  ).toFixed(2)}`
                : `$ ${parseFloat(variant.defaultPrice - variant.price).toFixed(
                    2,
                  )}`}
            </span>
          );
        },
      },
      // {
      //   title: '',
      //   key: 'remove',
      //   width: 60,
      //   render(value, variant) {
      //     return <Icon
      //       style={{ color: '#f5222d' }}
      //       type="close-circle"
      //       onClick={() => onVariantRemove(variant.id)}
      //     />
      //   }
      // },
    ];

    variantColumns.splice(2, 0, ...optionColumns[optionColumns.length - 1]);
    return (
      <Form onSubmit={this.handleSubmit} layout={"vertical"}>
        <Row>
          <Col xs={24} md={24} lg={9}>
            <Form.Item label="Title">
              {getFieldDecorator("title", {
                rules: [
                  {
                    required: true,
                    message: 'Please input product title!',
                  },
                ],
              })(
                <Input
                  type="text"
                  placeholder="Title"
                  onChange={e => onInputChange(e)}
                />
              )}
            </Form.Item>
            <Form.Item label="Description">
              <div className="box-border">
                <Editor
                  editorState={description}
                  wrapperClassName="demo-wrapper"
                  editorClassName="demo-editor"
                  onEditorStateChange={onEditorStateChange}
                  editorStyle={{ height: 300 }}
                />
              </div>
            </Form.Item>
            {/* TODO Display collection of stores */}
            <Form.Item
              label="Add to Collection/Category:"
              style={{ display: 'none' }}>
              <Select
                showSearch
                style={{ width: '100%' }}
                placeholder="Select a collection"
                optionFilterProp="children"
                filterOption={(input, option) =>
                  option.props.children
                    .toLowerCase()
                    .indexOf(input.toLowerCase()) >= 0
                }>
                <Select.Option value="collection-one">
                  Collection One
                </Select.Option>
                <Select.Option value="collection-two">
                  Collection Two
                </Select.Option>
                <Select.Option value="collection-three">
                  Collection Three
                </Select.Option>
              </Select>
            </Form.Item>
            <Form.Item label="Add product tags" style={{ display: "none" }}>
              {tagChild}
              <Input
                type="text"
                size="small"
                style={{ width: 78 }}
                value={tagInputValue}
                onChange={this.onTagChange}
                onPressEnter={onTagChange}
              />
            </Form.Item>
          </Col>
          <Col xs={24} md={24} lg={{ offset: 1, span: 14 }}>
            <Form.Item>
              <div className="variant-table-batch-actions">
                <Row>
                  <Col
                    xs={12}
                    md={12}
                    lg={12}
                    style={{ display: hasSelected ? 'initial' : 'none' }}>
                    <Search
                      name={"batchPrice"}
                      prefix="$"
                      type="number"
                      placeholder="0.00"
                      onSearch={value => this.updateSelectedVariants(value)}
                      enterButton="Update Sell Prices"
                    />
                  </Col>
                  <Col
                    xs={6}
                    md={6}
                    lg={6}
                    style={{ display: hasSelected ? 'initial' : 'none' }}>
                    <Button
                      onClick={this.removeSelectedVariants.bind(this)}
                      style={{ marginLeft: 16 }}>
                      Remove Variants
                    </Button>
                  </Col>
                  <Col xs={6} md={6} lg={6} style={{ float: 'right', display: 'initial' }}>
                    <div style={{ display: 'flex', alignItems: 'center', float: 'right', paddingTop: '5px' }}>
                      <div style={{ fontSize: '16px', paddingRight: '10px' }}>
                        Hold Orders
                      </div>
                      <Form.Item label="" valuepropname="checked" style={{paddingBottom: '0', height: '2px' }}>
                        {getFieldDecorator('orderHold', {
                          rules: [{
                            required: false,
                          }],
                          valuePropName: "checked",
                          initialValue: false
                        })(
                          <Switch
                            size='small'
                            style={{ minWidth: '40px' }}
                            onChange={onOrderHoldToggle}
                          />
                          ,
                        )}
                      </Form.Item>
                    </div>
                  </Col>
                </Row>
              </div>

              <Table
                rowSelection={rowSelection}
                columns={variantColumns}
                dataSource={data.validOptions}
                rowKey="id"
                scroll={{ y: 470 }}
                pagination={false}
                className="variant-table"
                style={{ border: 'none', position: "relative", top: "-4px" }}
              />

              <p style={{ margin: "20px 0" }}>
                <strong>Variant Count:</strong> {data.validOptions.length}
                <span style={{ marginTop: 8, textAlign: 'center' }}>
                  <Tag
                    color="red"
                    className="error-tag"
                    visible={data.validOptions.length > 100}
                    style={{ marginLeft: 16 }}>
                    Exceeded 100 variant limit per product, please remove{' '}
                    {Number(data.validOptions.length - 100)} variants
                  </Tag>
                </span>
              </p>
              <Form.Item valuepropname="checked">
                {getFieldDecorator('mockup', {
                  valuePropName: "checked",
                })(
                  <Card
                    title='Mockups to send to store'
                    style={{borderWidth: 2, borderRadius: 7, backgroundColor: '#e4e6f4'}} bodyStyle={{backgroundColor: '#fff'}}
                    hidden={data.selectedProduct.categoryDisplay === 'Monogram'}
                  >
                    <div>
                      <div>
                        <Tabs type={'line'} style={{marginTop: -18}} onTabClick={onTabClick}>
                          {
                            this.state.selectedProducts.map((product) => (
                              <TabPane tab={product.productName} key={String(product.productId)}>
                                {
                                  product.blankPsd.length > 0 ?
                                    <div>
                                      <Row style={{justifyContent: 'center'}}>
                                        {
                                          product.blankPsd.map((blankPsd) => (
                                            <Col xs={12} md={6} lg={4} style={{marginBottom: 10}}>
                                              <Checkbox
                                                value={{mockupId: blankPsd.blankMockupImage.id, productId: product.productId, blank_option_value_id: blankPsd.blankOptionValueId, blank_psd_id: blankPsd.id}}
                                                onChange={isSelectedMockup}>
                                                <img src={blankPsd.blankMockupImage.fileUrl} key={blankPsd.id} width={50}
                                                     height={50}/>
                                              </Checkbox>
                                            </Col>
                                          ))
                                        }
                                      </Row>
                                        <Row style={{float: 'right', justifyContent: 'flex-end'}}>
                                          <Col xs={12} md={12} lg={24}>
                                            <div style={{alignItems: 'flex-end'}}>
                                              {this.state.mockupsCounter}/10 Mockups selected
                                            </div>
                                          </Col>
                                        </Row>
                                    </div>
                                    :
                                    <Row>
                                      <Col xs={12} md={12} lg={24}>
                                        <div style={{color: '#1115', fontSize: '14px'}}>
                                          Default mockups will be pushed
                                        </div>
                                      </Col>
                                    </Row>
                                }
                              </TabPane>
                            ))
                          }
                        </Tabs>
                      </div>
                    </div>
                  </Card>
                )}
              </Form.Item>
            </Form.Item>
          </Col>
        </Row>
      </Form>
    );
  }
}

export default Form.create({ name: 'product_details' })(
  DesignerProductDetailsForm,
);
