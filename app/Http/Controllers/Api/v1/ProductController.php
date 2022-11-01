<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ProductHelper;
use App\Http\Controllers\Controller;

use App\Http\Resources\Products\ProductCollectionResource;
use App\Http\Resources\Products\VariantListWithFilesResource;
use App\Http\Resources\Products\ProductListResource;
use App\Http\Resources\Products\VariantListResource;
use App\Http\Resources\Products\ProductResource;
use App\Jobs\DeleteProduct;
use App\Jobs\DeleteProductVariant;
use App\Jobs\ProcessPlatformProductQueue;
use App\Jobs\ProcessProductArtFile;
use App\Jobs\ProcessProductPrintFile;
use App\Jobs\ProcessProductVariantStageFile;
use App\Models\Accounts\AccountBlankAccess;
use App\Models\Accounts\AccountImage;
use App\Models\Blanks\ArtworkStage\BlankStageCreateType;
use App\Models\Blanks\ArtworkStage\BlankStageCreateTypeBlankStage;
use App\Models\Blanks\ArtworkStage\BlankStageImageRequirement;
use App\Models\Blanks\Blank;
use App\Models\Blanks\BlankPrintImage;
use App\Models\Blanks\BlankVariant;
use App\Models\ImageType;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Products\PlatformProductQueue;
use App\Models\Products\Product;
use App\Models\Products\ProductLog;
use App\Models\Products\ProductVariant;
use App\Models\Products\ProductArtFile;
use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductVariantPrintFile;
use App\Models\Products\ProductVariantStageFile;
use App\Rules\PlatformStoreExists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use SunriseIntegration\TeelaunchModels\Utils\Formatters\BytesFormatter;
use Symfony\Component\Process\Process;
use App\Traits\PrintFileJobsCreation;

/**
 * @group  Products
 *
 * APIs for managing products
 */

class ProductController extends Controller
{
    use PrintFileJobsCreation;

    public function index(Request $request)
    {
        //
    }

    /**
     * Get Platform Products
     *
     * Get platform products
     *
     * @queryParam  page paging limit
     */
    public function getProducts(Request $request)
    {
        $limit = (int)config('pagination.per_page');

        $products = Product::search($request->all())
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return ProductListResource::collection($products);
    }


    /**
     * Get Platform Product By Id
     *
     * Get platform product by id
     *
     * @urlParam id required id
     */
    public function getProductVariants(Request $request , $productId)
    {
        $variants = Product::with('variants.blankVariant.optionValues.option', 'artFiles.blankStageLocation')->find($productId);

        if (!$variants) {
            return $this->responseNotFound();
        }
        if($request->withFiles=='true'){
            return new VariantListWithFilesResource($variants);
        }else{
            return new VariantListResource($variants);
        }
    }

    /**
     * Get Products list
     *
     * Super lean query list. The standard index goes way down all associations. Takes forever.
     *
     * @queryParam  page paging limit
     */
    public function list(Request $request)
    {
        $limit = (int)config('pagination.per_page');

        $products = Product::
            with('variants.blankVariant.blank')
            ->where('account_id', '=', Auth::user()->account_id)
            ->search($request->all())
            ->orderBy('created_at', 'desc')
            ->paginate($limit);

        return ProductListResource::collection($products);
    }



    public function getProductsByCategoryId($id)
    {
        return Product::with('variants.blankVariant.optionValues.option')
            ->whereHas('variants.blankVariant.blank', function ($q) use ($id) {
                return $q->where('blank_category_id', $id);
            })
            ->take(20)
            ->get();
    }

    /**
     * Get Product By Id
     *
     * Get account product by id
     *
     * @urlParam id required id
     */
    public function show($id)
    {
        $product = Product::with([
            'variants.blankVariant.blank.category',
            'variants.blankVariant.optionValues',
            'variants.blankVariant.optionValues.option',
            'artFiles.blankStageLocation',
        ])->find($id);

        if (!$product) {
            return $this->responseNotFound();
        }

        $blankCategory = $product->variants[0]->blankVariant->blank->category->name;
        $product = ProductHelper::getSurcharge($product, $blankCategory);

        return new ProductResource($product);
    }

