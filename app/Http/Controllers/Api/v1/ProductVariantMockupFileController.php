<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductMockupFile;
use App\Jobs\ProcessProductVariantMockupFile;
use App\Jobs\RetrieveMockupFile;
use App\Models\Products\Product;
use App\Models\Products\ProductMockupFile;
use App\Models\Products\ProductVariantMockupFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductVariantMockupFileController extends Controller
{
    public function process(Request $request, $id)
    {
        $mockupFile = ProductVariantMockupFile::findOrFail($id);
        $mockupFile->resetJob();

        if(config('app.env') === 'local'){
            ProcessProductVariantMockupFile::dispatch($mockupFile);
        }else {
            ProcessProductVariantMockupFile::dispatch($mockupFile)->onQueue('mockup-files');
        }
        return response($mockupFile->refresh());
    }

    public function processAllUnfinished(Request $request)
    {

        $productId = $request->productId;

        $mockupFiles = ProductMockupFile::where([['product_id',$productId],['status', '!=', ProductMockupFile::STATUS_FINISHED]])->get();

        if(config('app.env') == 'local'){
            $mockupFiles = ProductMockupFile::where([['product_id',$productId]])->get();
        }

        foreach ($mockupFiles as $mockupFile) {
            $mockupFile->resetJob();

            $productVariantMockupFiles = ProductVariantMockupFile::where([['product_mockup_file_id',$mockupFile->id]])->get();
            foreach ($productVariantMockupFiles as $productVariantMockupFile){
                $productVariantMockupFile->resetJob();
            }

            Log::debug('Dispatching ProcessProductMockupFile ID ' . $mockupFile->id);
            if (config('app.env') === 'local') {
                ProcessProductMockupFile::dispatch($mockupFile);
            } else {
                ProcessProductMockupFile::dispatch($mockupFile)->onQueue('mockup-files');
            }
        }

        return response($mockupFiles);
    }

    public function retrieve(Request $request, $id)
    {
        $mockupFile = \App\Models\Products\ProductVariantMockupFile::findOrFail($id);
        $mockupFile->deleteFile();
        $mockupFile->status = ProductVariantMockupFile::STATUS_REQUESTED;
        $mockupFile->finished_at = null;
        $mockupFile->retry_count = 0;
        $mockupFile->save();

        if(config('app.env') === 'local'){
            RetrieveMockupFile::dispatch($mockupFile);
        }else {
            RetrieveMockupFile::dispatch($mockupFile)->onQueue('mockup-files');
        }
        return response($mockupFile->refresh());
    }
}
