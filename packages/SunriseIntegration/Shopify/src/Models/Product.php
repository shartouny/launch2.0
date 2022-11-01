<?php

namespace SunriseIntegration\Shopify\Models;

use SunriseIntegration\Shopify\Models\Product\Image;
use SunriseIntegration\Shopify\Models\Product\Option;
use SunriseIntegration\Shopify\Models\Product\Variant;


/**
 * Class Product
 *
 *
 * @method getBodyHtml()
 * @method getCreatedAt()
 * @method getHandle()
 * @method getId()
 * @method getOptions()
 * @method getImages()
 * @method \SunriseIntegration\Shopify\Models\Product\Image getImage()
 * @method getProductType()
 * @method getPublishedAt()
 * @method getPublished()
 * @method getPublishedScope()
 * @method getTags()
 * @method getTemplateSuffix()
 * @method getTitle()
 * @method getMetafieldsGlobalTitleTag()
 * @method getMetafieldsGlobalDescriptionTag()
 * @method getUpdatedAt()
 * @method \SunriseIntegration\Shopify\Models\Product\Variant[] getVariants()
 * @method getVendor()
 *
 * @method setBodyHtml($html)
 * @method setCreatedAt($date)
 * @method setHandle($handle)
 * @method setId($id)
 * @method setProductType($type)
 * @method setPublishedAt($date)
 * @method setPublished($date)
 * @method setPublishedScope($scope)
 * @method setTags($tags)
 * @method setTemplateSuffix($templateSuffix)
 * @method setTitle($title)
 * @method setMetafieldsGlobalTitleTag($tag)
 * @method setMetafieldsGlobalDescriptionTag($descriptionTag)
 * @method setUpdatedAt($date)
 * @method setVendor($vendor)
 *
 * @package SunriseIntegration\Shopify\Models
 */
class Product extends AbstractEntity
{

    #region Properties

    protected $body_html;
    protected $created_at;
    protected $handle;
    protected $id;
    protected $options;

    /**
     * "images": ["src": "http://example.com/burton.jpg"]
     *
     * @var array
     */
    protected $images = [];
    protected $image;
    protected $product_type;
    protected $published_at;
    protected $published;

    /**
     * @var string web or global
     */
    protected $published_scope;
    protected $tags;
    protected $template_suffix;
    protected $title;
    protected $metafields_global_title_tag;
    protected $metafields_global_description_tag;
    protected $updated_at;
    protected $variants = [];
    protected $vendor;

    #endregion


    public function load($data)
    {

        if (\is_string($data)) {
            $data = json_decode($data);
            $data = !empty($data->product) ? $data->product : $data;
        } else {
            $data = !empty($data->product) ? $data->product : $data;
        }


        parent::load($data);
    }

    public function addVariant($variant)
    {
        $this->variants[] = $variant;
    }

    public function addImage($image)
    {
        $this->image = $image;
    }

    public function addImages($images)
    {

        if (\is_array($images)) {
            $this->images = $images;
        } else {
            $this->images[] = $images;
        }
    }

    public function addOptions($options)
    {

        if (\is_array($options)) {
            $this->options = $options;
        } else {
            $this->options[] = $options;
        }
    }

    protected function setVariants($variants)
    {

        if (\is_array($variants)) {

            foreach ($variants as $variant) {

                if ($variant instanceof Variant) {
                    $this->addVariant($variant);
                } else {

                    $variantToCreate = new Variant($this->getApiAuthorization());
                    $variantToCreate->load($variant);
                    $this->addVariant($variantToCreate);
                }

            }
        } else {
            $this->variants = $variants;
        }
    }

    protected function setImages($images)
    {

        if (\is_array($images)) {

            foreach ($images as $image) {

                if ($image instanceof Image) {
                    $this->addImages($image);
                } else {

                    $imageToCreate = new Image($this->getApiAuthorization());
                    $imageToCreate->load($image);
                    $this->addImages($imageToCreate);
                }

            }
        } else {
            $this->images = $images;
        }
    }

    protected function setImage($image)
    {

        if ($image instanceof Image) {
            $this->addImage($image);
        } else {
            $imageToCreate = new Image($this->getApiAuthorization());
            $imageToCreate->load($image);
            $this->addImage($imageToCreate);
        }
    }

    protected function setOptions($options)
    {

        if (\is_array($options)) {

            foreach ($options as $option) {

                if ($option instanceof Option) {
                    $this->addOptions($option);
                } else {

                    $optionToCreate = new Image($this->getApiAuthorization());
                    $optionToCreate->load($option);
                    $this->addOptions($optionToCreate);
                }

            }
        } else {
            $this->options = $options;
        }
    }


}
