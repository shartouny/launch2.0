<?php


namespace App\Jobs;

use SunriseIntegration\TeelaunchModels\Models\FileModel;

class DeleteOrderLineItemPrintFile extends BaseJob
{
    protected $fileModel;

    /**
     * Create a new job instance.
     *
     * @param FileModel $fileModel
     */
    public function __construct(FileModel $fileModel)
    {
        parent::__construct();
        $this->fileModel = $fileModel;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        // Delete fileModel instance using fileModel Models
        $this->fileModel->deleteFile();
    }
}
