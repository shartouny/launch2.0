<?php

namespace App\Console\Commands;

use App\Logger\CronLoggerFactory;
use Carbon\Carbon;
use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Cleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old logs and files';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    public $logChannel = 'cleanup';

    const DELETE_AFTER_DAYS = 14;

    public function handle()
    {
        $loggerFactory = new CronLoggerFactory('cleanup-cron');
        $logger = $loggerFactory->getLogger();

        $logger->info("******** START CLEANUP FOR " . self::DELETE_AFTER_DAYS . " days old FILES ********");

        $files = new RecursiveDirectoryIterator(storage_path('logs'));
        $files->setFlags(FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS);
        $files = new RecursiveIteratorIterator($files);

        $fileCount = 0;
        $deletedFiles = 0;

        foreach ($files as $file) {
            if ($file->isFile()) {
                $fileCount++;

                if ($this->canDelete($file)) {
                    $logger->info('Deleting file: ' . $file->getPathName());
                    unlink($file);
                    $deletedFiles++;
                }
            }
        }

        $logger->info("Total files found: $fileCount");
        $logger->info("Files deleted: $deletedFiles");

        $logger->info("********** FINISHED **********");
    }

    public function canDelete($file)
    {
        if ($file->getExtension() != 'log') {
            return false;
        }

        $now = Carbon::now();
        $lastModified = Carbon::createFromTimestamp($file->getMTime())->setTimezone('UTC');

        return $now->gt($lastModified->addDays(self::DELETE_AFTER_DAYS));
    }
}
