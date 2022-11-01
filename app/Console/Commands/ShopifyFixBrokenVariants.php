<?php

namespace App\Console\Commands;

use App\Logger\CronLoggerFactory;
use FilesystemIterator;
use Illuminate\Console\Command;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;

class ShopifyFixBrokenVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fix-broken-variants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One time use script to fix broken platform store product variants forshopify products';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public $logChannel = 'shopify';

    public function handle()
    {
        $loggerFactory = new CronLoggerFactory('shopify');
        $logger = $loggerFactory->getLogger();

        $logger->info("******** Fix Shopify Platform Products  ********");
        $matches = [];
        $page = 1;
        while(true){
            $brokenVariantLinksPaginator = ShopifyHelper::getBrokenVariantLinks($page);
            $brokenVariantLinks = $brokenVariantLinksPaginator->items();
            if(count($brokenVariantLinks) == 0){
                break;
            }
            $logger->info("Page " . $page);
            $newMatches = ShopifyHelper::fixBrokenVariantLinks2($brokenVariantLinks);
            $matches = array_merge($matches, $newMatches);
            $page++;

        }

        // $brokenVariantLinks = ShopifyHelper::getBrokenVariantLinks();

        // $matches = ShopifyHelper::fixBrokenVariantLinks($brokenVariantLinks);

        $logger->info("********** FINISHED **********");
    }
}
