<?php


namespace App\Traits;


use Illuminate\Database\Eloquent\Collection;

trait MockupProcessing
{
    public function getUniqueMockups($blanks, $variants, $imageCountLimit): array
    {
        $imageCount = 0;
        $usedMockupFileUrls = [];
        $mockupFileOptionValues = [];
        $mockupFilesToSend = [];
        $useStyleOption = $blanks->count() > 1;

        foreach ($variants as $variantIndex => $variant) {

            $blankOptions = new Collection();
            foreach ($blanks as $blank) {
                foreach ($blank->options as $blankOption) {
                    $blankOptions->push($blankOption);
                }
            }
            $blankOptions = $blankOptions->unique();
            $this->logger->debug("Blank Options:" . json_encode($blankOptions));

            $hasColorOption = false;
            foreach ($blankOptions as $option) {
                if (stripos($option->name, 'color') !== false) {
                    $hasColorOption = true;
                }
            }

            //Build images array
            $mockupFiles = $variant->mockupFiles;
            if (!$this->mockupFilesPerVariant) {
                $this->mockupFilesPerVariant = count($mockupFiles);
            }

            //Get picked mockups options if any
            $pickedMockups = $variant->product->picked_mockups;

            $this->logger->debug("useStyleOption? " . ($useStyleOption ? 'true' : 'false'));
            $useMockupOptions = ['style', 'color'];
            $usedMockupOptions = [];
            if (count($variant->blankVariant->optionValues) > 0) {
                $this->logger->info("Blank Variant Option Values: " . json_encode($variant->blankVariant->optionValues));
                //If option values, only send 1 of each color and style image if color option exists, else send all
                foreach ($variant->blankVariant->optionValues as $blankVariantOptionValue) {
                    $blankVariantOptionName = strtolower($blankVariantOptionValue->option->name);

                    //Only send style and color variations if color is an option, otherwise send all
                    $useImage = false;
                    foreach ($useMockupOptions as $useMockupOption) {
                        if (stripos($blankVariantOptionName, $useMockupOption) !== false) {
                            $this->logger->debug("Use image based off option $useMockupOption");
                            $useImage = true;
                            break;
                        }
                    }

                    //Styles need to be represented in images
                    if ($useStyleOption) {
                        $blankStyleOptionValueHash = $variant->blankVariant->blank->name . $blankVariantOptionName;
                        $this->logger->debug("blankStyleOptionValueHash: " . json_encode($blankStyleOptionValueHash));
                        if (!in_array($blankStyleOptionValueHash, $usedMockupOptions)) {
                            $usedMockupOptions[] = $blankStyleOptionValueHash;
                            $this->logger->debug("Use image based off blankStyleOptionValueHash not used");
                            $useImage = true;
                        }
                    }

                    if ($useImage || in_array($blankVariantOptionName, $useMockupOptions) || !$hasColorOption) {

                        if (isset($mockupFileOptionValues[$variant->id][$blankVariantOptionName])) {
                            $this->logger->info('Mockup File Option Values: Option Name:' . $blankVariantOptionName . ' | ' . print_r($mockupFileOptionValues[$variant->id][$blankVariantOptionName], true));
                        }

                        $hasVariantOptionNameBeenUsed = isset($mockupFileOptionValues[$variant->id][$blankVariantOptionName]);
                        $hasVariantOptionValueBeenUsed = $hasVariantOptionNameBeenUsed && in_array($blankVariantOptionValue->name, $mockupFileOptionValues[$variant->id][$blankVariantOptionName]);

                        $this->logger->debug("hasVariantOptionNameBeenUsed: " . ($hasVariantOptionNameBeenUsed ? 'true' : 'false'));
                        $this->logger->debug("hasVariantOptionValueBeenUsed: " . ($hasVariantOptionValueBeenUsed ? 'true' : 'false'));

                        //If option value hasn't been encountered, add the image so we dont duplicate images
                        if (!$hasVariantOptionValueBeenUsed) {
                            $mockupFileOptionValues[$variant->id][$blankVariantOptionName][] = $blankVariantOptionValue->name;
                            $this->logger->info("Mockup Files: " . json_encode($mockupFiles));
                                foreach ($variant->mockupFiles as $mockupFileIndex => $mockupFile) {
                                $hasUsedMockupFileUrl = in_array($mockupFile->file_url, $usedMockupFileUrls);
                                $this->logger->debug("usedMockupFileUrls: " . json_encode($usedMockupFileUrls));
                                $this->logger->debug("mockupFile->file_url: " . $mockupFile->file_url);
                                $this->logger->debug("hasUsedMockupFileUrl: " . ($hasUsedMockupFileUrl ? 'true' : 'false'));
                                if (!$hasUsedMockupFileUrl && $mockupFile->productMockupFile->file_url_original && $imageCount < $imageCountLimit) {
                                    //if the selected mockups array is empty selected default first 10 mockups
                                    if (empty($pickedMockups)) {
                                        $mockupFile->variantIndex = $variantIndex;
                                        $mockupFilesToSend[] = $mockupFile;
                                        $usedMockupFileUrls[] = $mockupFile->file_url;
                                        $imageCount++;
                                    }
                                    //Selected mockups pushed to store
                                    if (!empty($pickedMockups) && in_array($mockupFile->blank_psd_id, $pickedMockups) && !in_array($mockupFile->file_url, $mockupFilesToSend)) {
                                        $mockupFile->variantIndex = $variantIndex;
                                        $mockupFilesToSend[] = $mockupFile;
                                        $usedMockupFileUrls[] = $mockupFile->file_url;
                                        $imageCount++;
                                    }
                                }
                            }
                        }
                        else {
                            $this->logger->debug("Skip image");
                            $this->logger->debug("Isset mockupFileOptionValues $blankVariantOptionName ");
                            $this->logger->debug(isset($mockupFileOptionValues[$variant->id][$blankVariantOptionName]) ? 'true' : 'false');
                            $this->logger->debug("Is " . $blankVariantOptionValue->name . " in array? " . (in_array($blankVariantOptionValue->name, $mockupFileOptionValues[$variant->id][$blankVariantOptionName]) ? 'true' : 'false'));
                        }
                    }
                }
            }
            else {
                $this->logger->info("No Blank Variant Option Values, send all images");
                foreach ($variant->mockupFiles as $mockupFileIndex => $mockupFile) {
                    if ($mockupFile->file_url_original && $imageCount < $imageCountLimit) {
                        if (empty($pickedMockups)) {
                            $mockupFile->variantIndex = $variantIndex;
                            $mockupFilesToSend[] = $mockupFile;
                            $imageCount++;
                        }
                        if (!empty($pickedMockups) && in_array($mockupFile->blank_psd_id, $pickedMockups) && !in_array($mockupFile->file_url, $mockupFilesToSend)) {
                            $mockupFile->variantIndex = $variantIndex;
                            $mockupFilesToSend[] = $mockupFile;
                            $imageCount++;
                        }
                    }
                }
            }
        }

        return $mockupFilesToSend;
    }
}
