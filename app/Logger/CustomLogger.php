<?php

namespace App\Logger;

use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\Log;
use Monolog\Logger;

class CustomLogger extends Logger
{
    protected $logLevel;

    public function __construct(string $name, array $handlers = [], array $processors = [], ?DateTimeZone $timezone = null)
    {
        parent::__construct($name, $handlers, $processors, $timezone);
        $this->logLevel = array_search(strtoupper(config('logging.log_level')), self::$levels);
    }

    public function allowLog($writeLevel)
    {
        return $writeLevel >= $this->logLevel;
    }

    /**
     * @param string $message
     */
    public function title(string $message)
    {
        $divider = str_repeat('*', 30);
        $this->info("$divider $message $divider");
    }

    /**
     * @param string $message
     */
    public function header(string $message)
    {
        $divider = str_repeat('=', 25);
        $this->info("$divider $message $divider");
    }

    /**
     * @param string $message
     */
    public function subheader(string $message)
    {
        $divider = str_repeat('-', 25);
        $this->info("$divider $message $divider");
    }

    /**
     * @param string $message
     */
    public function subSubheader(string $message)
    {
        $divider = str_repeat('~', 25);
        $this->info("$divider $message $divider");
    }

    public function info($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "INFO: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::INFO)) {
                parent::info($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }

    public function error($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "ERROR: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::ERROR)) {
                parent::error($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }

    public function warning($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "WARNING: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::WARNING)) {
                parent::warning($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }

    public function debug($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "DEBUG: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::DEBUG)) {
                parent::debug($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }

    public function critical($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "CRITICAL: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::CRITICAL)) {
                parent::critical($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }

    public function emergency($message, array $context = []): void
    {
        try {
            if (config('app.env') === 'local' || config('app.debug') == true) {
                echo "EMERGENCY: " . $message . PHP_EOL;
            }
            if ($this->allowLog(self::EMERGENCY)) {
                parent::emergency($message, $context);
            }
        } catch (Exception $e) {
            Log::critical($e);
        }
    }
}
