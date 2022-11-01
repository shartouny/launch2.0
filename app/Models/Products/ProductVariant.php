<?php

namespace App\Models\Products;

class ProductVariant extends \SunriseIntegration\TeelaunchModels\Models\Products\ProductVariant
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    public function getMockupFileName(){
        $blankVariantOptionValues = [];
        foreach ($this->blankVariant['optionValues'] as $blankVariantOptionValue) {
            $blankVariantOptionValues[] = str_replace(' ', '_', $blankVariantOptionValue['name']);
        }

        $fileNameArray[] = $this->product['name'];
        if($blankVariantOptionValues){
            $fileNameArray[] =  implode('_', $blankVariantOptionValues);
        }
        $fileNameArray[] = 'Mockup';
        $fileNameArray[] =  uniqid(date('YmdHis'));
        $fileName = implode('_', $fileNameArray);
    }
}
