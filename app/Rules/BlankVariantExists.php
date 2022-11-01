<?php

namespace App\Rules;

use App\Models\Blanks\BlankVariant;
use Illuminate\Contracts\Validation\Rule;

class BlankVariantExists implements Rule
{
    private $parameters;

    /**
     * Create a new rule instance.
     *
     * @param null $parameters
     */
    public function __construct($parameters = null)
    {
        $this->parameters = $parameters;
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
        $variantExists = false;
        //Need to check if
        if (BlankVariant::where('id', $value)->exists()) {
            $variantExists = true;
        }
        return $variantExists;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The validation error message.';
    }
}
