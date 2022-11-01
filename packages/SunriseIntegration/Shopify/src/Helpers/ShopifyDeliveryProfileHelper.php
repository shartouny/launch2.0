<?php

namespace SunriseIntegration\Shopify\Helpers;

use SunriseIntegration\Shopify\API;
use App\Logger\CustomLogger;
use Exception;
use Illuminate\Support\Facades\DB;
use SunriseIntegration\Shopify\Helpers\ShopifyArrayGraphQL;
use App\Models\ShippingRules\ShippingRulesPlatformStore;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreSettings;

class ShopifyDeliveryProfileHelper
{
    /**
     * @var API
     */
    protected $api;

    /**
     * @var PlatformStore
     */
    protected $platformStore;

    /**
     * @var CustomLogger
     */
    protected $logger;

    /**
     * API constructor.
     * @param PlatformStore $platformStore
     * @param CustomLogger $logger
     */
    function __construct($platformStore, $logger)
    {
        $this->platformStore = $platformStore;
        $this->logger = $logger;
        $this->api = new API([
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'shop' => $this->platformStore->url
        ]);
        $this->api->setAccessToken($this->platformStore->api_token);
    }


    public function getPlatformDeliveryProfileIdForPlatformVariant($platformVariantId)
    {
        /*
        SELECT
            sr.name as name,
            srp.ship_price as price,
            cg.name as country_group_name
        FROM platform_store_product_variants as pspv
        INNER JOIN platform_store_product_variant_mappings pspvm on pspv.id = pspvm.platform_store_product_variant_id
        INNER JOIN product_variants pv on pspvm.product_variant_id = pv.id
        INNER JOIN shipping_rules_blank_variant srbv on pv.blank_variant_id = srbv.blank_variant_id
        INNER JOIN shipping_rules sr on srbv.shipping_rule_id = sr.id

        INNER JOIN shipping_rules_prices srp on sr.id = srp.shipping_rule_id

        INNER JOIN country_groups cg on srp.country_group_id = cg.id
        WHERE platform_variant_id = 39482040484036;
        */

        $query =  DB::table('platform_store_product_variants', 'pspv')
            ->join('platform_store_product_variant_mappings AS pspvm', 'pspv.id', '=', 'pspvm.platform_store_product_variant_id')
            ->join('product_variants AS pv', 'pspvm.product_variant_id', '=', 'pv.id')
            ->join('shipping_rules_blank_variant AS srbv', 'pv.blank_variant_id', '=', 'srbv.blank_variant_id')
            ->join('shipping_rules AS sr', 'srbv.shipping_rule_id', '=', 'sr.id')
            ->join('shipping_rules_platform_store AS srps', 'sr.id', '=', 'srps.shipping_rule_id')
            ->select(
                'srps.platform_rule_id AS platform_rule_id'
            )
            ->where([
                ['pspv.platform_variant_id', '=', (string) $platformVariantId],
                ['srps.platform_store_id', '=', $this->platformStore->id],
            ])
            ->first();
        if ($query) {
            return $query->platform_rule_id;
        } else {
            return null;
        }
    }

    public function addVariantsToDeliveryProfile(
        $shopifyDeliveryProfileId,
        $shopifyVariantIds
    ) {
        // make variant array
        $variantArr = [];

        foreach ($shopifyVariantIds as $variantId) {
            $variantArr[] = "gid://shopify/ProductVariant/" . $variantId;
        }

        $variantsString = json_encode($variantArr);
        $query = <<<EOD
        mutation {
            deliveryProfileUpdate (
                id: "gid://shopify/DeliveryProfile/$shopifyDeliveryProfileId",
                profile: {
                    variantsToAssociate: $variantsString,
                }
            )
            {
                profile {
                  id
                  name
                }
                userErrors {
                  field
                  message
                }
              }
        }
        EOD;

        $data = [
            "query" => $query
        ];
        $response = $this->api->graphQl($data);
        return $response;
    }

