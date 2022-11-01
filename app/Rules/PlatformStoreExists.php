<?php

namespace App\Rules;

use App\Models\Blanks\BlankVariant;
use App\Models\Platforms\PlatformStore;
use Illuminate\Contracts\Validation\Rule;

class PlatformStoreExists implements Rule
{
    private $accountId;

    /**
     * Create a new rule instance.
     *
     * @param null $parameters
     */
    public function __construct($accountId)
    {
        $this->accountId = $accountId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $platformStoreExists = false;
        if (PlatformStore::where([['id', $value], ['account_id', $this->accountId]])->exists()) {
            $platformStoreExists = true;
        }
        return $platformStoreExists;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "The platform store id doesn't exist";
    }
}
