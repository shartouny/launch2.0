<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use Exception;

class FulfillOrder extends BaseJob
{
    protected $platformManager;

    protected $order;

    public $logChannel = 'fulfill-orders';

    public $logger;

    public function __construct($platformManager, $order, $currentLogger)
    {
        parent::__construct();
        $this->platformManager = $platformManager;
        $this->order = $order;

        if (!$currentLogger) {
            $loggerFactory = new ConnectorLoggerFactory(
                $this->logChannel,
                $this->order->account_id,
                'system');
            $currentLogger = $loggerFactory->getLogger();
        }

        $this->logger = $currentLogger;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $this->logger->title("-------------------- Start Fulfill Order --------------------");

        try {
            $this->platformManager->loadApi();
            $this->platformManager->fulfillOrder($this->order);
        } catch (Exception $e) {
            $this->logger->error($e);
            throw $e;
        }

        $this->logger->title("-------------------- End Fulfill Order --------------------");
    }

}