    public function updateDeliveryProfiles()
    {
        // TODO update changes to price and name for shopify store if different.
        // currently just checks db to see if new profiles are missing and adds them

        $missingRules = DB::table('shipping_rules', 'sr')
            ->select(
                'id',
                'name',
            )
            ->whereNotIn('id', function ($query) {
                $query->select('shipping_rule_id')
                    ->from('shipping_rules_platform_store')
                    ->where('platform_store_id', '=', $this->platformStore->id);
            })
            ->get();
        $responses = [];
        // populate associations
        foreach ($missingRules as $rule) {
            $profileName = $rule->name;
            $query = DB::table('shipping_rules', 'sr')
                ->join('shipping_rules_prices AS srp', 'sr.id', '=', 'srp.shipping_rule_id')
                ->join('country_groups AS cg', 'srp.country_group_id', '=', 'cg.id')
                ->select(
                    'cg.name AS country_group',
                    'srp.ship_price AS price',
                )
                ->where('sr.id', '=', $rule->id)
                ->get();

            // build the profile array
            $profile = [];
            foreach ($query as $item) {
                $profile[$item->country_group] = $item->price;
            }
            // send it
            $response = $this->createDeliveryProfile(
                $profileName,
                $profile
            );

            // check for taken name. Shopify will not allow 2 profiles with the same name (shouldn't happen but could)
            if (
                $response->data &&
                $response->data->deliveryProfileCreate &&
                $response->data->deliveryProfileCreate->userErrors &&
                count($response->data->deliveryProfileCreate->userErrors) > 0 &&
                $response->data->deliveryProfileCreate->userErrors[0]->message === "Name has already been taken"
            ) {
                // try again with random number on end to create unique name
                $response = $this->createDeliveryProfile(
                    $profileName . ' ' . rand(100, 900),
                    $profile
                );
            }

            if($response->data->deliveryProfileCreate->profile){
                // get the platform profile id from response. Remove graph id information
                $rawProfileId = $response->data->deliveryProfileCreate->profile->id;
                $profileIdArr = explode('/', $rawProfileId);
                $profileId = intval($profileIdArr[count($profileIdArr) - 1]);
                ShippingRulesPlatformStore::updateOrCreate([
                    "shipping_rule_id" => $rule->id,
                    "platform_store_id" => $this->platformStore->id,
                    "platform_rule_id" => $profileId
                ]);
                $responses[] = $response;
            } else {
                $this->logger->error("Error creating delivery profile: " . json_encode($response));
            }
        }
        return $responses;
    }

