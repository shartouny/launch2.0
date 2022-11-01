<?php

namespace App\Jobs;

use Error;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SunriseIntegration\TeelaunchModels\Utils\Logger;

class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $processId;

    /**
     * @var int
     */
    public $tries = 3;
    /**
     * @var int
     */
    public $timeout = 300;
    /**
     * @var bool
     */
    public $deleteWhenMissingModels = true;
    /**
     * @var Logger
     */
    public $logger;
    /**
     * @var string
     */
    public $logChannel = 'jobs';

    /**
     * The maximum number of exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    public function __construct()
    {
        $this->logger = new Logger($this->logChannel);
        if(!$this->processId){
            $this->processId = str_random(5);
        }
    }

    /**
     * @param Exception|Error $e
     */
    public function failed($e){
        $this->logger->error($e->getMessage());

        if (app()->bound('sentry')) {
            app('sentry')->captureException($e);
        }
    }
}
