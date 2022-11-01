<?php

namespace App\Formatters\OrderDesk;

use App\Models\Blanks\BlankPrintImage;
use App\Models\Products\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SunriseIntegration\OrderDesk\Models\Order;
use SunriseIntegration\OrderDesk\Models\OrderItem;

class OrderItemFormatter
{
  static function formatForPlatform($lineItem, $lineItemCost)
  {
    $productVariant = $lineItem->productVariant;

    //Get product if deleted
    if(!$productVariant->product){
      $product = Product::where('id', $productVariant->product_id)->withTrashed()->first();
      $productVariant->product = $product;
    }

    $printFileReplacements = $lineItem->printFiles;
    $stageFiles = $productVariant->stageFiles;
    $printFiles = $productVariant->printFiles;
    $mockupFiles = $productVariant->mockupFiles;
    $blankVariant = $productVariant->blankVariant;
    $blank = $blankVariant->blank;
    $category = $blank->category;
    $isApparel = strtolower($category->name) == 'apparel';


    $orderDeskOrderItem = new OrderItem();

    $orderDeskOrderItem->setName(self::sanitize($productVariant->product->name));
    $orderDeskOrderItem->setPrice(round($lineItemCost / $blankVariant->quantity, 2));
    $orderDeskOrderItem->setQuantity($blankVariant->quantity * $lineItem->quantity);
    $orderDeskOrderItem->setCode($blankVariant->sku);

    $variationListKeys = [
      //Always Included
      'SKU',
      'Type',
      //Grab from Blank Variant Options
      'Size',
      'Color',
      'Pack',
      'Scent'
    ];

    $metadataKeys = [
      //Still needed?
      'shopify_order_id',
      'shopify_line_item_id',
      'shopify_vendor',
      'shopify_item_id',

      //Pulled from Variant Print Files
      'print_url_1',
      'print_url_1_original',
      'print_preview_1',
      'print_url_2',
      'print_url_2_original',
      'print_url_3',
      'print_url_3_original',
      'print_url_4',
      'print_url_4_original',
      'print_url_5',
      'print_url_5_original',
      'print_url_6',
      'print_url_6_original',

      //Input in Admin
      'inventory_location',
      'print_sku',
      'print_finish',
      'color',
      'cimpress_url_1',
      'photousa_print_url_1',
      'print_type_1',
      'print_mode',
      'shoe_fit',
      'shoe_size'
    ];

    $variationList = [
      'SKU' => $blankVariant->sku,
      'Type' => $category->name ?? 'N/A'
    ];
    foreach ($blankVariant->optionValues as $optionValue) {
      $variationList[$optionValue->option->name] = $optionValue->name;
    }
    $orderDeskOrderItem->setVariationList($variationList);


    //Set metadata
    $metadata = [
      'line_item_id' => $lineItem->id,
      'print_sku' => $blankVariant->sku,
    ];

    //Monogram Products Type
    if(!empty($lineItem->properties)){
        $properties = json_decode($lineItem->properties, true);
        $metadata['is_monogram'] = 'true';
        $metadata = array_merge($metadata, $properties);
    }

    Log::info('-----------------------------------------------------------------------------------------------');
    Log::info('Product Stage Files: '.json_encode($productVariant->product->stageFiles));
    Log::info('Product Print Files: '.json_encode($productVariant->product->printFiles));

    Log::info('Stage Files: '.json_encode($stageFiles));
    Log::info('Print Files: '.json_encode($printFiles));

    //Add print files
    $wasPrintFileReplaced = 'false';
    foreach ($printFiles as $printFile) {
      $printFileReplacement = Arr::first($printFileReplacements, function ($replacement, $key) use ($printFile) {
        return $replacement->product_print_file_id == $printFile->product_print_file_id;
      });

      if ($printFileReplacement) {
        $wasPrintFileReplaced = 'true';
        $fileUrl = $printFileReplacement->file_url; //Storage::url($printFileReplacement->file_path);
      } else {
        $fileUrl = $printFile->productPrintFile->file_url; //Storage::url($printFile->file_path);
      }

      $metadata[$printFile->productPrintFile->blankPrintImage->vendor_print_code] = $fileUrl;
      $metadata["{$printFile->productPrintFile->blankPrintImage->vendor_print_code}_original"] = $fileUrl;
      $metadata["{$printFile->productPrintFile->blankPrintImage->vendor_print_code}_replaced"] = $wasPrintFileReplaced;


      //productVariant.stageFiles.blankStageLocation.shortName
      //$metadata["{$printFile->productPrintFile->blankPrintImage->vendor_print_code}_side"] = $printFile->productPrintFile->blankPrintImage->name ? $printFile->productPrintFile->blankPrintImage->name : '';
      if($isApparel) {
        $metadata["{$printFile->productPrintFile->blankPrintImage->vendor_print_code}_location"] = $printFile->stageFile->productArtFile->blankStageLocationSub ? $printFile->stageFile->productArtFile->blankStageLocationSub->name : '';
        $metadata["{$printFile->productPrintFile->blankPrintImage->vendor_print_code}_offset"] = $printFile->stageFile->productArtFile->blankStageLocationSubOffset ? $printFile->stageFile->productArtFile->blankStageLocationSubOffset->name : 'Normal';
      }
    }

    $metadata['was_print_file_replaced'] = $wasPrintFileReplaced;

    //Add mockup files
    foreach ($mockupFiles as $key => $mockupFile) {
      $fileUrl = $mockupFile->productMockupFile->file_url; //Storage::url($mockupFile->file_path);
      $metadata["print_preview_" . ($key + 1)] = $fileUrl;
    }

    $orderDeskOrderItem->setMetadata($metadata);

    return $orderDeskOrderItem;
  }

  /**
   * @param $string
   * @return mixed
   */
  static function sanitize($string)
  {
    $string = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW);
    $string = str_replace('\n', '', $string);
    $string = str_replace('\r', '', $string);
    $string = str_replace('"', '', $string);
    $string = str_replace("'", '', $string);
    $string = str_replace("\\", '', $string);
    $string = trim($string);

    return $string;
  }
}
