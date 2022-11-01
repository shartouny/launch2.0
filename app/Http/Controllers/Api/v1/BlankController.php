<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Blanks\BlankCollectionResource;
use App\Http\Resources\Blanks\BlankResource;
use App\Models\Accounts\AccountBlankAccess;
use App\Models\Blanks\Blank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group  Blanks
 *
 * APIs for managing stores
 */

class BlankController extends Controller
{

    /**
     * Get Blanks
     *
     * Get account blanks with vendor, category, image, variants and options
     *
     * @queryParam  ids comma separated ids
     */
    public function index(Request $request)
    {
        $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();
        $query = Blank::available($storeBlanksAccessIds)
            ->with('vendor', 'category', 'image', 'options')
            ->stageSettings()
            ->with(['variants' => function ($query) use ($storeBlanksAccessIds) {
                $query->available()->with('optionValues','image', 'image2');
            }])
            ->with(['blank_psd' => function ($query) {
                $query->orderBy('sort')->with('blank_mockup_image', 'blank_psd_layer');
            }]);

        if ($request->ids) {
            $query->whereIn('id', explode(',', $request->ids));
        }

        $blanks = $query->get();

        $blanks = $this->filterOptionValues($blanks);

        return new BlankCollectionResource($blanks);
    }

    /**
     * Get Blank By Id
     *
     * Get account blank by id with vendor, category, image, variants and options
     *
     * @urlParam  blank required blank id
     */
    public function show(Request $request, $id)
    {
        $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();

        //TODO: Read BlankStages available bool in stageSettings()
        $blank = Blank::available($storeBlanksAccessIds)
            ->with('vendor', 'category', 'image', 'options')
            ->stageSettings()
            ->with(['variants' => function ($query) use ($storeBlanksAccessIds) {
                $query->available()->with('optionValues','image', 'image2');
            }])
            ->with(['blank_psd' => function ($query) {
                $query->orderBy('sort')->with('blank_mockup_image', 'blank_psd_layer');
            }])
            ->find($id);
        if (!$blank) {
            return $this->responseNotFound();
        }

        $blank = $this->filterOptionValues([$blank])[0];

        return new BlankResource($blank);
    }

    public function filterOptionValues($blanks)
    {
        foreach ($blanks as $blankIndex => $blank) {
            //Grab all option values off variants
            $variantOptionValues = [];
            foreach ($blank->variants as $variant) {
                foreach ($variant->optionValues as $variantOptionValue) {
                    $variantOptionValues[$variantOptionValue->id] = $variantOptionValue;
                }
            }

            $blanks[$blankIndex] = $this->assignBlankOptionValues($blank, array_values($variantOptionValues));
        }
        return $blanks;
    }

    public function assignBlankOptionValues($blank, $variantOptionValues)
    {
        //Assign variant option values to option->values to ensure only available options are presented

        foreach ($blank->options as $blankOptionKey => $blankOption) {
            $availableOptionValues = [];
            foreach ($variantOptionValues as $variantOptionValue) {
                if ($blankOption->id === $variantOptionValue->blank_blank_option->blank_option_id) {
                    $availableOptionValues[$variantOptionValue->id] = $variantOptionValue;
                }
            }
            $availableOptionValues = array_values($availableOptionValues);
            $blank->options[$blankOptionKey]->values = $availableOptionValues;
        }

        return $blank;
    }
}
