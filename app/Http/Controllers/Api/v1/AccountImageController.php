<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Resources\Accounts\AccountImageCollectionResource;
use App\Http\Resources\Accounts\AccountImageResource;
use App\Models\Accounts\AccountImage;
use App\Models\ImageType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use SunriseIntegration\TeelaunchModels\Models\Blanks\ArtworkStage\BlankStageCreateTypeBlankStage;
use SunriseIntegration\TeelaunchModels\Utils\Uploads\ProcessUpload;

class AccountImageController extends Controller
{

    public function index(Request $request)
    {
        $paginate = (int) config('pagination.images_per_page');

        $request->validate([
            'blankStageId' => 'exists:blank_stages,id',
            'createTypeId' => 'exists:blank_stage_create_types,id'
        ]);

        $accountImages = AccountImage::filterBlankStageImageRequirements($request->blankStageId, $request->createTypeId)
            ->search($request->all())
            ->orderBy('created_at', 'desc')
            ->paginate($paginate);

        return new AccountImageCollectionResource($accountImages);
    }

    public function show(Request $request, $id)
    {
        $accountImage = AccountImage::find($id);
        if (!$accountImage) {
            return $this->responseNotFound();
        }

        return new AccountImageResource($accountImage);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => AccountImage::getImageRequirementsValidation($request->blankStageId, $request->createTypeId)
        ]);
        try {
            $imageType = ImageType::select('id')->where('mime_type', $request->file('image')->getMimeType())->first();

            $image = AccountImage::create([
                'account_id' => Auth::user()->account_id,
                'size' => $request->file('image')->getSize(),
                'image_type_id' => $imageType->id
            ]);

            $image->saveFileFromRequest($request->file('image'));

            return new AccountImageResource($image);

        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'image' => 'file'
        ]);

        $image = AccountImage::findOrFail($id);

        try {
            if ($request->hasFile('image') && $request->file('image')) {
                $imageType = ImageType::select('id')->where('mime_type', $request->file('image')->getMimeType())->first();

                $image->saveFileFromRequest($request->file('image'));

                $metadata = ProcessUpload::getFileMetadata($image->file_url_original);
                $image->size = $request->file('image')->getSize();
                $image->width = $metadata['width'];
                $image->height = $metadata['height'];
                $image->image_type_id = $imageType->id;
                $image->save();
            }
            return new AccountImageResource($image);
        } catch (ModelNotFoundException $e) {
            return $this->responseNotFound();
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $accountImage = AccountImage::findOrFail($id);
            if ($accountImage->deleteFileAndRemoveFromDB()) {
                return $this->responseOk();
            }
        } catch (ModelNotFoundException $e) {
            return $this->responseNotFound();
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return $this->responseServerError();
    }

}
