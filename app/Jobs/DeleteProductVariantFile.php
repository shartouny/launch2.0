<?php

namespace App\Jobs;

use SunriseIntegration\TeelaunchModels\Models\FileModel;

class DeleteProductVariantFile extends BaseJob
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
        $this->fileModel->deleteFile();
        //$this->fileModel->forceDelete();
    }
}
