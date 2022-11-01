<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blanks\BlankCategoryCollectionResource;
use App\Http\Resources\Blanks\BlankCategoryProductCollectionResource;
use App\Http\Resources\Blanks\BlankCategoryProductResource;
use App\Http\Resources\Blanks\BlankCategoryResource;
use App\Models\Accounts\AccountBlankAccess;
use App\Models\Blanks\Blank;
use App\Models\Blanks\BlankCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * @group  Blanks
 *
 * APIs for managing blanks
 */

class BlankCategoryController extends Controller
{

    /**
     * Get Blank Categories
     *
     * Get account blank categories
     */
    public function index(Request $request)
    {
        $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();

        $availableBlankCategories = BlankCategory::available($storeBlanksAccessIds);
        if (!$availableBlankCategories) {
            return $this->responseServerError();
        }

        return new BlankCategoryCollectionResource(collect($availableBlankCategories));
    }

    /**
     * Get Blank Category By Id
     *
     * Get account blank category by id
     *
     * @urlParam category required category id
     */
    public function show(Request $request, $id)
    {
        $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();

        $blankCategory = BlankCategory::available($storeBlanksAccessIds, $id);
        if($blankCategory->count() === 0){
            return $this->responseNotFound();
        }

        return new BlankCategoryResource($blankCategory);
    }

    /**
     * Get Blanks By Category Id
     *
     * Get account blanks by category id
     *
     * @urlParam id required category id
     */
    public function blanks(Request $request, $id)
    {
        $blankCategory = BlankCategory::select('name')->find($id);
        if(!$blankCategory){
            return $this->responseNotFound();
        }

        $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();

        if($blankCategory->name == BlankCategory::NEW_CATEGORY_NAME){
            $blanks = Blank::newAvailableBlanks($storeBlanksAccessIds)->orderBy('id')->paginate();
            return BlankCategoryProductResource::collection($blanks);
        }

        $categoryIds = BlankCategory::availableCategoryIds($storeBlanksAccessIds, $id);
        if(!$categoryIds){
            return $this->responseNotFound();
        }
        $blanks = Blank::whereIn('blank_category_id', $categoryIds)->orderBy('name')->available($storeBlanksAccessIds)->get();

        return BlankCategoryProductResource::collection($blanks);
    }

}
