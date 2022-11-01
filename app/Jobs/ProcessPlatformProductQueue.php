<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Platforms\PlatformStoreProductLog;
use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Products\ProductLog;
use Carbon\Carbon;
use Exception;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Products\PlatformProductQueue;
use App\Models\Products\Product;
use App\Models\Products\ProductVariantMockupFile;

#Platform Includes

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Intervention\Image\ImageManager;
use SunriseIntegration\Etsy\Models\Listing;
use SunriseIntegration\Etsy\Models\ListingInventory;
use SunriseIntegration\Etsy\Models\ListingOffering;
use SunriseIntegration\Etsy\Models\ListingProduct;
use SunriseIntegration\Etsy\Models\Money;
use SunriseIntegration\Etsy\Models\PropertyValue;
use SunriseIntegration\Etsy\Helpers\EtsyHelper;
use SunriseIntegration\Shopify\Models\AbstractEntity;


//This needs to be generalized

#Shopify
use SunriseIntegration\Shopify\API as ShopifyAPI;
use App\Formatters\Shopify\ProductFormatter as ShopifyProductFormatter;
use SunriseIntegration\Shopify\Helpers\ShopifyDeliveryProfileHelper;
use App\Formatters\Shopify\VariantFormatter as ShopifyVariantFormatter;

#Rutter
use SunriseIntegration\Rutter\Http\Api as RutterApi;
use App\Formatters\Rutter\ProductFormatter as RutterProductFormatter;
use App\Formatters\Rutter\VariantFormatter as RutterVariantFormatter;

#Etsy
use SunriseIntegration\Etsy\API as EtsyAPI;
use App\Formatters\Etsy\ProductFormatter as EtsyProductFormatter;
use App\Formatters\Etsy\VariantFormatter as EtsyVariantFormatter;

#Launch
use App\Formatters\Launch\ProductFormatter as LaunchProductFormatter;
use App\Formatters\Launch\VariantFormatter as LaunchVariantFormatter;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Traits\MockupProcessing;