    /**
     * Create Product
     *
     * Create account product
     */
    public function create()
    {
        //
    }

    /**
     * Store Product
     *
     * Store product
     *
     * @bodyParam  *.name           string  name
     * @bodyParam  *.description    string  description
     *
     * @bodyParam  *.tags      array      tags
     * @bodyParam  *.tags.*    string     tags
     *
     * @bodyParam  *.blankVariants            array   required blankVariants
     * @bodyParam  *.blankVariants.*.id         int     required id
     * @bodyParam  *.blankVariants.*.blankId    int     required blankId
     * @bodyParam  *.blankVariants.*.isActive   boolean          isActive
     * @bodyParam  *.blankVariants.*.price      string  required price
     *
     * @bodyParam  *.stageFiles                                  array required  stageFiles
     * @bodyParam  *.stageFiles.*.blankId                          string required blankId
     * @bodyParam  *.stageFiles.*.imageId                          string required imageId
     * @bodyParam  *.stageFiles.*.createTypeId                     string required createTypeId
     * @bodyParam  *.stageFiles.*.blankStageGroupId                string required blankStageGroupId
     * @bodyParam  *.stageFiles.*.blankStageId                     string required blankStageId
     * @bodyParam  *.stageFiles.*.blankStageLocationId             string required blankStageLocationId
     * @bodyParam  *.stageFiles.*.blankStageLocationSubId          string required blankStageLocationSubId
     * @bodyParam  *.stageFiles.*.blankStageLocationSubOffsetId    array           blankStageLocationSubOffsetId
     *
     * @bodyParam  *.platformStores                   array               platformStores
     * @bodyParam  *.platformStores.*.id              int       required  id
     * @bodyParam  *.platformStores.*.collectionId    string              collectionId
     */
    public function store(Request $request)
    {
        $product = null;

        $validateFiles = config('app.enforce_image_requirements');

        $request->validate([
//          'blankId' => 'required|exists:blanks,id', //This may need to change to allow multiblank products

            '*.name' => 'string|min:1|max:190',
            '*.description' => 'string|max:65500|nullable',
            '*.tags' => 'array|max:5|nullable',
            '*.tags.*' => 'string|max:50',

            '*.blankVariants' => 'required|array',
            '*.blankVariants.*.id' => 'required|exists:blank_variants,id',
            '*.blankVariants.*.blankId' => 'required|integer',
            '*.blankVariants.*.isActive' => 'boolean',
            '*.blankVariants.*.price' => 'required|regex:/^\d+(\.\d{1,2})?$/',
//            'blankVariants.*.weight' => 'between:0,99999999.99',
//            'blankVariants.*.weightUnitId' => 'integer|exists:weight_units,id',

            '*.stageFiles.*.blankId' => 'required|exists:blanks,id',
            '*.stageFiles.*.imageId' => 'required|exists:account_images,id',
            '*.stageFiles.*.createTypeId' => 'required|exists:blank_stage_create_types,id',
            '*.stageFiles.*.blankStageGroupId' => 'required|exists:blank_stage_groups,id',
            '*.stageFiles.*.blankStageId' => 'required|exists:blank_stages,id',
            '*.stageFiles.*.blankStageLocationId' => 'required|exists:blank_stage_locations,id', //i.e. Front
            '*.stageFiles.*.blankStageLocationSubId' => 'required|exists:blank_stage_location_subs,id', //i.e. Left Chest
            '*.stageFiles.*.blankStageLocationSubOffsetId' => 'exists:blank_stage_location_sub_offsets,id|nullable', //i.e. Normal or Double

            '*.platformStores' => 'array|nullable',
            '*.platformStores.*.id' => ['required', new PlatformStoreExists(Auth::user()->account_id)], //Ensure only platform stores belonging to the account are allowed
            '*.platformStores.*.collectionId' => 'string|nullable',
        ]);

        $availableScopes = AccountBlankAccess::getStoreBlankAccessIds();

        $messageBag = new MessageBag();
        foreach ($request->all() as $reqProduct) {
            $reqProduct = (object)$reqProduct;

            //Check for unique blank variant ids
            $requestedVariantIds = [];
            foreach ($reqProduct->blankVariants as $blankVariantKey => $blankVariant) {
                if (in_array($blankVariant['id'], $requestedVariantIds)) {
                    $errorMessageBag = new MessageBag();
                    $errorMessageBag->add("blankVariants.$blankVariantKey.id", "blankVariants array must contain unique blankVariant ids");
                    $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                }
                $requestedVariantIds[] = $blankVariant['id'];
            }

            //Validate that the user is able to create products using the blanks available
            $blankVariants = BlankVariant::whereIn('id', $requestedVariantIds)->get();
            $blankIds = $blankVariants->pluck('blank')->pluck('id');
            $availableBlanks = Blank::whereIn('id', $blankIds)->available($availableScopes)->get();
            foreach ($blankIds as $blankVariantKey => $blankId) {
                if (array_search($blankId, array_column($availableBlanks->toArray(), 'id')) === false) {
                    $errorMessageBag = new MessageBag();
                    $errorMessageBag->add("blankVariants.$blankVariantKey.id", "blankVariant.blank $blankId is not available");
                    $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                }
            }
        }

        if ($messageBag->count() > 0) {
            return $this->responseBadRequest($messageBag);
        }

        foreach ($request->all() as $reqProductIndex => $reqProduct) {
            $reqProduct = (object)$reqProduct;
            //Log::debug('------------------------ PROCESS PRODUCT -------------------------');
            //Log::debug('Request Product: '.json_encode($reqProduct, JSON_PRETTY_PRINT));

            $accountImages = [];
            $blankStageCreateTypeBlankStages = [];
            foreach ($reqProduct->stageFiles as $fileKey => $stageFile) {
                //Log::debug('------------------------ STAGE FILE IMAGE ID '.$stageFile['imageId'].' -------------------------');
                $accountImage = AccountImage::findOrFail($stageFile['imageId']);
                $accountImages[$fileKey] = $accountImage;

                $blankStageCreateTypeBlankStage = BlankStageCreateTypeBlankStage::where([['blank_stage_id', $stageFile['blankStageId']], ['create_type_id', $stageFile['createTypeId']]])->with('image_requirement', 'create_type')->first();
                $blankStageCreateTypeBlankStages[$fileKey] = $blankStageCreateTypeBlankStage;

                //Ignore additional product validations to allow for batching of mismatched image requirements,
                // can be safely removed and enforces all products in a request to undergo validation
                if ($reqProductIndex !== 0) {
                    $validateFiles = false;
                }

                //Validate image requirements
                if ($validateFiles && $blankStageCreateTypeBlankStage) {
                    $imageRequirement = $blankStageCreateTypeBlankStage->image_requirement;
                    $imageTypes = $blankStageCreateTypeBlankStage->imageTypes;

                    $fileTypeAllowed = false;
                    $acceptedFileFormats = [];
                    foreach ($imageTypes as $imageType) {
                        $acceptedFileFormats[] = $imageType->imageType->file_extension;
                        if ($imageType->imageType && $imageType->image_type_id === $accountImage->image_type_id) {
                            $fileTypeAllowed = true;
                        }
                    }
                    if (!$fileTypeAllowed) {
                        $messageBag = new MessageBag();
                        $errorMessageBag = new MessageBag();
                        $errorMessageBag->add("stageFiles.$fileKey.imageId", "Image file format does not meet requirements. Accepted file formats are " . implode(', ', $acceptedFileFormats) . ".");
                        $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                        return $this->responseBadRequest($messageBag);
                    }

                    if ($imageRequirement->store_width_min && $imageRequirement->store_width_min > $accountImage->width || $imageRequirement->store_height_min && $imageRequirement->store_height_min > $accountImage->height) {
                        $messageBag = new MessageBag();
                        $errorMessageBag = new MessageBag();
                        $errorMessageBag->add("stageFiles.$fileKey.imageId", "Image dimensions $accountImage->width x $accountImage->height does not meet requirements: Minimum dimensions of $imageRequirement->store_width_min x $imageRequirement->store_height_min");
                        $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                        return $this->responseBadRequest($messageBag);
                    }
                    if ($imageRequirement->store_width_max && $imageRequirement->store_width_max < $accountImage->width || $imageRequirement->store_height_max && $imageRequirement->store_height_max < $accountImage->height) {
                        $messageBag = new MessageBag();
                        $errorMessageBag = new MessageBag();
                        $errorMessageBag->add("stageFiles.$fileKey.imageId", "Image dimensions $accountImage->width x $accountImage->height does not meet requirements: Maximum dimensions of $imageRequirement->store_width_max x $imageRequirement->store_height_max");
                        $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                        return $this->responseBadRequest($messageBag);
                    }
                    if ($imageRequirement->store_size_min && $imageRequirement->store_size_min > $accountImage->size) {
                        $messageBag = new MessageBag();
                        $errorMessageBag = new MessageBag();
                        $errorMessageBag->add("stageFiles.$fileKey.imageId", "Image size " . BytesFormatter::toHuman($accountImage->size) . " does not meet requirements: Minimum size of " . BytesFormatter::toHuman($imageRequirement->store_size_min));
                        $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                        return $this->responseBadRequest($messageBag);
                    }
                    if ($imageRequirement->store_size_max && $imageRequirement->store_size_max < $accountImage->size) {
                        $messageBag = new MessageBag();
                        $errorMessageBag = new MessageBag();
                        $errorMessageBag->add("stageFiles.$fileKey.imageId", "Image size " . BytesFormatter::toHuman($accountImage->size) . " does not meet requirements: Maximum size of " . BytesFormatter::toHuman($imageRequirement->store_size_max));
                        $messageBag->add('message', "The given data was invalid.")->add('errors', $errorMessageBag);
                        return $this->responseBadRequest($messageBag);
                    }
                }
            }
            $pickedMockups = property_exists($reqProduct, 'mockupIndex') ? $reqProduct->mockupIndex : [];

            $product = Product::create([
                'account_id' => Auth::user()->account_id,
//            'blank_id' => $reqProduct->blankId,
                'name' => $reqProduct->name ?? null,
                'description' => $reqProduct->description ?? null,
                'tags' => $reqProduct->tags ?? null,
                'picked_mockups' => $pickedMockups,
                'order_hold' => $reqProduct->orderHold ?? false,
            ]);

            ProductLog::create([
                'product_id'=> $product->id,
                'message' => "Product created",
                'message_type' => ProductLog::MESSAGE_TYPE_INFO
            ]);

            //Copy art files for product
            $productVariantArtFiles = [];
            $artFilesToDispatch = [];
            foreach ($reqProduct->stageFiles as $fileKey => $stageFile) {
                if ($blankStageCreateTypeBlankStages[$fileKey]->create_type->has_store_artwork) {
                    $productArtFile = ProductArtFile::create([
                        'account_id' => Auth::user()->account_id,
                        'status' => ProductVariantStageFile::STATUS_PENDING,
                        'account_image_id' => $accountImages[$fileKey]->id,
                        'product_id' => $product->id,
                        'blank_stage_group_id' => $stageFile['blankStageGroupId'] ?? null,
                        'blank_stage_id' => $stageFile['blankStageId'] ?? null,
                        'blank_stage_create_type_id' => $stageFile['createTypeId'] ?? null,
                        'blank_stage_location_id' => $stageFile['blankStageLocationId'] ?? null,
                        'blank_stage_location_sub_id' => $stageFile['blankStageLocationSubId'] ?? null,
                        'blank_stage_location_sub_offset_id' => $stageFile['blankStageLocationSubOffsetId'] ?? 1,
                        'blank_id' => $stageFile['blankId']
                    ]);

                    $productVariantArtFiles[$fileKey] = $productArtFile->id;

                    // dispatching here creates a race condition for Product Stage files below.
                    // add to array instead
                    // if (config('app.env') === 'local') {
                    //     ProcessProductArtFile::dispatch($productArtFile);
                    // } else {
                    //     ProcessProductArtFile::dispatch($productArtFile)->onQueue('stage-files');
                    // }
                    $artFilesToDispatch[] = $productArtFile;
                }
            }

            $blankVariantCount = 0;
            $accountImagesDispatched = [];
            foreach ($reqProduct->blankVariants as $blankVariant) {
                //debug('------------------------ BLANK VARIANT ID '.$blankVariant['id'].' -------------------------');
                //Log::debug('Blank Variant: '.json_encode($blankVariant, JSON_PRETTY_PRINT));

                $productVariant = ProductVariant::create([
                    'product_id' => $product->id,
                    'blank_variant_id' => $blankVariant['id'],
                    'price' => $blankVariant['price'] ?? null
                ]);

                $accountImage = null;
                $previousAccountImageId = null;

                $stageFileCount = 0;
                foreach ($reqProduct->stageFiles as $fileKey => $stageFile) {

                    if ($stageFile['blankId'] === $blankVariant['blankId']) {

                        $productVariantStageFile = ProductVariantStageFile::create([
                            'account_id' => Auth::user()->account_id,
                            'status' => ProductVariantStageFile::STATUS_RECEIVED,
                            'account_image_id' => $blankStageCreateTypeBlankStages[$fileKey]->create_type->has_store_artwork ? $accountImages[$fileKey]->id : null,
                            'product_art_file_id' => $blankStageCreateTypeBlankStages[$fileKey]->create_type->has_store_artwork ? $productVariantArtFiles[$fileKey] : null,
                            'product_id' => $product->id,
                            'product_variant_id' => $productVariant->id,
                            'blank_stage_group_id' => $stageFile['blankStageGroupId'] ?? null,
                            'blank_stage_id' => $stageFile['blankStageId'] ?? null,
                            'blank_stage_create_type_id' => $stageFile['createTypeId'] ?? null,
                            'blank_stage_location_id' => $stageFile['blankStageLocationId'] ?? null,
                            'blank_stage_location_sub_id' => $stageFile['blankStageLocationSubId'] ?? null,
                            'blank_stage_location_sub_offset_id' => $stageFile['blankStageLocationSubOffsetId'] ?? 1,
                            'blank_id' => $stageFile['blankId']
                        ]);

//                        // This is processed on ProcessProductArtFile job.
//                        if (config('app.env') === 'local') {
//                            ProcessProductVariantStageFile::dispatch($productVariantStageFile);
//                        } else {
//                            ProcessProductVariantStageFile::dispatch($productVariantStageFile)->onQueue('stage-files')->delay($blankVariantCount + ($stageFileCount * 2));
//                        }


                    }
                    $stageFileCount++;
                }
                $blankVariantCount++;
            }

            // dispatch art files not that Product Variant Stage Files have been processed
            foreach($artFilesToDispatch as $productArtFile){
                if (config('app.env') === 'local') {
                    ProcessProductArtFile::dispatch($productArtFile);
                } else {
                    ProcessProductArtFile::dispatch($productArtFile)->onQueue('stage-files');
                }
            }

            //Create PlatformProductQueue
            if ($reqProduct->platformStores) {
                //$delay = config('app.env') === 'local' ? 60 : 300;
                $averageMockupCreationTime = 10;
                $delay = 60 + count($reqProduct->blankVariants) * $averageMockupCreationTime;

                foreach ($reqProduct->platformStores as $platformStore) {
                    $platformProductQueue = PlatformProductQueue::create([
                        'account_id' => Auth::user()->account_id,
                        'platform_store_id' => $platformStore['id'],
                        'product_id' => $product->id
                    ]);

                    //This is now dispatched when last mockup image is received
                    if (isset($reqProduct->isMonogram) && $reqProduct->isMonogram) {
                        if (config('app.env') === 'local') {
                            ProcessPlatformProductQueue::dispatch($platformProductQueue);
                        } else {
                            ProcessPlatformProductQueue::dispatch($platformProductQueue)->onQueue('products');
                        }
                    }
                }

            }

            $product = Product::find($product->id);
        }

        return new ProductResource($product);
    }

