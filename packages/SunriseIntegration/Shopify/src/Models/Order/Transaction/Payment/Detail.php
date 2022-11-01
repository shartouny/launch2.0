<?php

namespace SunriseIntegration\Shopify\Models\Order\Transaction\Payment;

use SunriseIntegration\Shopify\Models\AbstractEntity;

/**
 * Class Detail
 *
 * @method getAvsResultCode()
 * @method getCreditCardBin()
 * @method getCvvResultCode()
 * @method getCreditCardNumber()
 * @method getCreditCardCompany()
 *
 * @method setAvsResultCode($code)
 * @method setCreditCardBin($bin)
 * @method setCvvResultCode($code)
 * @method setCreditCardNumber($number)
 * @method setCreditCardCompany($company)
 *
 * @package SunriseIntegration\Shopify\Models\Order\Transaction\Payment
 */
class Detail extends AbstractEntity {

	#region Properties

	protected $avs_result_code;
	protected $credit_card_bin;
	protected $cvv_result_code;
	protected $credit_card_number;
	protected $credit_card_company;

	#endregion
}
