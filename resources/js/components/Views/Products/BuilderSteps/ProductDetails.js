import React, { Component } from 'react';
import { Col, Row } from 'antd';
import ProductDetailsForm from '../../../Components/Forms/DesignerProductDetailsForm';
import Title from "antd/es/typography/Title";
import variantCustomize from '../../../../utils/variantCustomize';

/**
 *
 */
export default class ProductDetails extends Component {
  /**
   *
   * @param {{}} props
   */
  constructor(props) {
    super(props);
  };
  /**
   *
   * @returns {*}
   */
  render() {
    const {
      blank = {},
      data,
      description,
      onOrderHoldToggle,
      onInputChange,
      onTagChange,
      onTagClose,
      onInputChangePrice,
      onEditorStateChange,
      onVariantRemove,
      onVariantBatchRemove,
      tags,
      setVariantPrice,
      onUpdateSelectedVariantsPrice,
      setSelectedMockup
    } = this.props;

      variantCustomize.getSellingPrice(data)

    /**
     * TODO we just display the first template image?
     */
    const { products } = data;

    return (
      <div>
        <Row>
          <Col span={24}><Title>Details</Title></Col>
          <Col span={24}>
            <ProductDetailsForm
              data={data}
              tags={tags}
              onOrderHoldToggle={onOrderHoldToggle}
              onInputChange={onInputChange}
              onInputChangePrice={onInputChangePrice}
              onEditorStateChange={onEditorStateChange}
              onVariantRemove={onVariantRemove}
              onVariantBatchRemove={onVariantBatchRemove}
              description={description}
              onTagChange={onTagChange}
              onTagClose={onTagClose}
              setVariantPrice={setVariantPrice}
              onUpdateSelectedVariantsPrice={onUpdateSelectedVariantsPrice}
              setSelectedMockup={setSelectedMockup}
            />
          </Col>
        </Row>
      </div>
    );
  }
}
