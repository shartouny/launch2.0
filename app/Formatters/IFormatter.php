<?php
namespace App\Formatters;

Interface IFormatter
{
    static function formatForDb($platformData, $platformStore, $options, $logger = null);

    static function formatForPlatform($dbData, $platformStore, $options, $logger = null);
}
