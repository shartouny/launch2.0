<?php


namespace App\Helpers;


use App\Models\Accounts\AccountBlankAccess;
use App\Models\Blanks\Blank;

class ProductHelper
{
    public static function getSurcharge ($product, $blankCategory) {
        $artfiles = count($product->artFiles);
        if (strtolower($blankCategory) == 'apparel' && $artfiles > 1) {
            foreach ($product->variants as $variant) {
                $variant->blankVariant->price =  number_format($variant->blankVariant->price + 5, 2) ;
            }
        }
        return $product;
    }
}
