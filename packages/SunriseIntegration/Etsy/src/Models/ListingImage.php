<?php

namespace SunriseIntegration\Etsy\Models;

/**
 * Class ListingImage
 * @method getListingImageId;
 * @method getHexCode;
 * @method getRed;
 * @method getGreen;
 * @method getBlue;
 * @method getHue;
 * @method getSaturation;
 * @method getBrightness;
 * @method getIsBlackAndWhite;
 * @method getCreationTsz;
 * @method getListingId;
 * @method getRank;
 * @method getUrl75x75;
 * @method getUrl170x135;
 * @method getUrl570xn;
 * @method getUrlFullxfull;
 * @method getFullHeight;
 * @method getFullWidth;
 *
 * @method setListingImageId($value);
 * @method setHexCode($value);
 * @method setRed($value);
 * @method setGreen($value);
 * @method setBlue($value);
 * @method setHue($value);
 * @method setSaturation($value);
 * @method setBrightness($value);
 * @method setIsBlackAndWhite($value);
 * @method setCreationTsz($value);
 * @method setListingId($value);
 * @method setRank($value);
 * @method setUrl75x75($value);
 * @method setUrl170x135($value);
 * @method setUrl570xn($value);
 * @method setUrlFullxfull($value);
 * @method setFullHeight($value);
 * @method setFullWidth($value);
 **/
class ListingImage extends AbstractEntity
{
    protected $listingImageId;
    protected $hexCode;
    protected $red;
    protected $green;
    protected $blue;
    protected $hue;
    protected $saturation;
    protected $brightness;
    protected $isBlackAndWhite;
    protected $creationTsz;
    protected $listingId;
    protected $rank;
    protected $url75x75;
    protected $url170x135;
    protected $url570xn;
    protected $urlFullxfull;
    protected $fullHeight;
    protected $fullWidth;

    public $url_75x75;
}
