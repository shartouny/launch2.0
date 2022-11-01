<?php

namespace SunriseIntegration\Shopify\Models;

use SunriseIntegration\Shopify\Models\AbstractEntity;


/**
 * Class FulfillmentService
 *
 * @method getId()
 * @method getName()
 * @method getEmail()
 * @method getServiceName()
 * @method getHandle()
 * @method getFulfillmentOrdersOptIn()
 * @method getIncludePendingStock()
 * @method getProviderId()
 * @method getLocationId()
 * @method getCallbackUrl()
 * @method getTrackingSupport()
 * @method getInventoryManagement()
 *
 * @method setId($id)
 * @method setName($name)
 * @method setEmail($email)
 * @method setServiceName($service_name)
 * @method setHandle($handle)
 * @method setFulfillmentOrdersOptIn($fulfillment_orders_opt_in)
 * @method setIncludePendingStock($include_pending_stock)
 * @method setProviderId($provider_id)
 * @method setLocationId($location_id)
 * @method setCallbackUrl($callback_url)
 * @method setTrackingSupport($tracking_support)
 * @method setInventoryManagement($inventory_management)
 *
 * @package SunriseIntegration\Shopify\Models\FulfillmentService
 * */

class FulfillmentService extends AbstractEntity {

    #region Properties

    protected $id;
    protected $name;
    protected $email;
    protected $service_name;
    protected $handle;
    protected $fulfillment_orders_opt_in;
    protected $include_pending_stock;
    protected $provider_id;
    protected $location_id;
    protected $callback_url;
    protected $tracking_support;
    protected $inventory_management;

    #endregion
}
