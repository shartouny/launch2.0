<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class Receipt
 *
 * @method getReceiptId
 * @method getReceiptType
 * @method getOrderId
 * @method getSellerUserId
 * @method getBuyerUserId
 * @method getCreationTsz
 * @method getCanRefund
 * @method getLastModifiedTsz
 * @method getName
 * @method getFirstLine
 * @method getSecondLine
 * @method getCity
 * @method getState
 * @method getZip
 * @method getFormattedAddress
 * @method getCountryId
 * @method getPaymentMethod
 * @method getPaymentEmail
 * @method getMessageFromSeller
 * @method getMessageFromBuyer
 * @method getWasPaid
 * @method getTotalTaxCost
 * @method getTotalVatCost
 * @method getTotalPrice
 * @method getTotalShippingCost
 * @method getCurrencyCode
 * @method getMessageFromPayment
 * @method getWasShipped
 * @method getBuyerEmail
 * @method getSellerEmail
 * @method getIsGift
 * @method getNeedsGiftWrap
 * @method getGiftMessage
 * @method getGiftWrapPrice
 * @method getDiscountAmt
 * @method getSubtotal
 * @method getGrandtotal
 * @method getAdjustedGrandtotal
 * @method getBuyerAdjustedGrandtotal
 * @method getShipments
 * @method getTransactions
 *
 * @method setReceiptId(int $value)
 * @method setReceiptType(int $value)
 * @method setOrderId(int $value)
 * @method setSellerUserId(int $value)
 * @method setBuyerUserId(int $value)
 * @method setCreationTsz(float $value)
 * @method setCanRefund(bool $value)
 * @method setLastModifiedTsz(float $value)
 * @method setName(string $value)
 * @method setFirstLine(string $value)
 * @method setSecondLine(string $value)
 * @method setCity(string $value)
 * @method setState(string $value)
 * @method setZip(string $value)
 * @method setFormattedAddress(string $value)
 * @method setCountryId(int $value)
 * @method setPaymentMethod(string $value)
 * @method setPaymentEmail(string $value)
 * @method setMessageFromSeller(string $value)
 * @method setMessageFromBuyer(string $value)
 * @method setWasPaid(bool $value)
 * @method setTotalTaxCost(float $value)
 * @method setTotalVatCost(float $value)
 * @method setTotalPrice(float $value)
 * @method setTotalShippingCost(float $value)
 * @method setCurrencyCode(string $value)
 * @method setMessageFromPayment(string $value)
 * @method setWasShipped(bool $value)
 * @method setBuyerEmail(string $value)
 * @method setSellerEmail(string $value)
 * @method setIsGift(bool $value)
 * @method setNeedsGiftWrap(bool $value)
 * @method setGiftMessage(string $value)
 * @method setGiftWrapPrice(float $value)
 * @method setDiscountAmt(float $value)
 * @method setSubtotal(float $value)
 * @method setGrandtotal(float $value)
 * @method setAdjustedGrandtotal(float $value)
 * @method setBuyerAdjustedGrandtotal(float $value)
 * @method setShipments(array $value)
 * @method setTransactions($value)
 *
 * @package SunriseIntegration\Etsy\Models
 */
class Receipt extends AbstractEntity
{

    #region Properties

    #Fields
    protected $receiptId;
    protected $receiptType;
    protected $orderId;
    protected $sellerUserId;
    protected $buyerUserId;
    protected $creationTsz;
    protected $canRefund;
    protected $lastModifiedTsz;
    protected $name;
    protected $firstLine;
    protected $secondLine;
    protected $city;
    protected $state;
    protected $zip;
    protected $formattedAddress;
    protected $countryId;
    protected $paymentMethod;
    protected $paymentEmail;
    protected $messageFromSeller;
    protected $messageFromBuyer;
    protected $wasPaid;
    protected $totalTaxCost;
    protected $totalVatCost;
    protected $totalPrice;
    protected $totalShippingCost;
    protected $currencyCode;
    protected $messageFromPayment;
    protected $wasShipped;
    protected $buyerEmail;
    protected $sellerEmail;
    protected $isGift;
    protected $needsGiftWrap;
    protected $giftMessage;
    protected $giftWrapPrice;
    protected $discountAmt;
    protected $subtotal;
    protected $grandtotal;
    protected $adjustedGrandtotal;
    protected $buyerAdjustedGrandtotal;
    protected $shipments;

    protected $transactions;

    #endregion

}
