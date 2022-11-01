<?php

namespace App\Traits;

use App\Jobs\ProcessProductPrintFile;
use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductVariantPrintFile;
use Exception;
use Illuminate\Support\Facades\Log;

trait PrintFileJobsCreation
{
    public function createPrintFileJobs($printFiles, $productVariantStageFile)
    {
        Log::debug('Create Print Files');
        try {
            foreach ($printFiles as $printFile) {
                $productPrintFile = ProductPrintFile::where([
                    'account_id' => $productVariantStageFile->account_id,
                    'product_id' => $productVariantStageFile->product_id,
                    'blank_print_image_id' => $printFile->id
                ])->first();

                if (!$productPrintFile) {
                    Log::debug('No existing ProductPrintFile found, creating one');
                    $productPrintFile = ProductPrintFile::create([
                        'account_id' => $productVariantStageFile->account_id,
                        'product_id' => $productVariantStageFile->product_id,
                        'blank_id' => $productVariantStageFile->blank_id,
                        'blank_print_image_id' => $printFile->id,
                        'blank_stage_id' => $productVariantStageFile->blank_stage_id,
                        'blank_stage_location_id' => $productVariantStageFile->blank_stage_location_id,
                        'width' => $productVariantStageFile->width,
                        'height' => $productVariantStageFile->height,
                        'product_art_file_id' => $productVariantStageFile->productArtFile->id
                    ]);
                    Log::debug('Dispatching ProductPrintFile ID ' . $productPrintFile->id);
                    if (config('app.env') === 'local') {
                        ProcessProductPrintFile::dispatch($productPrintFile);
                    } else {
                        ProcessProductPrintFile::dispatch($productPrintFile)->onQueue('print-files');
                    }
                }
                else {
                    Log::debug('Existing ProductPrintFile found | ID: ' . $productPrintFile->id);
                }

                $existingPrintFile = ProductVariantPrintFile::where([
                    'account_id' => $productVariantStageFile->account_id,
                    'product_id' => $productVariantStageFile->product_id,
                    'product_variant_id' => $productVariantStageFile->product_variant_id,
                    'blank_print_image_id' => $printFile->id
                ])->first();
                if ($existingPrintFile) {
                    Log::debug('Print file already exists');
                    continue;
                }

                $productVariantPrintFile = ProductVariantPrintFile::create([
                    'account_id' => $productVariantStageFile->account_id,
                    'product_id' => $productVariantStageFile->product_id,
                    'product_variant_id' => $productVariantStageFile->product_variant_id,
                    'blank_print_image_id' => $printFile->id,
                    'blank_stage_id' => $productVariantStageFile->blank_stage_id,
                    'width' => $productVariantStageFile->width,
                    'height' => $productVariantStageFile->height,
                    'product_variant_stage_file_id' => $productVariantStageFile->id,
                    'product_print_file_id' => $productPrintFile->id
                ]);
                Log::debug('Created ProductVariantPrintFile ID ' . $productVariantPrintFile->id);
            }
        } catch (Exception $e) {
            Log::debug($e);
        }
    }
}