    /**
     * Edit Product By Id
     *
     * Edit account product by id
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update Product By Id
     *
     * Update account product by id
     *
     * @bodyParam name string name
     * @bodyParam description string description
     * @urlParam id  id
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            '*.name' => 'string|min:1|max:190',
            '*.description' => 'string|max:65500|nullable'
        ]);

        $product = Product::find($id);

        if (!$product) {
            return $this->responseNotFound();
        }

        $product->name = $request->name;
        $product->description = $request->description;
        $product->save();

        if(!empty($request->artFiles)){

            $productArtFiles = ProductArtFile::where('account_id', Auth::user()->account_id)
                ->where('product_id', $product->id)
                ->get();

            foreach ($request->artFiles as $artFile){

                foreach ($productArtFiles as $productArtFile){

                    // make sure we are trying to update the art file for the corresponding blank
                    if($productArtFile->id == $artFile['id']){
                        if(isset($artFile['accountImageId']) ){
                            // if product art file is not changed avoid re-triggering images treatment jobs
                            if($productArtFile->account_image_id == $artFile['accountImageId']){
                                continue;
                            }

                            $productArtFile->account_image_id = $artFile['accountImageId'];
                            $productArtFile->status = ProductVariantStageFile::STATUS_PENDING;
                            $productArtFile->save();

                            if (config('app.env') === 'local') {
                                ProcessProductArtFile::dispatch($productArtFile);
                            }
                            else {
                                ProcessProductArtFile::dispatch($productArtFile)->onQueue('stage-files');
                            }
                        }
                    }
                }
            }
        }
        ProductLog::create([
            'product_id'=> $id,
            'message' => "Product updated",
            'message_type' => ProductLog::MESSAGE_TYPE_INFO
        ]);
        return $this->responseOk();
    }

    /**
     * Delete Product By Id
     *
     * Delete account product by id
     *
     * @urlParam  id required comma separated ids
     */
    public function destroy(Request $request, $id)
    {
        $ids = explode(',', $id);

        $products = Product::whereIn('id', $ids)->get();
        foreach ($products as $product) {
            $product->delete();
            ProductLog::create([
                'product_id'=> $product->id,
                'message' => "Product deleted",
                'message_type' => ProductLog::MESSAGE_TYPE_INFO
            ]);
            if (config('app.env') === 'local') {
                DeleteProduct::dispatch($product);
            } else {
                DeleteProduct::dispatch($product)->onQueue('deletes');
            }
        }

        return $this->index($request);
    }

