<?php

namespace App\Models\Orders;

use App\Models\Accounts\AccountSettings;
use App\Scopes\Account;
use Illuminate\Database\Eloquent\Builder;

class Order extends \SunriseIntegration\TeelaunchModels\Models\Orders\Order {
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new Account());

        static::addGlobalScope('logs', function (Builder $builder) {
            $builder->with(
                'logs'
            );
        });

        static::addGlobalScope('lineItems', function (Builder $builder) {
            $builder->with(
                'lineItems'
            );
        });

        static::addGlobalScope('storePlatform', function (Builder $builder) {
            $builder->with(
                'store.platform'
            );
        });

        static::addGlobalScope('shipping', function (Builder $builder) {
            $builder->with(
                'shippingAddress',
                'billingAddress',
                'shipments'
            );
        });

        static::addGlobalScope('payments', function (Builder $builder) {
            $builder->with(
                'payments.lineItems',
                'payments.accountPayment'
            );
        });

        static::addGlobalScope('blankVariant', function (Builder $builder) {
            $builder->with(
                'lineItems.platformStoreProductVariant.productVariant.blankVariant',
                'lineItems.platformStoreProductVariant.productVariant.blankVariant.blank',
                'lineItems.platformStoreProductVariant.productVariant.printFiles',
                'lineItems.productVariant.blankVariant',
                'lineItems.productVariant.blankVariant.blank'
            );
        });

        static::addGlobalScope('art', function (Builder $builder) {
            $builder->with(
                'lineItems.productVariant.product.artFiles.imageType',
                'lineItems.productVariant.product.artFiles.blankStageLocation',

                'lineItems.productVariant.stageFiles.imageType',
                'lineItems.productVariant.stageFiles.blankStageLocation',
                'lineItems.productVariant.stageFiles.productArtFile',
                'lineItems.productVariant.stageFiles.blankStage.create_types.image_requirement',
                'lineItems.productVariant.stageFiles.blankStageCreateType',

                'lineItems.productVariant.printFiles.productPrintFile.imageType',
                'lineItems.productVariant.printFiles.productPrintFile.productArtFile.imageType',

                'lineItems.productVariant.blankVariant.optionValues.option',

                'lineItems.platformStoreProductVariant.productVariant.product.artFiles.imageType',
                'lineItems.platformStoreProductVariant.productVariant.product.artFiles.blankStageLocation',

                'lineItems.platformStoreProductVariant.productVariant.stageFiles.imageType',
                'lineItems.platformStoreProductVariant.productVariant.stageFiles.blankStageLocation',
                'lineItems.platformStoreProductVariant.productVariant.stageFiles.productArtFile',

                'lineItems.platformStoreProductVariant.productVariant.printFiles.imageType',
                'lineItems.platformStoreProductVariant.productVariant.blankVariant.optionValues.option',

                'lineItems.printFiles',
                'lineItems.artFiles'
            );
        });
    }
}
