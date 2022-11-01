<?php

namespace SunriseIntegration\Etsy\Helpers;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use SunriseIntegration\Etsy\API as EtsyAPI;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore;
use SunriseIntegration\TeelaunchModels\Utils\Logger;

class EtsyHelper
{
    /**
     * @param PlatformStore $platformStore
     * @param Logger $logger
     * @return int
     * @throws \OAuthException
     * @throws \RateLimitException
     */
    static function createShippingTemplate($platformStore, $logger)
    {
        $shippingTemplateId = null;
        $shippingTemplateTitle = "Standard Shipping (teelaunch)";

        //Create shipping profile
        $shippingTemplateData = [
            "title" => $shippingTemplateTitle,
            "origin_country_id" => 209, //US country id
            "destination_country_id" => 209, // destination_country_id 209 us, 79 canada, 0 everywhere else
            "primary_cost" => 5.00,
            "secondary_cost" => 0.75,
            "destination_region_id" => 0, //All countries
            "min_processing_days" => 7,
            "max_processing_days" => 8
        ];

        $etsyApi = new EtsyAPI(config('etsy.api_key'), config('etsy.api_secret'), $platformStore->api_token, $platformStore->api_secret, $logger);

        try {
            $response = $etsyApi->createShippingTemplate($shippingTemplateData);
            $logger->info("Create Shipping Profile | HTTP $etsyApi->lastHttpCode");
            $logger->debug("Response is type " . gettype($response));
            $logger->info("Response: " . json_encode($response));

            $shippingTemplateId = null;
            if (is_object($response)) {
                $shippingTemplateId = $response->results[0]->shipping_template_id;
            }

            if (is_string($response) && strpos($response, 'already exists') !== false) {
                $logger->info("Shipping template already exists in Etsy, attempting to retrieve it");
                $offset = 0;
                do {
                    $response = $etsyApi->getShippingTemplates(100, $offset);
                    $offset = $etsyApi->pagination->getNextOffset();
                    if ($response->results) {
                        foreach ($response->results as $shippingTemplate) {
                            if ($shippingTemplate->title == $shippingTemplateTitle) {
                                $shippingTemplateId = $shippingTemplate->shipping_template_id;
                            }
                        }
                    }
                } while (!$shippingTemplateId && $offset);
            }

            //Save to DB
            if ($shippingTemplateId) {
                $platformStore->settings()->updateOrCreate([
                    'key' => 'shipping_template_id'
                ],
                [
                    'value' => $shippingTemplateId
                ]);
            }
        } catch (\Exception $e) {
            $logger->error($e);
            return null;
        }

        if (!$shippingTemplateId) {
            return null;
        }

        $entriesToCreate = [
            [
                'destination_country_id' => 209, //US
                'primary_cost' => 5.00,
                'secondary_cost' => 0.75
            ],
            [
                'destination_country_id' => 79, //Canada
                'primary_cost' => 8.00,
                'secondary_cost' => 1.00
            ],
            [
                'destination_country_id' => 0, //Rest of the world
                'primary_cost' => 10.00,
                'secondary_cost' => 1.00
            ]
        ];
        foreach ($entriesToCreate as $entry) {
            $dataToPost = [
                "shipping_template_id" => $shippingTemplateId,
                "origin_country_id" => 209,
                "destination_country_id" => $entry['destination_country_id'],
                "primary_cost" => $entry['primary_cost'],
                "secondary_cost" => $entry['secondary_cost'],
                "min_processing_days" => 7,
                "max_processing_days" => 8
            ];
            $etsyApi->createShippingTemplateEntry($dataToPost);
        }

        return $shippingTemplateId;
    }