class ProcessPlatformProductQueue extends BaseJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MockupProcessing;

    public $tries = 1;
    public $retryAfter = 300; //5 minutes in seconds
    public $timeout = 300; //5 minutes in seconds

    protected $platformProductQueue;
    protected $accountId;
    protected $product;
    protected $platformStore;
    protected $mockupFilesPerVariant;

    protected $createImages = true;

    public $logChannel = 'platform-products';

    protected $etsyOptionSeparator = ' / ';
    protected $etsyPlaceHolderSKU = 'PLACEHOLDER';
    protected $etsySkuVariantIdMapping = [];

    protected $response;

    const RESIZE_RATIO_ETSY = 0.75;


    /**
     * Create a new job instance.
     *
     * @param PlatformProductQueue $platformProductQueue
     */
    public function __construct(PlatformProductQueue $platformProductQueue)
    {
        parent::__construct();
        $this->platformProductQueue = $platformProductQueue;
        $this->accountId = $this->platformProductQueue->account_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->response = null;
        $isMonogram = false;

        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $this->platformProductQueue->account_id,
            'system');
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessPlatformProductQueue | ID: {$this->platformProductQueue->id} | Status: {$this->platformProductQueue->status}");

        if (!isset($this->platformProductQueue->product) || $this->platformProductQueue->product->deleted_at) {
            $this->logger->info("Product has been deleted, abort process");
            return;
        }

        $debug = false;
        if ($debug) {
            $this->createImages = config('app.env') !== 'local';
        }

        $this->platformProductQueue->status = $this->platformProductQueue::STATUS_STARTED;
        $this->platformProductQueue->started_at = Carbon::now();
        $this->platformProductQueue->save();

        //Set vars
        $this->product = Product::where('id', $this->platformProductQueue->product_id)->first();
        $this->accountId = $this->product->account_id;
        $blankVariants = $this->product->variants->pluck('blankVariant');
        $blanks = $blankVariants->pluck('blank');
        $blanks = (new Collection($blanks))->unique();
        foreach ($blanks as $blank){
            if($blank->blank_category_id === 10){
                $isMonogram = true;
            }
        }

        if(!$isMonogram){
            //Check all mockups are created
            $totalMockupFileCount = ProductVariantMockupFile::where('product_id', $this->platformProductQueue->product_id)->count();
            if ($totalMockupFileCount == 0) {
                $this->logger->info("No mockup files found, releasing the job");
                $this->release($this->retryAfter);
                return;
            }

            $finishedMockupFileCount = ProductVariantMockupFile::where([['product_id', $this->platformProductQueue->product_id], ['status', ProductVariantMockupFile::STATUS_FINISHED]])->count();
            if ($finishedMockupFileCount < $totalMockupFileCount) {
                if ($this->attempts() < $this->tries) {
                    $this->logger->info("Mockup files are still processing, retry in {$this->retryAfter}s | Total Count: $totalMockupFileCount | Finished Count: $finishedMockupFileCount ");
                    $this->release($this->retryAfter);
                    return;
                }
                $this->logger->info("Mockup files are still processing, however this is the last attempt proceeding with creating product in platform | Total Count: $totalMockupFileCount | Finished Count: $finishedMockupFileCount ");
            }
        }

        $platformStore = PlatformStore::where([['account_id', $this->accountId], ['id', $this->platformProductQueue->platform_store_id]])->with('platform', 'account')->first();
        $this->platformStore = $platformStore;
        $this->account = $platformStore->account;

        $this->logger->info("Platform Store: " . json_encode($platformStore));
        //Format the data to match platform requirements
        $formattedData = $this->formatDataForPlatform($platformStore, $this->product);

        $this->logger->debug("Formatted Data: " . json_encode($formattedData));

        if (!$formattedData) {
            $this->logger->info("Failed to format product, failing the job");
            $this->platformProductQueue->status = $this->platformProductQueue::STATUS_FAILED;
            $this->platformProductQueue->finished_at = Carbon::now();
            $this->platformProductQueue->save();
            $this->fail(new Exception("Failed to format product, failing the job"));
        }

        //Send to platform
        if (!$this->sendToPlatform($platformStore, $formattedData)) {
            $this->logger->info("Failed to create product, failing the job");
            $this->platformProductQueue->status = $this->platformProductQueue::STATUS_FAILED;
            $this->platformProductQueue->finished_at = Carbon::now();
            $this->platformProductQueue->save();
            $this->fail(new Exception("Failed to create product, failing the job | Response: " . json_encode($this->response)));
        }

        //Set PlatformProductQueue status
        $this->platformProductQueue->status = $this->platformProductQueue::STATUS_FINISHED;
        $this->platformProductQueue->finished_at = Carbon::now();
        $this->platformProductQueue->save();

        $this->logger->title("End ProcessPlatformProductQueue | ID: {$this->platformProductQueue->id} | Status: {$this->platformProductQueue->status}");
    }

    /**
     * @param PlatformStore $platformStore
     * @param Product $product
     * @return mixed
     */
    public function formatDataForPlatform($platformStore, $product)
    {
        $formattedData = null;
        $platformName = strtolower($platformStore->platform->name);

        $this->logger->info("----- Format Product for $platformName Store {$platformStore->name} | Product ID: {$this->product->id} | Platform Store ID: {$platformStore->id} -----");

        switch ($platformName) {
            case 'shopify':
                $formattedData = ShopifyProductFormatter::formatForPlatform($product, $platformStore, $this->logger);
                break;
            case 'etsy':
                try {
                    $shippingTemplateId = $platformStore->settings()->where('key', 'shipping_template_id')->first()->value ?? EtsyHelper::createShippingTemplate($platformStore, $this->logger);

                    if (!$shippingTemplateId) {
                        throw new Exception("No shipping template available");
                    }

                    $options = [
                        'shippingTemplateId' => $shippingTemplateId
                    ];
                    $formattedData = EtsyProductFormatter::formatForPlatform($product, $platformStore, $options, $this->logger);
                    $this->logger->info("Etsy formatted data: " . json_encode($formattedData));
                } catch (Exception $e) {
                    $this->logger->error($e);
                    $this->fail($e);
                }
                break;
            case 'launch':
                $formattedData = LaunchProductFormatter::formatForPlatform($product, $platformStore, $this->logger);
                break;
            case 'rutter':
                $formattedData = RutterProductFormatter::formatForPlatform($product, $platformStore, $this->logger);
                break;
            default:
                //Fail
                $this->logger->warning("No matching platform found");
                $this->fail(new Exception("Platform $platformName doesn't exist"));
                break;
        }
        return $formattedData;
    }

    /**
     * @param PlatformStore $platformStore
     * @param AbstractEntity $platformProduct
     * @return bool
     */
    public function sendToPlatform($platformStore, $platformProduct)
    {
//        $platformStoreProduct = null;
        $platformVariants = null;
        $platformImages = null;
        $isMonogram = false;

        $platformName = strtolower($platformStore->platform->name);
        $this->logger->info("----- Send Product to $platformName Store {$platformStore->name} | Product ID: {$this->product->id} | Platform Store ID: {$platformStore->id} -----");
        switch ($platformName) {
            case 'shopify':
                //This doesn't go here, needs a Factory
                $apiOptions = [
                    'shop' => $platformStore->url,
                    'key' => config('shopify.api_key'),
                    'secret' => config('shopify.api_secret'),
                    'token' => $platformStore->api_token,
                    'logger' => $this->logger
                ];
                if (config('app.env') === 'local') {
                    $this->logger->debug("API Options: " . json_encode($apiOptions));
                }
                $shopifyApi = new ShopifyAPI($apiOptions);
                $productArray = $platformProduct->toArray();

                // remove null values, they cause issues
                $productArray = array_filter($productArray, function ($value) {
                    return !is_null($value);
                });
                if ($productArray['variants']) {
                    foreach ($productArray['variants'] as $i => $variant) {
                        $productArray['variants'][$i] = array_filter($productArray['variants'][$i], function ($value) {
                            return !is_null($value);
                        });
                    }
                }


                $this->logger->debug("Shopify post product: " . json_encode($productArray));
                $this->response = $shopifyApi->addProduct($productArray);
                $this->logger->info("---------- Create Product | HTTP {$shopifyApi->lastHttpCode()} ----------");

                if ($shopifyApi->lastHttpCode() !== 201) {
                    $this->logger->error("Failed to create product");
                    $this->logger->info("Sent Data: {$platformProduct->toJson()}");
                    $this->logger->info("Response: " . json_encode($this->response));
                    return false;
                }

                // setup shipping profiles
                $deliveryProfileHelper = new ShopifyDeliveryProfileHelper($platformStore, $this->logger);

                $platformProduct = new \SunriseIntegration\Shopify\Models\Product();
                $platformProduct->load($this->response);
                $shopifyVariants = $platformProduct->getVariants();


                //Add images to Shopify product
                $blankVariants = $this->product->variants->pluck('blankVariant');
                $blanks = $blankVariants->pluck('blank');
                $blanks = (new Collection($blanks))->unique();

                foreach ($blanks as $blank){
                    if($blank->blank_category_id === 10){
                        $isMonogram = true;
                    }
                }
                $variants = new Collection($this->product->variants);

                $mockupFilesToSend = !$isMonogram ? $this->getUniqueMockups($blanks, $variants, 250) : [];

                $imageManager = new ImageManager(array('driver' => 'imagick'));

                $variantInfo = [];
                foreach ($variants as $variant) {
                    $variantInfo[] = ['id' => $variant->id, 'optionValues' => $variant->blankVariant->optionValues->pluck('name')];
                }

                $this->logger->debug("Variants: " . json_encode($variants->pluck('id')));
                $this->logger->debug("Variant Info: " . json_encode($variantInfo));

                foreach ($mockupFilesToSend as $mockupFileIndex => $mockupFile) {
                    if ($mockupFile->productMockupFile->file_url_original) {
                        //Encode as JPG
                        $imagePath = $mockupFile->productMockupFile->file_url_original;
                        $imageEncoded = $imageManager->make($imagePath)->encode('jpg');
                        $productImage = [
                            'attachment' => base64_encode($imageEncoded),
                            'filename' => $mockupFile->productMockupFile->file_name . '.jpg'
                        ];

                        //Assign Shopify Variant IDs to Image, using each Shopify variant only once
                        $shopifyVariantIds = [];
                        foreach ($variants as $variantIndex => $variant) {
                            foreach ($variant->mockupFiles as $variantMockupFile) {
                                if ($variantMockupFile->productMockupFile->id === $mockupFile->productMockupFile->id) {
                                    $shopifyVariantIds[] = $shopifyVariants[$variantIndex]->getId();
                                    $this->logger->debug("Assign Variant ID {$variant->id} to Mockup File ID {$mockupFile->productMockupFile->id}");
                                    break;
                                }
                            }
                        }
                        $productImage['variant_ids'] = $shopifyVariantIds;

                        $this->response = $shopifyApi->addProductImage($platformProduct->getId(), $productImage);

                        $this->logger->info("---------- Create Product Image | HTTP {$shopifyApi->lastHttpCode()} ----------");

                        $this->logger->debug("Variant IDs: " . json_encode($shopifyVariantIds));
                        $this->logger->debug("Mockup File: " . json_encode($mockupFile));

                        if ($shopifyApi->lastHttpCode() !== 200) {
                            $this->logger->error("Failed to create product image");
                            //$this->logger->info("Sent Data: " . json_encode($productImage));
                            $this->logger->info("Response: " . json_encode($this->response));
                            //Failed to create image, what to do here?
                        }
                    }
                }

                if($isMonogram){
                    foreach ($blankVariants as $blankVariant) {
                        if ($blankVariant->image) {
                            //Encode as JPG
                            $imagePath = $blankVariant->image->file_url_original;
                            $imageEncoded = $imageManager->make($imagePath)->encode('jpg');
                            $productImage = [
                                'attachment' => base64_encode($imageEncoded),
                                'filename' => $blankVariant->image->file_name . '.jpg'
                            ];

                            //Assign Shopify Variant IDs to Image, using each Shopify variant only once
                            $shopifyVariantIds = [];
                            foreach ($variants as $variantIndex => $variant) {
                                if ($variant->blank_variant_id === $blankVariant->id) {
                                    $shopifyVariantIds[] = $shopifyVariants[$variantIndex]->getId();
                                    break;
                                }
                            }

                            $productImage['variant_ids'] = $shopifyVariantIds;

                            $this->response = $shopifyApi->addProductImage($platformProduct->getId(), $productImage);

                            $this->logger->info("---------- Create Product Image | HTTP {$shopifyApi->lastHttpCode()} ----------");

                            if ($shopifyApi->lastHttpCode() !== 200) {
                                $this->logger->error("Failed to create product image");
                                //$this->logger->info("Sent Data: " . json_encode($productImage));
                                $this->logger->info("Response: " . json_encode($this->response));
                                //Failed to create image, what to do here?
                            }
                        }
                    }
                }

                // pull full platform product with added images
                $this->response = $shopifyApi->getProduct($platformProduct->getId());
                $shopifyProduct = new \SunriseIntegration\Shopify\Models\Product();
                $shopifyProduct->load($this->response);
                $platformProduct = $shopifyProduct;

                //If success add product to platform_products_table and platform_variants_table
                $platformStoreProduct = $this->saveToDb($platformStore, $platformProduct, $platformVariants, $platformImages);

                // update the delivery profiles
                $this->logger->info("Updating delivery profiles");
                $deliveryProfileHelper->updateDeliveryProfiles();

                // add variants to shipping rule
                $deliveryProfilesArr = [];
                foreach($shopifyVariants as $variant){
                    $shopifyDeliveryProfileId = $deliveryProfileHelper->getPlatformDeliveryProfileIdForPlatformVariant(
                        $variant->getId()
                    );
                    // $this->logger->debug("Delivery variant ". $variant->getId() ." delivery profile id: " . json_encode($shopifyDeliveryProfileId));
                    if($shopifyDeliveryProfileId){
                        if(!isset($deliveryProfilesArr[$shopifyDeliveryProfileId])){
                            $deliveryProfilesArr[$shopifyDeliveryProfileId] = [];
                        }
                        $deliveryProfilesArr[$shopifyDeliveryProfileId][] = $variant->getId();
                    } else {
                        $this->logger->error("Could not find delivery profile for Shopify variant id ". $variant->getId());
                    }
                }
                // add variants to delivery profile
                foreach($deliveryProfilesArr as $profileId => $profileVariants){
                    $response = $deliveryProfileHelper->addVariantsToDeliveryProfile(
                        $profileId,
                        $profileVariants
                    );
                }
                return $platformStoreProduct;

                break;
            case 'etsy':
                $etsyApi = new EtsyAPI(config('etsy.api_key'), config('etsy.api_secret'), $platformStore->api_token, $platformStore->api_secret, $this->logger);

                $this->response = $etsyApi->createListing($platformProduct->toArray());
                $this->logger->info("Create listing | HTTP $etsyApi->lastHttpCode");
                $this->logger->info("Response: " . json_encode($this->response));

                //If failed due to non-existing payment template
                if ($etsyApi->lastHttpCode !== 201) {
                    if (stripos($this->response,'The user must have a payment template in order to create or update a listing') !== false) {
                        $this->logger->error("Failed to create product: The user must have a payment template in order to create or update a listing.");

                        ProductLog::create([
                            'product_id'=> $this->product->id,
                            'message' => "You must add a payment method to etsy before you create products",
                            'message_type' => ProductLog::MESSAGE_TYPE_ERROR
                        ]);

                        return false;
                    }
                }

                //If failed due to deleted Etsy shipping profile, create new one and try again
                if ($etsyApi->lastHttpCode !== 201) {
                    if (
                        stripos($this->response,'Shipping profile name does not match the shipping profile type') !== false ||
                        stripos($this->response,'Shipping template does not exist"') !== false
                    ) {
                        try {
                            $this->logger->info("Shipping profile does not exist. Creating one.");
                            $shippingProfileId = EtsyHelper::createShippingTemplate($platformStore, $this->logger);
                            if ($shippingProfileId) {
                                $platformProduct->setShippingTemplateId($shippingProfileId);
                                $this->response = $etsyApi->createListing($platformProduct->toArray());
                                $this->logger->info("Create listing | HTTP $etsyApi->lastHttpCode");
                                $this->logger->info("Response: " . json_encode($this->response));
                            } else {
                                $this->logger->error("Failed to create Shipping Profile");
                            }
                        } catch (Exception $e) {
                            $this->logger->error($e);
                        }
                    }
                }

                if ($etsyApi->lastHttpCode !== 201) {
                    $this->logger->error("Failed to create product");
                    $this->logger->info("Sent Data: {$platformProduct->toJson()}");

                    if ((config('app.env') !== 'local') && $this->response == 'Rate limit exceeded') {
                        ProcessPlatformProductQueue::dispatch($this->platformProductQueue)->onQueue('products')->delay(now()->addHours(1));
                    }

                    return false;
                }

                $etsyListing = new Listing($this->response);

                $listingId = $etsyListing->getListingId();
                $this->logger->info("Listing ID: " . $listingId);
                //Add variants here
                $blankVariants = $this->product->variants->pluck('blankVariant');
                $blanks = $blankVariants->pluck('blank');
                $blanks = (new \Illuminate\Database\Eloquent\Collection($blanks))->unique();


                //Create Etsy Variant Data
                $imageManager = new ImageManager(array('driver' => 'imagick'));

                $variants = $this->product->variants;

                foreach ($blanks as $blank){
                    if($blank->blank_category_id === 10){
                        $isMonogram = true;
                    }
                }

                $mockupFilesToSend = !$isMonogram ? $this->getUniqueMockups($blanks, $variants, 10) : [];

                $usedOptionValues = [];
                $variantOptionValues = [];
                $useStyleOption = $blanks->count() > 1;
                foreach ($variants as $variantIndex => $variant) {
                    //Get blank options
                    $blankOptions = new Collection();
                    foreach ($blanks as $blank) {
                        foreach ($blank->options as $blankOption) {
                            $blankOptions->push($blankOption);
                        }
                    }
                    $blankOptions = $blankOptions->unique();
                    $this->logger->debug("Blank Options:" . json_encode($blankOptions));

                    //Get options
                    $options = [];
                    if ($useStyleOption) {
                        $options[] = "Style";
                    }
                    $hasColorOption = false;
                    foreach ($blankOptions as $option) {
                        if (stripos($option->name, 'color') !== false) {
                            $hasColorOption = true;
                        }
                        if (count($options) < 2) {
                            $options[] = $option->name;
                        }
                    }
                    $this->logger->debug("Options:" . json_encode($options));

                    //Get option values
                    $optionValues = [];
                    if ($useStyleOption) {
                        $optionValues[] = $variant->blankVariant->blank->name;
                        $usedOptionValues['Style'][] = $variant->blankVariant->blank->name;
                    }

                    //Need to sort values to match options
                    $this->logger->subheader("Sort Blank Options");
                    $this->logger->info("Blank Options:" . json_encode($blankOptions));
                    foreach ($blankOptions as $blankOptionIndex => $blankOption) {
                        $this->logger->debug("Blank Options {$blankOption->id}");

                        $matchFound = false;
                        foreach ($variant->blankVariant->optionValues as $optionValueIndex => $optionValue) {
                            $this->logger->debug("Blank Variant Option Values ID {$optionValue->option->id}");

                            if ($blankOption->id == $optionValue->option->id) {
                                $optionValues[] = $optionValue->name;
                                $usedOptionValues[$blankOption->name][] = $optionValue->name;
                                $this->logger->debug("MATCH");
                                $matchFound = true;
                                break;
                            }
                        }

                        if (!$matchFound) {
                            //Add null value since none found
                            if (count($optionValues) <= $blankOptionIndex) {
                                $this->logger->debug("NO MATCH AND optionValues COUNT LESS THAN OR EQUAL TO blankOptionIndex");
                                $optionValues[] = null;
                            } else {
                                $this->logger->debug("NO MATCH");
                                $optionValues[] = 'Default';
                                $usedOptionValues[$blankOption->name][] = 'Default';

                            }
                        }
                    }
                    $this->logger->debug("Option Values:" . json_encode($optionValues));

                    $compressedOptionValues = $optionValues;
                    if (count($optionValues) > 2) {
                        $compressedOptionValues = [];
                        $compressedOptionValues[] = $optionValues[0];
                        $remainingOptions = array_slice($optionValues, 1);
                        $compressedOptionValues[] = implode(' / ', $remainingOptions);
                    }
                    $variantOptionValues[$variant->id] = $compressedOptionValues;

                    foreach ($options as $index => $option) {
                        if ($index > 1) {
                            break;
                        }

                    }

                }

                $etsyMaxAllowedOptionValues = 70; //Etsy limits a max of 70 option values per option
                $this->logger->debug("Used Option Values:" . json_encode($usedOptionValues));
                foreach ($usedOptionValues as $optionName => $values) {
                    $arrayValues = array_values(array_unique($values));
                    $usedOptionValues[$optionName] = $arrayValues; //array_slice($arrayValues, 0, $etsyMaxAllowedOptionValues);
                }
                $this->logger->debug("Unique Used Option Values:" . json_encode($usedOptionValues));

                $i = 0;
                $iterableOptionValues = [];
                foreach ($usedOptionValues as $oName => $oValues) {
                    $iterableOptionValues[$i] = ['name' => $oName, 'values' => $oValues];
                    $i++;
                }

                $allOptionValues = [];
                $optionValues1 = $iterableOptionValues[0] ?? [];
                $optionValues2 = $iterableOptionValues[1] ?? [];
                $optionValues3 = $iterableOptionValues[2] ?? [];
                $usedCombinedValues = [];
                if (count($optionValues1) > 0) {
                    foreach ($optionValues1['values'] as $value1) {
                        if (isset($optionValues2['values']) && count($optionValues2['values']) > 0) {
                            foreach ($optionValues2['values'] as $value2) {
                                if (isset($optionValues3['values']) && count($optionValues3['values']) > 0) {
                                    foreach ($optionValues3['values'] as $value3) {
                                        //Combine option 2 and option 3 values as Etsy only allows 2 options per product
                                        $combinedValues = $value2 . ' / ' . $value3;
                                        $usedCombinedValues[] = $combinedValues;
                                        $usedCombinedValues = array_values(array_unique($usedCombinedValues));
                                        if(count($usedCombinedValues) > 70){
                                            $this->logger->warning("Skipping the rest of the variants due to Etsy limitation of 70 option values per option");
                                            break(3);
                                        }
                                        $allOptionValues[] = [$value1, $combinedValues];
                                    }
                                } else {
                                    $allOptionValues[] = [$value1, $value2];
                                }
                            }
                        } else {
                            $allOptionValues[] = [$value1];
                        }
                    }
                }
                $this->logger->debug("Used Combined Option Values:" . json_encode($usedCombinedValues));
                $this->logger->debug("All Option Values:" . json_encode($allOptionValues));
                $this->logger->debug('Variant Option Values:' . json_encode($variantOptionValues));

                /**
                 * New process for Etsy
                 */
                $listingProducts = [];
                $usedPropertyIds = [];

                //Handle products without variants
                if (count($allOptionValues) == 0) {
                    $this->logger->debug("Handle product without variants");
                    $listingProduct = new ListingProduct();
                    $propertyValues = [];
                    $variant = $this->product->variants[0];

                    $listingProduct->setPropertyValues($propertyValues);

                    $offering = new ListingOffering();
                    $offering->setPrice($variant->price ?? $lastVariantPrice ?? $this->product->variants->first()->price);
                    $offering->setQuantity(999);
                    $offering->setIsEnabled(true);

                    $listingProduct->setOfferings([$offering]);

                    $sku = $variant->blankVariant->sku ?? $this->etsyPlaceHolderSKU;
                    $sku = substr($sku, 0, 32);
                    $this->etsySkuVariantIdMapping[$sku] = $variant->id;
                    $listingProduct->setSku($sku);

                    $listingProducts[] = [
                        'property_values' => $listingProduct->getPropertyValues(),
                        'sku' => $listingProduct->getSku(),
                        'offerings' => $listingProduct->getOfferings()
                    ];
                }

                //Handle products with variants
                foreach ($allOptionValues as $optionValues) {
                    //foreach ($this->product->variants as $variantIndex => $variant) {

                    $listingProduct = new ListingProduct();
                    $propertyValues = [];

                    /**
                     * Property IDS
                     * Size = 62809790395
                     * Primary Color = 200
                     **/
                    $isValidVariant = true;
                    foreach ($iterableOptionValues as $index => $iOptionValue) {
                        //Etsy only allows 2 Options
                        if ($index > 1) {
                            break;
                        }


                        //Use option name unless this is the last option, then combine any additional options
                        $option = $iOptionValue['name'];
                        if ($index == 1) {
                            $optionNames = [];
                            for ($i = 1; $i < count($iterableOptionValues); $i++) {
                                $optionNames[] = $iterableOptionValues[$i]['name'];
                            }
                            $option = implode(' / ', $optionNames);
                        }


                        //TODO: Determine Scent and Depth property ids from Etsy Taxonomy
                        $propertyId = null;
                        $propertyIds = [
                            'style' => 510,
                            'color' => 200,
                            'size' => 100,
                            'quantity' => 510,
                            'scent' => 510,
                            'depth' => 510
                        ];
                        //Find Property ID by name
                        foreach ($propertyIds as $propName => $propId) {
                            if (stripos($option, $propName) !== false) {
                                $propertyId = $propId;
                                if (!in_array($propertyId, $usedPropertyIds)) {
                                    $usedPropertyIds[] = $propertyId;
                                }
                                break;
                            }
                        }


                        //Catch all Style property if no match found
                        if (!$propertyId) {
                            $propertyId = 510;
                            if (!in_array($propertyId, $usedPropertyIds)) {
                                $usedPropertyIds[] = $propertyId;
                            }
                        }


                        $optionName = $option;
                        $optionName = str_replace('Sizes', 'Size', $optionName);


                        $propertyValue = new PropertyValue();
                        $propertyValue->setPropertyName($optionName);
                        if ($propertyId) {
                            $propertyValue->setPropertyId($propertyId);
                        }

                        $propertyValue->setValues([$optionValues[$index]]);

                        if ($propertyId === 100) {
                            $alphaScaleId = 301;
                            $propertyValue->setScaleId($alphaScaleId);
                        }

                        $this->logger->info('Property Value:' . json_encode($propertyValue));
                        $propertyValues[] = $propertyValue;
                    }


                    $this->logger->debug('$optionValues: ' . json_encode($optionValues));
                    $this->logger->debug('$variantOptionValues: ' . json_encode($variantOptionValues));
                    $isValidVariant = in_array($optionValues, $variantOptionValues);

                    $this->logger->debug('Is valid variant? ' . ($isValidVariant ? 'true' : 'false'));
                    $variant = null;
                    $variantId = null;
                    if ($isValidVariant) {
                        $variantId = array_search($optionValues, $variantOptionValues);
                        $this->logger->info('Variant ID: ' . $variantId);
                        //$this->logger->debug('Product Variants: ' . json_encode($this->product->variants));

                        $variant = Arr::first($this->product->variants, function ($variant) use ($variantId) {
                            return (int)$variant->id == (int)$variantId;
                        });
                        $this->logger->debug('Variant: ' . json_encode($variant));

                        $lastVariantPrice = $variant->price;
                    }

                    $listingProduct->setPropertyValues($propertyValues);

                    $offering = new ListingOffering();
                    $offering->setPrice($variant->price ?? $lastVariantPrice ?? $this->product->variants->first()->price);
                    $offering->setQuantity($isValidVariant ? 999 : 0);
                    $offering->setIsEnabled($isValidVariant);

                    $listingProduct->setOfferings([$offering]);

                    $sku = $variant->blankVariant->sku ?? $this->etsyPlaceHolderSKU;
                    if ($isValidVariant) {
                        $this->etsySkuVariantIdMapping[$sku] = $variantId;
                    }
                    $sku = substr($sku, 0, 32);

                    //Set only valid options sku's
                    $listingProduct->setSku($isValidVariant ? $sku : '');

                    $listingProducts[] = [
                        'property_values' => $listingProduct->getPropertyValues(),
                        'sku' => $listingProduct->getSku(),
                        'offerings' => $listingProduct->getOfferings()
                    ];
                }

                /**
                 * END OF New process for Etsy
                 */

                $this->logger->info("Listing Products: " . json_encode($listingProducts));

                if (count($listingProducts)) {
                    $propertyOnId = $usedPropertyIds; //array_slice($usedPropertyIds,0,1);
                    $this->logger->info("Property on ID: " . json_encode($propertyOnId));
                    $dataToPost = [
                        'products' => json_encode($listingProducts),
                        'price_on_property' => implode(',', $propertyOnId),
                        'sku_on_property' => implode(',', $propertyOnId),
                        'quantity_on_property' => implode(',', $propertyOnId)
                    ];

                    $this->response = $etsyApi->updateListingInventory($etsyListing->getListingId(), $dataToPost);
                    $this->logger->info("Update listing | HTTP $etsyApi->lastHttpCode");
                    $this->logger->info("Sent ListingInventory Data: " . json_encode($dataToPost));

                    if ($etsyApi->lastHttpCode != 200) {
                        $this->logger->error("Failed to create Etsy Listing");
                        $this->fail(new Exception('Failed to create Etsy Listing: '.$this->response));
                    }

                    $platformVariants = new \SunriseIntegration\Etsy\Models\ListingInventory($this->response);
                    $this->logger->info("Platform Variants Response | Data: " . $platformVariants->toJson());
                }

                //Send Mockup Files
                $this->logger->info(count($mockupFilesToSend) . " mockupFilesToSend:" . json_encode($mockupFilesToSend));

                if ($this->createImages) {
                    if($isMonogram){
                        foreach ($blankVariants as $blankVariant) {
                            if ($blankVariant->image) {
                                //Encode as JPG
                                $imagePath = $etsyApi->downloadArtwork($blankVariant->image->file_url_original);
                                try {
                                    $imageInfo = $imageManager->make($imagePath);
                                    $imageResized = $imageManager->make($imagePath)->resize(intval($imageInfo->width() * self::RESIZE_RATIO_ETSY), intval($imageInfo->height() * self::RESIZE_RATIO_ETSY));
                                    $imageManager->canvas($imageInfo->width(), $imageInfo->height(), '#ffffff')->insert($imageResized, 'center')->save($imagePath);

                                    $this->response = $etsyApi->oauthAddImages($etsyListing->getListingId(), $imagePath, $etsyListing->getTitle());

                                    $this->logger->info("---------- Create Product Image | HTTP {$etsyApi->lastHttpCode} ----------");


                                    if ($etsyApi->lastHttpCode !== 200 && $etsyApi->lastHttpCode !== 201) {
                                        $this->logger->error("Failed to create product image");
                                        $this->logger->error("Response: " . json_encode($this->response));
                                    } else {
                                        $this->logger->debug("Sent Data: " . json_encode($imagePath));
                                        $this->logger->debug("Response: " . json_encode($this->response));
                                    }

                                }
                                catch (Exception $e) {
                                    $this->logger->error($e);
                                }

                                unlink($imagePath);
                            }
                        }
                    }
                    else{
                        do {
                            $mockupFile = array_pop($mockupFilesToSend);
                            if (is_object($mockupFile)) {
                                $this->logger->info("Downloading {$mockupFile->productMockupFile->file_url_original} | Dir: $etsyApi->uploadPath");
                                $imagePath = $etsyApi->downloadArtwork($mockupFile->productMockupFile->file_url_original);
                                //Set white bg on image for Etsy
                                try {
                                    $imageInfo = $imageManager->make($imagePath);
                                    $imageResized = $imageManager->make($imagePath)->resize(intval($imageInfo->width() * self::RESIZE_RATIO_ETSY), intval($imageInfo->height() * self::RESIZE_RATIO_ETSY));
                                    $imageManager->canvas($imageInfo->width(), $imageInfo->height(), '#ffffff')->insert($imageResized, 'center')->save($imagePath);

                                    $this->response = $etsyApi->oauthAddImages($etsyListing->getListingId(), $imagePath, $etsyListing->getTitle());

                                    $this->logger->info("---------- Create Product Image | HTTP {$etsyApi->lastHttpCode} ----------");


                                    if ($etsyApi->lastHttpCode !== 200 && $etsyApi->lastHttpCode !== 201) {
                                        $this->logger->error("Failed to create product image");
                                        $this->logger->error("Response: " . json_encode($this->response));
                                    } else {
                                        $this->logger->debug("Sent Data: " . json_encode($imagePath));
                                        $this->logger->debug("Response: " . json_encode($this->response));
                                    }

                                } catch (Exception $e) {
                                    $this->logger->error($e);
                                }

                                unlink($imagePath);
                            }
                        } while ($mockupFilesToSend);
                    }
                }

                $this->response = $etsyApi->getListing($etsyListing->getListingId());
                $this->logger->info("Get Listing Response:" . json_encode($this->response));

                if ($etsyApi->lastHttpCode === 200) {
                    $listingData = $this->response->results;
                    $etsyListing = new Listing($listingData[0]);
                    $platformImages = $etsyListing->getImages();
                }

                $platformProduct = $etsyListing;

                //If success add product to platform_products_table and platform_variants_table
                return $this->saveToDb($platformStore, $platformProduct, $platformVariants, $platformImages);
                break;
            case 'launch':
                $productArray = $platformProduct;
                $this->logger->debug("Launch post product: " . json_encode($productArray));

                //Add images to Launch product
                $blankVariants = $this->product->variants->pluck('blankVariant');
                $blanks = $blankVariants->pluck('blank');
                $blanks = (new Collection($blanks))->unique();

                //Check If Monogram
                foreach ($blanks as $blank){
                    if($blank->blank_category_id === 10){
                        $isMonogram = true;
                    }
                }

                $variants = new Collection($this->product->variants);
                $mockupFilesToSend = !$isMonogram ? $this->getUniqueMockups($blanks, $variants, 250) : [];

                $this->logger->debug("Variants: " . json_encode($variants->pluck('id')));

                foreach ($mockupFilesToSend as $mockupFileIndex => $mockupFile) {
                    if ($mockupFile->productMockupFile->file_url_original) {
                        //Encode as JPG
                        $productImage = $mockupFile->productMockupFile->file_url_original;

                        //Assign Launch Variant IDs to Image, using each Launch variant only once
                        foreach ($variants as $variantIndex => $variant) {
                            foreach ($variant->mockupFiles as $variantMockupFile) {
                                if ($variantMockupFile->productMockupFile->id === $mockupFile->productMockupFile->id) {
                                    $productArray['variants'][$variantIndex]['images'][] = $mockupFile->file_url;
                                    $this->logger->debug("Assign Variant ID {$variant->id} to Mockup File ID {$mockupFile->productMockupFile->id}");
                                    break;
                                }
                            }
                        }
                    }
                }

                if($isMonogram){
                    foreach ($blankVariants as $blankVariant) {
                        if ($blankVariant->image) {
                            //Encode as JPG
                            $imagePath = $blankVariant->image->file_url_original;
                            $productImage = $imagePath;

                            //Assign Launch Variant IDs to Image, using each Launch variant only once
                            $shopifyVariantIds = [];
                            foreach ($variants as $variantIndex => $variant) {
                                if ($variant->blank_variant_id === $blankVariant->id) {
                                    $productArray['variants'][$variantIndex]['images'][] = $productImage;
                                    break;
                                }
                            }
                        }
                    }
                }

                // pull full platform product with added images
                $platformProduct = $productArray;

                //If success add product to platform_products_table and platform_variants_table
                $platformStoreProduct = $this->saveToDb($platformStore, $platformProduct, $platformVariants, $platformImages);

                return $platformStoreProduct;
                break;
            case 'rutter':
                $rutterApi = new RutterApi($platformStore->api_token);
                $productArray = $platformProduct;

                //Add images to Rutter product
                $blankVariants = $this->product->variants->pluck('blankVariant');
                $blanks = $blankVariants->pluck('blank');
                $blanks = (new Collection($blanks))->unique();

                foreach ($blanks as $blank){
                    if($blank->blank_category_id === 10){
                        $isMonogram = true;
                    }
                }
                $variants = new Collection($this->product->variants);
                $mockupFilesToSend = !$isMonogram ? $this->getUniqueMockups($blanks, $variants, 250) : [];

                if (!$isMonogram) {
                    foreach ($mockupFilesToSend as $mockupFileIndex => $mockupFile) {
                        if ($mockupFile->productMockupFile->file_url_original) {
                            $imagePath = $mockupFile->productMockupFile->file_url;
                            $productImage = [
                                'src' => $imagePath,
                            ];

                            //Assign Rutter Variant ID to Image
                            //Max 1 image allowed per variant
                            foreach ($variants as $variantIndex => $variant) {
                                foreach ($variant->mockupFiles as $variantMockupFile) {
                                    if ($variantMockupFile->productMockupFile->id === $mockupFile->productMockupFile->id) {
                                        if(empty($productArray['variants'][$variantIndex]['images'])) {
                                            $productArray['variants'][$variantIndex]['images'][] = $productImage;
                                        }
                                        else{
                                            $productArray['images'][] = $productImage;
                                        }

                                        $this->logger->debug("Assign Variant ID {$variant->id} to Mockup File ID {$mockupFile->productMockupFile->id}");
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                if($isMonogram){
                    foreach ($blankVariants as $blankVariant) {
                        if ($blankVariant->image) {
                            $imagePath = $blankVariant->image->file_url;
                            $productImage = [
                                'src' => $imagePath
                            ];

                            //Assign Rutter Variant ID to Image
                            //Max 1 image allowed per variant
                            foreach ($variants as $variantIndex => $variant) {
                                if(empty($productArray['variants'][$variantIndex]['images'])) {
                                    $productArray['variants'][$variantIndex]['images'][] = $productImage;
                                }
                                else{
                                    $productArray['images'][] = $productImage;
                                }
                                break;
                            }
                        }
                    }
                }

                //Max 6 images allowed per product
                if(!empty($productArray['images'])){
                    if(count($productArray['images']) > 6){
                        $productArray['images'] = array_slice($productArray['images'], 0, 6);
                    }
                }
                //Set default product image using the first variant image else unset
                else{
                    if(!empty($productArray['variants'])){
                        $productArray['images'] = $productArray['variants'][0]['images'];
                    }
                    else{
                        unset($productArray['images']);
                    }
                }

                $this->logger->debug("Rutter post product: " . json_encode($productArray));

                $this->response = $rutterApi->addProduct($productArray);
                $this->logger->info("---------- Create Product | HTTP ". $this->response['code'] ." ----------");

                if ($this->response['code'] !== 200) {
                    $this->logger->error("Failed to create product");
                    $this->logger->info("Sent Data: ". json_encode($productArray));
                    $this->logger->info("Response: " . json_encode($this->response));
                    return false;
                }

                $platformProduct = $this->response['data']->product;
                $platformVariants = $platformProduct->variants;

                //If success add product to platform_products_table and platform_variants_table
                $platformStoreProduct = $this->saveToDb($platformStore, $platformProduct, $platformVariants, $platformImages);

                return $platformStoreProduct;
                break;

            default:
                //Fail
                $this->logger->error("No platform defined");
                $this->fail();
                return false;
                break;
        }
    }

    /**
     * @param $platformStore
     * @param $product
     * @param $variants
     * @param mixed|null $images
     * @return mixed|null
     */
    public function saveToDb($platformStore, $product, $variants, $images = null)
    {
        $platformStoreProduct = new PlatformStoreProduct();
        $platformName = strtolower($platformStore->platform->name);

        switch ($platformName) {
            case 'shopify':
                $platformStoreProduct = ShopifyProductFormatter::formatForDb($product, $platformStore, [], $this->logger);
                $platformStoreProduct->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProduct->id,
                    'message' => "Product sent to $platformName Store $platformStore->name",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);

                ProductLog::create([
                    'product_id'=> $this->product->id,
                    'message' => "Product sent to $platformName store $platformStore->name",
                    'message_type' => ProductLog::MESSAGE_TYPE_INFO
                ]);

                $this->logger->info("Platform Store Product Created | ID: " . $platformStoreProduct->id);

                foreach ($product->getVariants() as $variantIndex => $shopifyVariant) {
                    $options = [
                        'platform_store_product_id' => $platformStoreProduct->id,
                        'product' => $product
                    ];
                    $platformStoreProductVariant = ShopifyVariantFormatter::formatForDb($shopifyVariant, $platformStore, $options, $this->logger);
                    $platformStoreProduct->variants()->save($platformStoreProductVariant);
                    $this->logger->info("Platform Store Product Variant Created | Data: " . $platformStoreProductVariant->toJson());
                }

                //save platform product variant mappings
                $this->logger->header("Save Mappings");
                foreach ($platformStoreProduct->variants as $variantIndex => $platformStoreProductVariant) {
                    $this->logger->subheader("Platform Store Product Variant ID {$platformStoreProductVariant->id}");

                    $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                    $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;

                    // match based on sku
                    $variantId = null;
                    foreach($this->product->variants as $productVariant){
                        // $this->logger->debug("pspv_sku: " . $platformStoreProductVariant->sku . ", pvbv_sku: " . $productVariant->blankVariant->sku);
                        if($platformStoreProductVariant->sku == $productVariant->blankVariant->sku){
                            // matches based on sku
                            $variantId = $productVariant->id;
                            break;
                        }
                    }
                    // fallback to variant index if all else failed
                    if(is_null($variantId)){
                        $variantId = $this->product->variants[$variantIndex]->id;
                        $this->logger->info("Could not find variant mapping for " . $platformStoreProductVariant->sku);
                    }

                    // $platformStoreProductVariantMapping->product_variant_id = $this->product->variants[$variantIndex]->id;
                    $platformStoreProductVariantMapping->product_variant_id = $variantId;

                    $platformStoreProductVariantMapping->save();
                    $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());
                }
                break;
            case 'etsy':
                $platformStoreProduct = EtsyProductFormatter::formatForDb($product, $platformStore);
                $platformStoreProduct->image = isset($images[0]) ? $images[0]->url_75x75 : null;
                $platformStoreProduct->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProduct->id,
                    'message' => "Product sent to $platformName Store $platformStore->name",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);

                ProductLog::create([
                    'product_id'=> $this->product->id,
                    'message' => "Product sent to $platformName store $platformStore->name",
                    'message_type' => ProductLog::MESSAGE_TYPE_INFO
                ]);

                $this->logger->info("Platform Store Product Created | ID: " . $platformStoreProduct->id);

                $this->logger->info("Platform Variants | Data: " . print_r($variants, true));
                $mockupImageColorMap = [];
                if ($variants && is_array($variants->getProducts())) {
                    foreach ($variants->getProducts() as $etsyProductIndex => $etsyProduct) {
                        $etsyProduct = new ListingProduct($etsyProduct);
                        $platformStoreProductVariant = EtsyVariantFormatter::formatForDb($etsyProduct);

                        $propertyValues = $etsyProduct->getPropertyValues();
                        if ($this->mockupFilesPerVariant > 0) {
                            $imageIndex = $this->mockupFilesPerVariant * $etsyProductIndex;
                            if (isset($images[$imageIndex])) {
                                //$platformStoreProductVariant->image = $images[$imageIndex]->url_75x75;
//                            foreach ($propertyValues as $propertyValue) {
//                                $propertyValue = new PropertyValue($propertyValue);
//                                if (stripos($propertyValue->getPropertyName(), 'color') !== false) {
//                                    $mockupImageColorMap[$propertyValue->getValues()[0]] = $images[$imageIndex]->url_75x75;
//                                }
//                            }
                            }
                        }

                        //Dont save Placeholder inactive variants
                        $offerings = $etsyProduct->getOfferings() ?? [];
                        if (!$offerings[0] || $offerings[0]->is_enabled) {
                            $platformStoreProduct->variants()->save($platformStoreProductVariant);
                            $this->logger->info("Platform Store Product Variant Created | Data: " . $platformStoreProductVariant->toJson());
                        }
                    }
                }

                $this->logger->header("Save Mappings");
                $placeholderVariantCount = 0;
                $this->logger->debug("Variants: " . json_encode($this->product->variants));
                foreach ($this->product->variants as $variant) {
                    $this->logger->subheader("Variant ID $variant->id");
                    $this->logger->debug("Variant Option Values: " . json_encode($variant->blankVariant->optionValues));
                    $this->logger->debug("Platform Store Product Variants: " . json_encode($platformStoreProduct->variants));

                    foreach ($platformStoreProduct->variants as $variantIndex => $platformStoreProductVariant) {
                        if (isset($this->etsySkuVariantIdMapping[$platformStoreProductVariant->sku]) && $this->etsySkuVariantIdMapping[$platformStoreProductVariant->sku] === $variant->id) {
                            $this->logger->subheader("Platform Store Product Variant ID {$platformStoreProductVariant->id}");
                            $this->logger->debug("Platform Store Product Variant: " . json_encode($platformStoreProductVariant));

                            //TODO This can be removed since we are filling only valid etsy sku variants
                            if (stripos($platformStoreProductVariant->sku, $this->etsyPlaceHolderSKU) !== false) {
                                $this->logger->debug("Skip Placeholder Variant SKU " . $platformStoreProductVariant->sku);
                                $placeholderVariantCount++;
                                continue;
                            }

                            $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                            $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;

                            $platformStoreProductVariantMapping->product_variant_id = $this->etsySkuVariantIdMapping[$platformStoreProductVariant->sku] ?? null;

                            $platformStoreProductVariantMapping->save();
                            $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());
                            break;
                        }
                    }
                }

                break;
            case 'rutter':
                $platformStoreProduct = RutterProductFormatter::formatForDb($product, $platformStore);
                $platformStoreProduct->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProduct->id,
                    'message' => "Product sent to Store $platformStore->name",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);

                ProductLog::create([
                    'product_id'=> $this->product->id,
                    'message' => "Product sent to store $platformStore->name",
                    'message_type' => ProductLog::MESSAGE_TYPE_INFO
                ]);

                $this->logger->info("Platform Store Product Created | ID: " . $platformStoreProduct->id);

                $this->logger->info("Platform Variants | Data: " . json_encode($variants));

                if ($variants) {
                    foreach ($variants as $variant) {
                        $platformStoreProductVariant = RutterVariantFormatter::formatForDb($variant);
                         $platformStoreProduct->variants()->save($platformStoreProductVariant);
                         $this->logger->info("Platform Store Product Variant Created | Data: " . json_encode($platformStoreProductVariant));
                        }
                    }

                //save platform product variant mappings
                $this->logger->header("Save Mappings");
                foreach ($platformStoreProduct->variants as $variantIndex => $platformStoreProductVariant) {
                    $this->logger->subheader("Platform Store Product Variant ID {$platformStoreProductVariant->id}");

                    $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                    $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;

                    // match based on sku
                    $variantId = null;
                    $matchedSKU = substr($platformStoreProductVariant->sku, 0, strrpos( $platformStoreProductVariant->sku, '_'));
                    foreach($this->product->variants as $productVariant){
                        if($matchedSKU == $productVariant->blankVariant->sku){
                            // matches based on sku
                            $variantId = $productVariant->id;
                            break;
                        }
                    }
                    // fallback to variant index if all else failed
                    if(is_null($variantId)){
                        $variantId = $this->product->variants[$variantIndex]->id;
                        $this->logger->info("Could not find variant mapping for " . $platformStoreProductVariant->sku);
                    }

                    $platformStoreProductVariantMapping->product_variant_id = $variantId;

                    $platformStoreProductVariantMapping->save();
                    $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());
                }

                break;
            case 'launch':
                $platformStoreProduct = LaunchProductFormatter::formatForDb($product, $platformStore, [], $this->logger);
                $platformStoreProduct->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProduct->id,
                    'message' => "Product sent to $platformName Store $platformStore->name",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);

                ProductLog::create([
                    'product_id'=> $this->product->id,
                    'message' => "Product sent to $platformName store $platformStore->name",
                    'message_type' => ProductLog::MESSAGE_TYPE_INFO
                ]);

                $this->logger->info("Platform Store Product Created | ID: " . $platformStoreProduct->id);

                foreach ($product['variants'] as $variantIndex => $launchVariant) {
                    $this->logger->info("Platform Store Product Variant Creation| ID: " . $launchVariant['id']);

                    $options = [
                        'platform_store_product_id' => $platformStoreProduct->id,
                        'product' => $product
                    ];
                    $platformStoreProductVariant = LaunchVariantFormatter::formatForDb($launchVariant, $platformStore, $options, $this->logger);
                    $platformStoreProduct->variants()->save($platformStoreProductVariant);
                    $this->logger->info("Platform Store Product Variant Created | Data: " . $platformStoreProductVariant->toJson());
                }

                //save platform product variant mappings
                $this->logger->header("Save Mappings");
                foreach ($platformStoreProduct->variants as $variantIndex => $platformStoreProductVariant) {
                    $this->logger->subheader("Platform Store Product Variant ID {$platformStoreProductVariant->id}");

                    $platformStoreProductVariantMapping = new PlatformStoreProductVariantMapping();
                    $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;

                    // match based on sku
                    $variantId = null;
                    foreach($this->product->variants as $productVariant){
                        // $this->logger->debug("pspv_sku: " . $platformStoreProductVariant->sku . ", pvbv_sku: " . $productVariant->blankVariant->sku);
                        if($platformStoreProductVariant->sku == $productVariant->blankVariant->sku){
                            // matches based on sku
                            $variantId = $productVariant->id;
                            break;
                        }
                    }
                    // fallback to variant index if all else failed
                    if(is_null($variantId)){
                        $variantId = $this->product->variants[$variantIndex]->id;
                        $this->logger->info("Could not find variant mapping for " . $platformStoreProductVariant->sku);
                    }

                    // $platformStoreProductVariantMapping->product_variant_id = $this->product->variants[$variantIndex]->id;
                    $platformStoreProductVariantMapping->product_variant_id = $variantId;

                    $platformStoreProductVariantMapping->save();
                    $this->logger->info("Platform Store Product Variant Mapping Created | Data: " . $platformStoreProductVariantMapping->toJson());
                }
                break;
                default:
                //Fail
                $this->fail(new Exception("Platform \"$platformName\" doesn't exist"));
                break;
        }

        return $platformStoreProduct;
    }
}
