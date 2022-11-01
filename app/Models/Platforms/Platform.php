<?php

namespace App\Models\Platforms;

class Platform extends \SunriseIntegration\TeelaunchModels\Models\Platforms\Platform {
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    public function getLogoAttribute($value){
        if (preg_match('/^[T|t]eelaunch$/', $this->name)) {
            return $value;
        }

        return strtolower($this->name).".svg";
    }
}