    static function convertCountryIdToISO($countryId)
    {
        $countries = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "countries.json"));
        foreach ($countries->results as $country) {
            if ($countryId == $country->country_id) {
                return $country->iso_country_code;
            }
        }
        return null;
    }

    static function cleanCarrier($carrier): string
    {
        // convert carrier to an etsy supported carrier
        // https://www.etsy.com/developers/documentation/getting_started/seller_tools#section_supported_carriers
        $carrierMap = [
            // list form etsy
            "4PX Worldwide Express" => "4px",
            "ABF Freight" => "abf",
            "ACS Courier" => "acscourier",
            "APC Postal Logistics" => "apc",
            "AeroFlash" => "aeroflash",
            "Afghan Post" => "afghan-post",
            "Amazon Logistics UK" => "amazon-uk-api",
            "Amazon Logistics US" => "amazon",
            "An Post" => "an-post",
            "Anguilla Postal Service" => "anguilla-post",
            "Aramex" => "aramex",
            "Asendia UK" => "asendia-uk",
            "Asendia USA" => "asendia-usa",
            "Australia Post" => "australia-post",
            "Austrian Post" => "austrian-post",
            "Austrian Post Registered" => "austrian-post-registered",
            "BH Posta" => "bh-posta",
            "Bahrain Post" => "bahrain-post",
            "Bangladesh Post Office" => "bangladesh-post",
            "Belgium Post Domestic" => "bpost",
            "Belgium Post International" => "bpost-international",
            "Belposhta" => "belpost",
            "Blue Dart" => "bluedart",
            "BotswanaPost" => "botswanapost",
            "Brunei Postal Services" => "brunei-post",
            "Bulgarian Posts" => "bgpost",
            "Cambodia Post" => "cambodia-post",
            "Canada Post" => "canada-post",
            "Canpar Courier" => "canpar",
            "Ceska Posta" => "ceska-posta",
            "China EMS" => "china-ems",
            "China Post" => "china-post",
            "Chit Chats" => "chitchats",
            "Chronopost France" => "chronopost-france",
            "Chronopost Portugal" => "chronopost-portugal",
            "Chunghwa Post" => "taiwan-post",
            "City Link" => "city-link",
            "Colissimo" => "colissimo",
            "Collect+" => "collectplus",
            "Correios de Brasil" => "brazil-correios",
            "Correios de Macau" => "correios-macau",
            "Correios de Portugal (CTT)" => "portugal-ctt",
            "Correo Argentino Domestic" => "correo-argentino",
            "Correo Argentino International" => "correo-argentino-intl",
            "Correo Uruguayo" => "correo-uruguayo",
            "Correos - Espana" => "spain-correos-es",
            "Correos Chile" => "correos-chile",
            "Correos De Mexico" => "correos-de-mexico",
            "Correos de Costa Rica" => "correos-de-costa-rica",
            "Correos del Ecuador" => "correos-ecuador",
            "Courier Post" => "courierpost",
            "Couriers Please" => "couriers-please",
            "Cyprus Post" => "cyprus-post",
            "DHL Benelux" => "dhl-benelux",
            "DHL Express" => "dhl",
            "DHL Germany" => "dhl-germany",
            "DHL Global Mail" => "dhl-global-mail",
            "DHL Netherlands" => "dhl-nl",
            "DHL Parcel NL" => "dhlparcel-nl",
            "DHL Polska" => "dhl-poland",
            "DHL Spain Domestic" => "dhl-es",
            // "DHL eCommerce" => "dhl-global-mail-asia", // wrong for us
            "DPD" => "dpd",
            "DPD Germany" => "dpd-de",
            "DPD Polska" => "dpd-poland",
            "DPD UK" => "dpd-uk",
            "DTDC India" => "dtdc",
            "Deltec Courier" => "deltec-courier",
            "Deutsche Post" => "deutsch-post",
            "Direct Link" => "directlink",
            "EC-Firstclass" => "ec-firstclass",
            "Egypt Post" => "egypt-post",
            "El Correo" => "el-correo",
            "Elta Courier" => "elta-courier",
            "Empost" => "emirates-post",
            "Empresa de Correos de Bolivia" => "correos-bolivia",
            "Estafeta" => "estafeta",
            "Estes" => "estes",
            "Estonian Post" => "estonian-post",
            "Ethiopian Postal Service" => "ethiopian-post",
            "Evergreen" => "evergreen",
            "Fastway Australia" => "fastway-au",
            "Fastway Couriers" => "fastway-ireland",
            "Fastway New Zealand" => "fastway-nz",
            "Fastways Couriers South Africa" => "fastway-za",
            "FedEx" => "fedex",
            "Fedex UK (Domestic)" => "fedex-uk",
            "First Flight Couriers" => "first-flight",
            "Flash Courier" => "flash-courier",
            "Freightquote by C. H. Robinson" => "freightquote",
            "GATI-KWE" => "gati-kwe",
            "GLS" => "gls",
            "Ghana Post" => "ghana-post",
            "Globegistics" => "globegistics",
            "Greyhound" => "greyhound",
            "Guernsey Post" => "guernsey-post",
            "Hay Post" => "hay-post",
            "Hellenic Post" => "hellenic-post",
            "Hermes" => "hermes-de",
            "Hermes Italy" => "hermes-it",
            "Hermes UK" => "hermes",
            "Hong Kong Post" => "hong-kong-post",
            "Hrvatska Posta" => "hrvatska-posta",
            "India Post" => "india-post",
            "India Post International" => "india-post-int",
            "Interlink Express" => "interlink-express",
            "International Seur" => "international-seur",
            "Ipostel" => "ipostel",
            "Iran Post" => "iran-post",
            "Islandspostur" => "islandspostur",
            "Isle of Man Post Office" => "isle-of-man-post",
            "Israel Post" => "israel-post",
            "Israel Post Domestic" => "israel-post-domestic",
            "Jamaica Post" => "jamaica-post",
            "Japan Post" => "japan-post",
            "Jersey Post" => "jersey-post",
            "Jordan Post" => "jordan-post",
            "Kazpost" => "kazpost",
            "Korea Post" => "kpost",
            "Korea Post EMS" => "korea-post",
            "Kuehne + Nagel" => "kn",
            "La Poste" => "la-poste-colissimo",
            "La Poste Monaco" => "poste-monaco",
            "La Poste Tunisienne" => "poste-tunisienne",
            "La Poste du Senegal" => "poste-senegal",
            "Landmark Global" => "landmark-global",
            "LaserShip" => "lasership",
            "Latvijas Pasts" => "latvijas-pasts",
            "LibanPost" => "libanpost",
            "Lietuvos Pastas" => "lietuvos-pastas",
            "MRW" => "mrw-spain",
            "Magyar Posta" => "magyar-posta",
            "Makedonska Posta" => "makedonska-posta",
            "Malaysia Pos Daftar" => "malaysia-post-posdaftar",
            "Maldives Post" => "maldives-post",
            "MaltaPost" => "maltapost",
            "Mauritius Post" => "mauritius-post",
            "Mondial Relay" => "mondialrelay",
            "Multipack" => "mexico-multipack",
            "Nacex" => "nacex-spain",
            "New Zealand Post" => "new-zealand-post",
            "Nexive" => "tntpost-it",
            "Nieuwe Post Nederlandse Antillen (PNA)" => "nieuwe-post-nederlandse-antillen-pna",
            "Nigerian Postal Service" => "nipost",
            "Nova Poshta" => "nova-poshta",
            "OCA" => "oca-ar",
            "OPEK" => "opek",
            "OPT" => "opt",
            "OPT de Nouvelle-Caledonie" => "opt-nouvelle-caledonie",
            "Oman Post" => "oman-post",
            "OnTrac" => "ontrac",
            "PTT Posta" => "ptt-posta",
            "Pakistan Post" => "pakistan-post",
            "Parcelforce Worldwide" => "parcel-force",
            "Poczta Polska" => "poczta-polska",
            "Pos Indonesia" => "pos-indonesia",
            "Pos Indonesia International" => "pos-indonesia-int",
            "Pos Malaysia" => "malaysia-post",
            "Post Aruba" => "post-aruba",
            "Post Fiji" => "post-fiji",
            "Post Luxembourg" => "post-luxembourg",
            "PostNL Domestic" => "postnl",
            "PostNL International" => "postnl-international",
            "PostNL International 3S" => "postnl-3s",
            "PostNord" => "danmark-post",
            "PostNord Logistics" => "postnord",
            "Posta" => "posta",
            "Posta Kenya" => "posta-kenya",
            "Posta Moldovei" => "posta-moldovei",
            "Posta Romana" => "posta-romana",
            "Posta Shqiptare" => "posta-shqiptare",
            "Posta Slovenije" => "posta-slovenije",
            "Posta Srbije" => "posta-srbije",
            "Posta Uganda" => "posta-uganda",
            "Poste Italiane" => "poste-italiane",
            "Poste Italiane Paccocelere" => "poste-italiane-paccocelere",
            "Poste Maroc" => "poste-maroc",
            "Posten AB" => "sweden-posten",
            "Posten Norge" => "posten-norge",
            "Posti" => "posti",
            "Postmates" => "postmates",
            "Purolator" => "purolator",
            "Qatar Post" => "qatar-post",
            "RL Carriers" => "rl-carriers",
            "RPX Indonesia" => "rpx",
            "Red Express" => "red-express",
            "Redpack" => "mexico-redpack",
            "Royal Mail" => "royal-mail",
            "Russian Post" => "russian-post",
            "S.F International" => "sfb2c",
            "SDA Express Courier" => "italy-sda",
            "SEUR Espana (Domestico)" => "spanish-seur",
            "SEUR Portugal (Domestico)" => "portugal-seur",
            "SF Express" => "sf-express",
            "Safexpress" => "safexpress",
            "Sagawa" => "sagawa",
            "Saudi Post" => "saudi-post",
            "Selektvracht" => "selektvracht",
            "Senda Express" => "mexico-senda-express",
            "Sendle" => "sendle",
            "Serpost" => "serpost",
            "Singapore Post" => "singapore-post",
            "Singapore SpeedPost" => "singapore-speedpost",
            "Siodemka" => "siodemka",
            "SkyNet Wordwide Express" => "skynetworldwide",
            "Skynet Malaysia" => "skynet-malaysia",
            "Skynet Worldwide Express" => "skynetworldwide",
            "Slovenska posta" => "slovenska-posta",
            "South Africa Post Office" => "sapo",
            "Stallion Express" => "stallionexpress",
            "StarTrack" => "star-track",
            "Swiss Post" => "swiss-post",
            "TA-Q-BIN Hong Kong" => "taqbin-hk",
            "TA-Q-BIN Japan" => "taqbin-jp",
            "TA-Q-BIN Malaysia" => "taqbin-my",
            "TA-Q-BIN Singapore" => "taqbin-sg",
            "TGX" => "tgx",
            "TNT" => "tnt",
            "TNT Australia" => "tnt-au",
            "TNT France" => "tnt-fr",
            "TNT Italia" => "tnt-it",
            "TNT UK" => "tnt-uk",
            "TTPost" => "ttpost",
            "Thailand Post" => "thailand-post",
            "Toll Global Express" => "toll-global-express",
            "Toll Priority" => "toll-priority",
            "UK Mail" => "uk-mail",
            "UPS" => "ups",
            "UPS Freight" => "ups-freight",
            "USPS" => "usps",
            "UkrPoshta" => "ukrposhta",
            "Vanuatu Post" => "vanuatu-post",
            "Vietnam Post" => "vnpost",
            "Vietnam Post EMS" => "vnpost-ems",
            "Whistl" => "whistl",
            "Xend" => "xend",
            "YRC Freight" => "yrc",
            "Yakit" => "yakit",
            "Yanwen" => "yanwen",
            "Yemen Post" => "yemen-post",
            "Yodel" => "yodel",
            "Yodel International" => "yodel-international",
            "Zampost" => "zampost",
            "Zimpost" => "zimpost",
            "i-parcel" => "i-parcel",
            "myHermes UK" => "myhermes-uk",
            "uShip" => "uship",

            // teelaunch custom
            "Asendia" => "asendia-usa",
            "asendia" => "asendia-usa",
            "DHL eCommerce" => "dhl-global-mail",
            "dhl_global_mail" => "dhl-global-mail",
            "endicia" => "usps",
            "FEDEX" => "fedex",
            "UPSMailInnovations" => "ups",
            "ups" => "ups",
            "UPS MI" => "ups",
            "DHL Global" => "dhl-global-mail",
            "RoyalMail" => "royal-mail",
            "Fastway" => "fastway-au"

        ];
        // return mapped etsy carrier if it exist in the list
        if (array_key_exists($carrier, $carrierMap)) {
            return $carrierMap[$carrier];
        }
        //TODO handle unknowns, such as
        // Spring https://mailingtechnology.com/tracking/?tn=N90000730148
        // newgistics http://tracking.smartlabel.com/default.aspx?trackingvalue=9261292700541303426868
        // RMSC untracked

        // Cannot find a carrier clean the tracking information and submit anyway
        return strtolower(str_replace(' ', '-', $carrier));
    }
}
