<?php

namespace App\Console\Commands;

use App\Models\Accounts\Account;
use Illuminate\Console\Command;
use App\Logger\CronLoggerFactory;


class ProcessOrderShipments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platforms:process-order-shipments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs Order Shipment processing';

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
        $loggerFactory = new CronLoggerFactory('process-order-shipments-cron');
        $logger = $loggerFactory->getLogger();

        $logger->title('Start Processing Order Shipments');

        Account::where('active', true)->chunkById(100, function ($accounts) use ($logger) {
            foreach ($accounts as $account) {
                $logger->info("Processing Order Shipments for {$account->name} | ID: {$account->id}");

                try {
                    $this->callSilent('platforms:process-account-order-shipments', ['accountId' => $account->id]);
                } catch (\Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
        });

        $logger->title('Finished Processing Order Shipments');
    }
}
