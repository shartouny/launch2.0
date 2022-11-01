<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class Transaction
 *
 * @method getTransactionId;
 * @method getTitle;
 * @method getDescription;
 * @method getSellerUserId;
 * @method getBuyerUserId;
 * @method getCreationTsz;
 * @method getPaidTsz;
 * @method getShippedTsz;
 * @method getPrice;
 * @method getCurrencyCode;
 * @method getQuantity;
 * @method getTags;
 * @method getMaterials;
 * @method getImageListingId;
 * @method getReceiptId;
 * @method getShippingCost;
 * @method getIsDigital;
 * @method getFileData;
 * @method getListingId;
 * @method getIsQuickSale;
 * @method getSellerFeedbackId;
 * @method getBuyerFeedbackId;
 * @method getTransactionType;
 * @method getUrl;
 * @method getVariations;
 * @method getProductData;
 * @method getReceipt
 * @method getMainimage
 *
 * @method setTransactionId(int $value);
 * @method setTitle(string $value);
 * @method setDescription(string $value);
 * @method setSellerUserId(int $value);
 * @method setBuyerUserId(int $value);
 * @method setCreationTsz(float $value);
 * @method setPaidTsz(float $value);
 * @method setShippedTsz(float $value);
 * @method setPrice(float $value);
 * @method setCurrencyCode(string $value);
 * @method setQuantity(int $value);
 * @method setTags(array $value);
 * @method setMaterials(array $value);
 * @method setImageListingId(int $value);
 * @method setReceiptId(int $value);
 * @method setShippingCost(float $value);
 * @method setIsDigital(bool $value);
 * @method setFileData(string $value);
 * @method setListingId(int $value);
 * @method setIsQuickSale(bool $value);
 * @method setSellerFeedbackId(int $value);
 * @method setBuyerFeedbackId(int $value);
 * @method setTransactionType(string $value);
 * @method setUrl(string $value);
 * @method setVariations(array $value);
 * @method setProductData(ListingProduct $value);
 * @method setReceipt($value);
 * @method setMainimage($value);
 *
 * @package SunriseIntegration\Etsy\Models
 */
class Transaction extends AbstractEntity
{

    #region Properties

    #Fields
    protected $transactionId;
    protected $title;
    protected $description;
    protected $sellerUserId;
    protected $buyerUserId;
    protected $creationTsz;
    protected $paidTsz;
    protected $shippedTsz;
    protected $price;
    protected $currencyCode;
    protected $quantity;
    protected $tags;
    protected $materials;
    protected $imageListingId;
    protected $receiptId;
    protected $shippingCost;
    protected $isDigital;
    protected $fileData;
    protected $listingId;
    protected $isQuickSale;
    protected $sellerFeedbackId;
    protected $buyerFeedbackId;
    protected $transactionType;
    protected $url;
    protected $variations;
    protected $productData;

    public $receipt;
    public $mainimage;

    #endregion


}
