<?php

namespace App\Console\Commands;

use App\Models\Accounts\Account;
use App\Models\Accounts\AccountSettings;
use App\Models\Platforms\Platform;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;


class OrderDeskExportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orderdesk:export-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs order export for OrderDesk';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $loggerFactory = new CronLoggerFactory('orderdesk-export-orders-cron');
        $logger = $loggerFactory->getLogger();

        $logger->title('Start Exporting Orders');

        Account::where('active', true)->chunkById(10, function ($accounts) use ($logger) {
            foreach ($accounts as $account) {

                $ordersLastExported = AccountSettings::where('account_id', $account->id)->where('key', 'orders_last_exported')->first();
                if (!$this->needsSyncing($ordersLastExported, 60, $logger)) {
                    $logger->warning("OVERLAP - Exporting Orders is skipped for Account ID: {$account->id} | {$account->name}");
                    // continue;
                    // shutdown if we ever see an overlap as another instance is running.
                    $logger->warning("EXITING: Another instance seems to be running.");
                    return false;
                }

                $logger->info("Exporting Orders for Account ID: {$account->id} | {$account->name}");

                try {
                    $this->callSilent('orderdesk:account-export-orders', ['accountId' => $account->id]);
                } catch (\Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
        });

        $logger->title('Finished Exporting Orders');

    }

    private function needsSyncing($lastSync, $syncDelay, $logger)
    {
        $lastSync = $lastSync->value ?? '2017-01-01 01:01:01';

        if (!$syncDelay) {
            $logger->info('Sync Frequency: Disabled');
            return false;
        }

        $logger->info('Last Sync Time: ' . $lastSync . ' | ' . 'Sync Delay: ' . $syncDelay .'min');

        $lastSyncTime = Carbon::parse($lastSync);
        $timeNow = Carbon::now();

        $difference = $lastSyncTime->diffInMinutes($timeNow);
        if (($difference + 1) >= $syncDelay) {
            return true;
        } else {
            return false;
        }
    }
}
