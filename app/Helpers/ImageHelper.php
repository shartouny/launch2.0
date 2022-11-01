<?php


namespace App\Helpers;


use Intervention\Image\ImageManager;
use Intervention\Image\Image;
use App\Models\ImageCanvas;
use App\Models\ImageCanvasSubArtwork;
use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductArtFile;
use App\Logger\CustomLogger;
use Imagick;

class ImageHelper
{

    /**
     * @param Image $img
     * @param ImageManager $imageManager
     * @return Image
     * @throws Exception
     */
    public static function fixColorSpace(&$img, $imageManager = null)
    {
        if(empty($imageManager)){
            $imageManager = new ImageManager(array('driver' => 'imagick'));
        }
        $imagickObject = $img->getCore();
        if ($imagickObject->getImageColorspace() == \Imagick::COLORSPACE_CMYK) {
            $imagickObject->transformimagecolorspace(\Imagick::COLORSPACE_SRGB);
            return $imageManager->make($imagickObject);
        } else {
            return $img;
        }
    }

     /**
     * @param Image $image
     * @param ImageCanvasSubArtwork $imageCanvasSubArtwork
     * @param CustomLogger $logger
     * @return Image
     */
    public static function resizeImage($image, $imageCanvasSubArtwork, $logger = null, $useConstraints = true)
    {
        $resizeImage = false;
        $imageCanvasSubArtworkWidth = (int)$imageCanvasSubArtwork->width;
        $imageCanvasSubArtworkMaxWidth = (int)$imageCanvasSubArtwork->max_width ?? 0;
        $imageCanvasSubArtworkHeight = (int)$imageCanvasSubArtwork->height;
        $imageCanvasSubArtworkMaxHeight = (int)$imageCanvasSubArtwork->max_height ?? 0;

        if ($imageCanvasSubArtworkWidth > 0 && $imageCanvasSubArtworkHeight > 0) {
            $resizeImage = true;
        }
        else if($imageCanvasSubArtworkMaxWidth > 0 || $imageCanvasSubArtworkMaxHeight > 0){
            if($imageCanvasSubArtworkMaxWidth > 0 && $image->width() > $imageCanvasSubArtworkWidth && $image->width() > $imageCanvasSubArtworkMaxWidth) {
                $imageCanvasSubArtworkWidth = $imageCanvasSubArtworkMaxWidth;
                $resizeImage = true;
            }
            elseif($imageCanvasSubArtworkMaxWidth > 0 && $image->width() > $imageCanvasSubArtworkWidth && $image->width() < $imageCanvasSubArtworkMaxWidth) {
                $imageCanvasSubArtworkWidth = $image->width();
                $resizeImage = true;
            }

            if($imageCanvasSubArtworkMaxHeight > 0 && $image->height() > $imageCanvasSubArtworkHeight && $image->height() > $imageCanvasSubArtworkMaxHeight) {
                $imageCanvasSubArtworkHeight = $imageCanvasSubArtworkMaxHeight;
                $resizeImage = true;
            }
            elseif($imageCanvasSubArtworkMaxHeight > 0 && $image->height() > $imageCanvasSubArtworkHeight && $image->height() < $imageCanvasSubArtworkMaxHeight) {
                $imageCanvasSubArtworkHeight = $image->height();
                $resizeImage = true;
            }
        }

        if($resizeImage){
            if($logger){
                $logger->info("Resize image to $imageCanvasSubArtworkWidth x $imageCanvasSubArtworkHeight");
            }
            if($useConstraints) {
                $image->resize($imageCanvasSubArtworkWidth, $imageCanvasSubArtworkHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            } else {
                $image->resize($imageCanvasSubArtworkWidth, $imageCanvasSubArtworkHeight, function ($constraint) {
                });
            }
            return $image;
        }
        if($logger){
            $logger->info("No Resize necessary");
        }
        return $image;

    }

    /**
     * @param Image $finalImage
     * @param ImageCanvas $imageCanvas
     * @param ProductPrintFile $productPrintFile
     * @param CustomLogger $logger
     * @return Image
     * @throws Exception
     */
    public static function processImageCanvasSubArtwork (
        &$finalImage, 
        &$imageCanvas, 
        &$productPrintFile,
        $logger,
        $options = []
    ) {
        $imageManager = new ImageManager(array('driver' => 'imagick'));

        // cache to store loaded images
        $cache = [];

        foreach ($imageCanvas->imageCanvasSubArtwork as $imageCanvasSubArtwork) {
            $logger->header("Process ImageCanvasSubArtwork | ID: {$imageCanvasSubArtwork->id}");

            $logger->debug('imageCanvasSubArtwork:' . json_encode($imageCanvasSubArtwork));

            //$artFile = $this->productPrintFile->productArtFile;

            $artFile = ProductArtFile::where([['account_id',$productPrintFile->account_id],['product_id',$productPrintFile->product_id],['blank_stage_id',$imageCanvasSubArtwork->blank_stage_id]])->first();
            $logger->debug('artFile:' . json_encode($artFile));

            if(!$artFile){
                $logger->warning('No art file found, skipping image');
                continue;
            }

            $logger->debug("Layer file URL: $artFile->file_url_original");
            
            // use cache if exists
            $cache_url = explode('?', $artFile->file_url_original)[0];
            if(isset($cache[$cache_url])){
                // reuse the cached canvas
                $image = $cache[$cache_url];
                $image->reset();
                $logger->debug("Reusing cache image");
            } else {
                // download the image and store it in the cache
                $image = $imageManager->make($artFile->file_url_original);
                $image->backup();
                $cache[$cache_url] = $image;
                $logger->debug("Storing cached image");
            }

            $image = self::fixColorSpace($image);

            // if ($this->isApparel) {
            if (isset($options['isApparel']) && $options['isApparel']) {
                $logger->debug("Trim image for apparel");
                $image->trim();
            }

            $image = self::resizeImage($image, $imageCanvasSubArtwork, $logger, false);


            //Rotate and flip image
            if ($imageCanvasSubArtwork->is_flip_horizontal) {
                $logger->debug("Flip Horizontal");
                $image->flip('h');
            }

            if ($imageCanvasSubArtwork->is_flip_vertical) {
                $logger->debug("Flip Vertical");
                $image->flip('v');
            }

            if (!empty($imageCanvasSubArtwork->rotate_degrees)) {
                $logger->debug("Rotate $imageCanvasSubArtwork->rotate_degrees degrees");
                $image->rotate($imageCanvasSubArtwork->rotate_degrees);
            }

            //Check overlay_blank_image_id on imageCanvasSubArtwork
            if ($imageCanvasSubArtwork->blankImageOverlay) {
                //TODO: Paste overlay?

            }

            $logger->info("Insert image | Left: $imageCanvasSubArtwork->left | Top: $imageCanvasSubArtwork->top");
            $finalImage->insert($image, 'top-left', $imageCanvasSubArtwork->left, $imageCanvasSubArtwork->top);

        }
        return $finalImage;
    }
}
