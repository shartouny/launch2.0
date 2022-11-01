<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $allowedAccountPaths = [
        'mockup-files',
        'print-files',
        'stage-files',
        'branding-images',
        'art-files',
        'orders'
    ];

    function showImage($filePath)
    {
        $explodedPath = explode('/', $filePath);
        if (stripos($explodedPath[0], 'account') === 0) {
            if (!in_array($explodedPath[2], $this->allowedAccountPaths) && (!isset($explodedPath[4]) || !in_array($explodedPath[4], $this->allowedAccountPaths))) {
                return $this->responseNotFound();
            }
        }

        $cacheResponse = false;

        $oneMonth = 2592000;
        $sixMonths = 15552000;
        $secondsToCache = $sixMonths;
        $cacheControlValues = ['private', "max-age=$secondsToCache", 'must-revalidate'];

        try {
            $lastModifiedAt = Storage::disk('s3-nocache')->lastModified($filePath);
        } catch (\Exception $e) {
            return $this->responseNotFound();
        }

        $cacheResponse = false; //DO NOT USE REDIS FOR IMAGE CACHING
        if ($cacheResponse) {
            $cacheKey = $filePath . $lastModifiedAt;

            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            } else {
                return Cache::remember($cacheKey, $secondsToCache, function () use ($secondsToCache, $filePath, $cacheControlValues, $lastModifiedAt) {
                    return response(Storage::get($filePath))
                        ->header('Content-Type', 'image')
                        ->header('Cache-Control', $cacheControlValues)
                        ->header('ETag', $lastModifiedAt);
                });
            }
        } else {
            return response(Storage::get($filePath))
                ->header('Content-Type', 'image')
                ->header('Cache-Control', $cacheControlValues)
                ->header('ETag', $lastModifiedAt);
        }
    }
}
