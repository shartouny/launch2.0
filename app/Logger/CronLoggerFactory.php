<?php

namespace App\Logger;

use Carbon\Carbon;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class CronLoggerFactory
{
    protected $channel;

    public function __construct($channel)
    {
        $this->channel = $channel;
    }

    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    public function getLogger($folderPath = 'logs/crons/')
    {
        $dateString = Carbon::now()->format('m_d_Y');

        $filePath = $this->channel . '_';
        $filePath .= $dateString. '.log';

        $dateFormat = "m/d/Y H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message%\n";
        $formatter = new LineFormatter($output, $dateFormat);


        $stream = new StreamHandler(storage_path($folderPath . $filePath), Logger::DEBUG);
        $stream->setFormatter($formatter);

        $processId = str_random(5);
        $cronLogger = new CustomLogger($processId);
        $cronLogger->pushHandler($stream);

        return $cronLogger;
    }

}
