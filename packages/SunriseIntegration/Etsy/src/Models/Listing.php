<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class Listing
 *
 * @method getListingId()
 * @method getState()
 * @method getUserId()
 * @method getCategoryId()
 * @method getTitle()
 * @method getDescription()
 * @method getCreationTsz()
 * @method getEndingTsz()
 * @method getOriginalCreationTsz()
 * @method getLastModifiedTsz()
 * @method getPrice()
 * @method getCurrencyCode()
 * @method getQuantity()
 * @method getSku()
 * @method getTags()
 * @method getTaxonomyId()
 * @method getSuggestedTaxonomyId()
 * @method getTaxonomyPath()
 * @method getMaterials()
 * @method getShopSectionId()
 * @method getFeaturedRank()
 * @method getStateTsz()
 * @method getUrl()
 * @method getViews()
 * @method getNumFavorers()
 * @method getShippingTemplateId()
 * @method getShippingProfileId()
 * @method getProcessingMin()
 * @method getProcessingMax()
 * @method getWhoMade()
 * @method getIsSupply()
 * @method getWhenMade()
 * @method getItemWeight()
 * @method getItemWeightUnit()
 * @method getItemLength()
 * @method getItemWidth()
 * @method getItemHeight()
 * @method getItemDimensionsUnit()
 * @method getIsPrivate()
 * @method getRecipient()
 * @method getOccasion()
 * @method getStyle()
 * @method getNonTaxable()
 * @method getIsCustomizable()
 * @method getIsDigital()
 * @method getFileData()
 * @method getCanWriteInventory()
 * @method getHasVariations()
 * @method getShouldAutoRenew()
 * @method getLanguage()
 * @method getImageIds()
 *
 * @method getUser();
 * @method getShop();
 * @method getSection();
 * @method getImages();
 * @method getMainimage();
 * @method getShippinginfo();
 * @method getShippingtemplate();
 * @method getShippingupgrades();
 * @method getPaymentinfo();
 * @method getTranslations();
 * @method getAttributes();
 * @method getInventory();
 * @method getVariations();
 * @method getVariationimage();
 *
 * @method setListingId($value)
 * @method setState($value)
 * @method setUserId($value)
 * @method setCategoryId($value)
 * @method setTitle($value)
 * @method setDescription($value)
 * @method setCreationTsz($value)
 * @method setEndingTsz($value)
 * @method setOriginalCreationTsz($value)
 * @method setLastModifiedTsz($value)
 * @method setPrice($value)
 * @method setCurrencyCode($value)
 * @method setQuantity($value)
 * @method setSku($value)
 * @method setTags(array $value)
 * @method setTaxonomyId($value)
 * @method setSuggestedTaxonomyId($value)
 * @method setTaxonomyPath($value)
 * @method setMaterials($value)
 * @method setShopSectionId($value)
 * @method setFeaturedRank($value)
 * @method setStateTsz($value)
 * @method setUrl($value)
 * @method setViews($value)
 * @method setNumFavorers($value)
 * @method setShippingTemplateId($value)
 * @method setShippingProfileId($value)
 * @method setProcessingMin($value)
 * @method setProcessingMax($value)
 * @method setWhoMade($value)
 * @method setIsSupply($value)
 * @method setWhenMade($value)
 * @method setItemWeight($value)
 * @method setItemWeightUnit($value)
 * @method setItemLength($value)
 * @method setItemWidth($value)
 * @method setItemHeight($value)
 * @method setItemDimensionsUnit($value)
 * @method setIsPrivate($value)
 * @method setRecipient($value)
 * @method setOccasion($value)
 * @method setStyle(array $value)
 * @method setNonTaxable($value)
 * @method setIsCustomizable($value)
 * @method setIsDigital($value)
 * @method setFileData($value)
 * @method setCanWriteInventory($value)
 * @method setHasVariations($value)
 * @method setShouldAutoRenew($value)
 * @method setLanguage($value)
 * @method setImageIds(array $value)
 *
 * @method setUser($value);
 * @method setShop($value);
 * @method setSection($value);
 * @method setImages($value);
 * @method setMainimage($value);
 * @method setShippinginfo($value);
 * @method setShippingtemplate($value);
 * @method setShippingupgrades($value);
 * @method setPaymentinfo($value);
 * @method setTranslations($value);
 * @method setAttributes($value);
 * @method setInventory($value);
 * @method setVariations($value);
 * @method setVariationimage($value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class Listing extends AbstractEntity
{

    #region Properties

    #Fields
    protected $listing_id;
    protected $state;
    protected $user_id;
    protected $category_id;
    protected $title;
    protected $description;
    protected $creation_tsz;
    protected $ending_tsz;
    protected $original_creation_tsz;
    protected $last_modified_tsz;
    protected $price;

    protected $currency_code;
    protected $quantity;

    protected $sku;
    protected $tags;
    protected $taxonomy_id;
    protected $suggested_taxonomy_id;
    protected $taxonomy_path;
    protected $materials;
    protected $shop_section_id;
    protected $featured_rank;
    protected $state_tsz;
    protected $url;
    protected $views;
    protected $num_favorers;
    protected $shipping_template_id;
    protected $shipping_profile_id;
    protected $processing_min;
    protected $processing_max;
    protected $who_made;
    protected $is_supply;
    protected $when_made;
    protected $item_weight;
    protected $item_weight_unit;
    protected $item_length;
    protected $item_width;
    protected $item_height;
    protected $item_dimensions_unit;
    protected $is_private;

    protected $recipient;
    protected $occasion;
    protected $style;
    protected $non_taxable;
    protected $is_customizable;
    protected $is_digital;
    protected $file_data;
    protected $can_write_inventory;
    protected $has_variations;
    protected $should_auto_renew;
    protected $language;
    protected $image_ids;

    #Associations
    protected $user;
    protected $shop;
    protected $section;
    protected $images;
    protected $mainimage;
    protected $shippinginfo;
    protected $shippingtemplate;
    protected $shippingupgrades;
    protected $paymentinfo;
    protected $translations;
    protected $attributes;
    protected $inventory;
    protected $variations;
    protected $variationimage;

    #endregion

}
