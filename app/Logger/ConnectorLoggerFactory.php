<?php

namespace App\Logger;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class ConnectorLoggerFactory
{
    protected $channel;
    protected $accountId;
    protected $connectorName;
    protected $processId;

    public function __construct($channel, $accountId, $connectorName = 'general', $processId = null)
    {
        $this->channel = $channel;
        $this->accountId = $accountId;
        $this->connectorName = strtolower($connectorName);
        $this->processId = $processId;
    }

    public function getLogger($baseDir = 'logs/accounts/')
    {
        if(!$this->processId) {
            $this->processId = str_random(5);
        }

        $dateString = Carbon::now()->format('m-d-Y');
        $filePath = $this->connectorName . '-' . $this->channel . '-';
        $filePath .= $dateString . '.log';

        $dateFormat = "m/d/Y H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        //Log stream
        $stream = new StreamHandler(storage_path($baseDir . $this->accountId . '/' . $this->connectorName . '/' . $filePath), Logger::DEBUG);
        $stream->setFormatter($formatter);

        //Alert stream
        $errorNotifyPath = storage_path($baseDir . $this->accountId . '/' . $this->connectorName . '/' . $this->channel . '-errors.log');
        if (file_exists($errorNotifyPath)) {
            file_put_contents($errorNotifyPath, null);
        }
        $errorStream = new StreamHandler($errorNotifyPath, Logger::ALERT, false);
        $errorStream->setFormatter($formatter);

        //Create logger and attach streams
        $connectorLogger = new CustomLogger($this->processId);
        $connectorLogger->pushHandler($stream);
        //$connectorLogger->pushHandler($errorStream);

        return $connectorLogger;
    }

}
