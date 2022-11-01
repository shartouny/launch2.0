<?php

namespace SunriseIntegration\Shopify\Models\Order;

use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Transaction
 *
 *
 * @method getAmount()
 * @method getAuthorization()
 * @method getCreatedAt()
 * @method getDeviceId()
 * @method getGateway()
 * @method getSourceName()
 * @method getPaymentDetails()
 * @method getId()
 * @method getOrderId()
 * @method getReceipt()
 * @method getErrorCode()
 * @method getStatus()
 * @method getTest()
 * @method getUserId()
 * @method getCurrency()
 * @method getKind()
 *
 * @method setAmount($amount)
 * @method setAuthorization($authorization)
 * @method setCreatedAt($date)
 * @method setDeviceId($id)
 * @method setGateway($gateway)
 * @method setSourceName($source)
 * @method setPaymentDetails($details)
 * @method setId($id)
 * @method setOrderId($id)
 * @method setReceipt($receipt)
 * @method setErrorCode($code)
 * @method setStatus($status)
 * @method setTest($test)
 * @method setUserId($id)
 * @method setCurrency($currency)
 * @method setKind($type)
 *
 *
 * @package SunriseIntegration\Shopify\Models\Order
 */
class Transaction extends AbstractEntity {

	#region Constants

	const SOURCE_WEB = 'web';
	const SOURCE_POS = 'pos';
	const SOURCE_IPHONE = 'iphone';
	const SOURCE_ANDROID = 'android';
	const SOURCE_EXTERNAL = 'external';

	const KIND_AUTHORIZATION = 'apiAuthorization';
	const KIND_CAPTURE = 'capture';
	const KIND_SALE = 'sale';
	const KIND_VOID = 'void';
	const KIND_REFUND = 'refund';

	const STATUS_PENDING = 'pending';
	const STATUS_FAILURE = 'failure';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR = 'error';

	#endregion


	#region Properties

	protected $amount = 0;
	protected $authorization;
	protected $created_at;
	protected $device_id;
	protected $gateway = 'bogus';
	protected $source_name = 'web';
	protected $payment_details = [];
	protected $id;
	protected $order_id;
	protected $receipt;
	protected $error_code;
	protected $status;
	protected $test;
	protected $user_id;
	protected $currency = 'USD';
	protected $kind;

	#endregion

}