    public function createDeliveryProfile(
        $profileName,
        $profile
    ) {
        $locationId = PlatformStoreSettings::where([['platform_store_id', $this->platformStore->id], ['key', 'fulfillment_service_location_id']])->first()->value;

        $priceUS = isset($profile["United States"]) ? $profile["United States"] : null;
        $priceCA = isset($profile["Canada"]) ? $profile["Canada"] : null;
        $priceUK = isset($profile["United Kingdom"]) ? $profile["United Kingdom"] : null;
        $priceAU = isset($profile["Australia"]) ? $profile["Australia"] : null;
        $priceEU = isset($profile["EU"]) ? $profile["EU"] : null;
        $priceRW = isset($profile["Rest of the World"]) ? $profile["Rest of the World"] : null;
        $query = <<<EOD
        mutation {
            deliveryProfileCreate (
                profile: {
                name: "$profileName",
                variantsToAssociate: [
                ],
                locationGroupsToCreate: {
                    locations: [
                        "gid://shopify/Location/$locationId"
                    ],
                    zonesToCreate: [
        EOD;

        if (isset($profile["United States"])) {
            $query .= <<<EOD

            {
                name: "United States",
                countries: [
                {
                    code: US,
                    includeAllProvinces: true
                }
                ],
                methodDefinitionsToCreate: [
                {
                    name: "Standard"
                    active: true
                    rateDefinition: {
                        price: {
                            amount: $priceUS
                            currencyCode: USD
                        }
                    }
                }
                ]
            },
            EOD;
        }
        if (isset($profile["Canada"])) {
            $query .= <<<EOD
                    {
                        name: "Canada",
                        countries: [
                        {
                            code: CA,
                            includeAllProvinces: true
                        }
                        ],
                        methodDefinitionsToCreate: [
                        {
                            name: "Standard"
                            active: true
                            rateDefinition: {
                                price: {
                                    amount: $priceCA
                                    currencyCode: USD
                                }
                            }
                        }
                        ]
                    },
            EOD;
        }
        if (isset($profile["United Kingdom"])) {
            $query .= <<<EOD
                    {
                        name: "United Kingdom",
                        countries: [
                        {
                            code: GB,
                            includeAllProvinces: true
                        }
                        ],
                        methodDefinitionsToCreate: [
                        {
                            name: "Standard"
                            active: true
                            rateDefinition: {
                                price: {
                                    amount: $priceUK
                                    currencyCode: USD
                                }
                            }
                        }
                        ]
                    },
            EOD;
        }
        if (isset($profile["Australia"])) {
            $query .= <<<EOD
                    {
                        name: "Australia",
                        countries: [
                        {
                            code: AU,
                            includeAllProvinces: true
                        }
                        ],
                        methodDefinitionsToCreate: [
                        {
                            name: "Standard"
                            active: true
                            rateDefinition: {
                                price: {
                                    amount: $priceAU
                                    currencyCode: USD
                                }
                            }
                        }
                        ]
                    },
            EOD;
        }

        if (isset($profile["EU"])) {
            $query .= <<<EOD
                    {
                        name: "Europe",
                        countries: [
                            {
                                code: AX,
                                includeAllProvinces: true
                              },
                              {
                                  code: AL,
                                  includeAllProvinces: true
                              },
                              {
                                  code: AD,
                                  includeAllProvinces: true
                              },
                              {
                                  code: AM,
                                  includeAllProvinces: true
                              },
                              {
                                  code: AT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: BY,
                                  includeAllProvinces: true
                              },
                              {
                                  code: BE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: BA,
                                  includeAllProvinces: true
                              },
                              {
                                  code: BV,
                                  includeAllProvinces: true
                              },
                              {
                                  code: BG,
                                  includeAllProvinces: true
                              },
                              {
                                  code: HR,
                                  includeAllProvinces: true
                              },
                              {
                                  code: CY,
                                  includeAllProvinces: true
                              },
                              {
                                  code: CZ,
                                  includeAllProvinces: true
                              },
                              {
                                  code: DK,
                                  includeAllProvinces: true
                              },
                              {
                                  code: EE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: FO,
                                  includeAllProvinces: true
                              },
                              {
                                  code: FI,
                                  includeAllProvinces: true
                              },
                              {
                                  code: FR,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: DE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GI,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GR,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GL,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GP,
                                  includeAllProvinces: true
                              },
                              {
                                  code: GG,
                                  includeAllProvinces: true
                              },
                              {
                                  code: HU,
                                  includeAllProvinces: true
                              },
                              {
                                  code: IS,
                                  includeAllProvinces: true
                              },
                              {
                                  code: IE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: IM,
                                  includeAllProvinces: true
                              },
                              {
                                  code: IT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: JE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: XK,
                                  includeAllProvinces: true
                              },
                              {
                                  code: LV,
                                  includeAllProvinces: true
                              },
                              {
                                  code: LI,
                                  includeAllProvinces: true
                              },
                              {
                                  code: LT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: LU,
                                  includeAllProvinces: true
                              },
                              {
                                  code: MT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: YT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: MD,
                                  includeAllProvinces: true
                              },
                              {
                                  code: MC,
                                  includeAllProvinces: true
                              },
                              {
                                  code: ME,
                                  includeAllProvinces: true
                              },
                              {
                                  code: NL,
                                  includeAllProvinces: true
                              },
                              {
                                  code: MK,
                                  includeAllProvinces: true
                              },
                              {
                                  code: NO,
                                  includeAllProvinces: true
                              },
                              {
                                  code: PL,
                                  includeAllProvinces: true
                              },
                              {
                                  code: PT,
                                  includeAllProvinces: true
                              },
                              {
                                  code: RE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: RO,
                                  includeAllProvinces: true
                              },
                              {
                                  code: SM,
                                  includeAllProvinces: true
                              },
                              {
                                  code: RS,
                                  includeAllProvinces: true
                              },
                              {
                                  code: SK,
                                  includeAllProvinces: true
                              },
                              {
                                  code: SI,
                                  includeAllProvinces: true
                              },
                              {
                                  code: ES,
                                  includeAllProvinces: true
                              },
                              {
                                  code: SJ,
                                  includeAllProvinces: true
                              },
                              {
                                  code: SE,
                                  includeAllProvinces: true
                              },
                              {
                                  code: CH,
                                  includeAllProvinces: true
                              },
                              {
                                  code: TR,
                                  includeAllProvinces: true
                              },
                              {
                                  code: UA,
                                  includeAllProvinces: true
                              },
                              {
                                  code: VA,
                                  includeAllProvinces: true
                              }
                        ],
                        methodDefinitionsToCreate: [
                        {
                            name: "Standard"
                            active: true
                            rateDefinition: {
                                price: {
                                    amount: $priceEU
                                    currencyCode: USD
                                }
                            }
                        }
                        ]
                    },
            EOD;
        }

        if (isset($profile["Rest of the World"])) {
            $query .= <<<EOD
                    {
                        name: "Rest of the World",
                        countries: [
                            {
                                restOfWorld: true
                            }
                        ],
                        methodDefinitionsToCreate: [
                        {
                            name: "Standard"
                            active: true
                            rateDefinition: {
                                price: {
                                    amount: $priceRW
                                    currencyCode: USD
                                }
                            }
                        }
                        ]
                    },
            EOD;
        }

        $query .= <<<EOD
                    ]
                }
                }
            )
            {
                profile {
                  id
                  name
                }
                userErrors {
                  field
                  message
                }
              }
        }
        EOD;

        $data = [
            "query" => $query
        ];
        $response = $this->api->graphQl($data);
        return $response;
    }

    public function getExistingDeliveryProfiles()
    {
        $queryArr = [
            'shop' => [
                'features' => [
                    'deliveryProfiles'
                ]
            ],
            'deliverySettings' => [
                'legacyModeProfiles'
            ],
            "deliveryProfiles(first: 10)" => [
                'edges' => [
                    'node' => [
                        'id',
                        'name',
                        'default',
                        'productVariantsCountV2' => [
                            'capped',
                            'count'
                        ]
                    ]
                ]
            ]
        ];

        $query = ShopifyArrayGraphQL::convert($queryArr);
        $data = [
            "query" => $query,
            "variables" => [
                "first" => 10
            ]
        ];
        $response = $this->api->graphQl($data);
        try {
            $deliveryProfiles = $response->data->deliveryProfiles->edges;
            return $deliveryProfiles;
        } catch (Exception $e) {
            $response = null;
            $this->log->error($e->getMessage());
            return $response;
        }
    }
}