    /**
     * Delete Product Variants
     *
     * Delete product variants
     *
     * @urlParam productId required productId
     * @urlParam variantId required comma separated variantId
     */
    public function destroyVariants(Request $request, $productId, $id)
    {

        $ids = explode(',', $id);

        $variants = ProductVariant::whereIn('id', $ids)->with('blankVariant.optionValues')->get();

        $variantName1 = '';
        $variantName2 = '';
        $variantsCount = 0;
        $variantName = [];

        foreach ($variants as $variant) {
            foreach ($variant->blankVariant->optionValues as $variantValue) {
                $variantName[] = $variantValue->name;
            }

            $variant->delete();

            if (config('app.env') === 'local') {
                DeleteProductVariant::dispatch($variant);
            } else {
                DeleteProductVariant::dispatch($variant)->onQueue('deletes');
            }
        }

        if (ProductVariant::where('product_id', $productId)->count() === 0) {
            Product::where('id', $productId)->delete();
        }

        $result =  implode(' / ', $variantName);

        ProductLog::create([
            'product_id'=> $productId,
            'message' => " Variant $result deleted",
            'message_type' => ProductLog::MESSAGE_TYPE_INFO
        ]);

        return $this->responseOk();
    }

    /**
     * Hold Product Orders
     *
     * Hold product orders
     *
     * @urlParam id required comma separated ids
     */
    public function ordersHold(Request $request, $id){

        $ids = explode(',', $id);

        try {
            $products = Product::whereIn('id', $ids)->where('order_hold', false)->get();
            foreach ($products as $product) {
                $product->ordersHold();
            }
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return new ProductCollectionResource($products);
    }

    /**
     * Release Product Orders
     *
     * Release product orders
     *
     * @urlParam id required comma separated ids
     */
    public function ordersRelease(Request $request, $id){

        $ids = explode(',', $id);

        try {
            $products = Product::whereIn('id', $ids)->where('order_hold', true)->get();
            foreach ($products as $product) {
                $product->ordersRelease();
            }
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return new ProductCollectionResource($products);
    }

    public function generateProductPrintFiles(Request $request){

        header('Access-Control-Allow-Origin: '.env('ADMIN_APP_URL'));

        $account_id = $request->a;
        $product_id = $request->p;

        try{
            //Delete product related print data
            ProductPrintFile::where(['account_id' => $account_id, 'product_id' => $product_id])->delete();
            ProductVariantPrintFile::where(['account_id' => $account_id, 'product_id' => $product_id])->delete();

            //Get product variants stage files
            $productVariantStageFiles = ProductVariantStageFile::where(['account_id' => $account_id, 'product_id' => $product_id])->get();
            foreach ($productVariantStageFiles as $productVariantStageFile){
                $blankId = $productVariantStageFile->blank_id;
                $blankStageGroupId = $productVariantStageFile->blank_stage_group_id;

                //Print files has no options - OLD PROCESS
                $printFiles = BlankPrintImage::where([
                    ['blank_id', $blankId],
                    ['blank_stage_group_id', $blankStageGroupId],
                    ['is_active', true]
                ])->whereNull('blank_option_value_id')->get();
                if($printFiles){
                    $this->createPrintFileJobs($printFiles, $productVariantStageFile, Log::class);
                }

                //Print files has multiple options - NEW PROCESS
                if ($productVariantStageFile->productVariant->blankVariant->optionValues->count()) {
                    $optionsPrintFiles = [];

                    //handle products with at least 1 option print files
                    $parentPrintFiles = BlankPrintImage::where('blank_id', $blankId)
                        ->where('blank_stage_group_id', $blankStageGroupId)
                        ->where('is_active', true)
                        ->whereNull('parent_id')
                        ->get();

                    foreach ($parentPrintFiles as $parentPrintFile){

                        $parentPrintFileOptions = DB::select(DB::raw('
                        SELECT GROUP_CONCAT(blank_option_value_id) as options
                            FROM blank_print_images
                            WHERE blank_id = :blankId
                                AND parent_id = :parentId
                                OR id = :parentPrintFileId
                        '), array(
                            'blankId' => $blankId,
                            'parentId' => $parentPrintFile->id,
                            'parentPrintFileId' => $parentPrintFile->id,
                        ));
                        $parentPrintFileOptions = explode(',', $parentPrintFileOptions[0]->options);

                        $countMatch = $productVariantStageFile->productVariant->blankVariant->optionValues->count();

                        foreach ($productVariantStageFile->productVariant->blankVariant->optionValues as $blankVariantOptionValue){
                            foreach ($parentPrintFileOptions as $parentPrintFileOption){
                                if($blankVariantOptionValue->id == $parentPrintFileOption){
                                    $countMatch--;
                                }
                            }
                        }
                        if($countMatch == 0){
                            array_push($optionsPrintFiles, $parentPrintFile);
                        }
                    }

                    $this->createPrintFileJobs($optionsPrintFiles, $productVariantStageFile, Log::class);
                }
            }
        }
        catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return $this->responseOk();
    }
}
