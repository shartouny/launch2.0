<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductVariantPrintFile;
use App\Models\Products\ProductVariantPrintFile;
use Illuminate\Http\Request;

class ProductVariantPrintFileController extends Controller
{
    public function process(Request $request, $id)
    {
        $printFile = ProductVariantPrintFile::findOrFail($id);
        $printFile->deleteFile();
        $printFile->status = 1;
        $printFile->save();

        if(config('app.env') === 'local'){
            ProcessProductVariantPrintFile::dispatch($printFile);
        }else {
            ProcessProductVariantPrintFile::dispatch($printFile)->onQueue('print-files');
        }
        return response($printFile->refresh());
    }
}
