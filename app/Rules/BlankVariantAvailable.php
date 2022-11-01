<?php

namespace App\Rules;

use App\Models\Accounts\AccountBlankAccess;
use App\Models\Blanks\Blank;
use App\Models\Blanks\BlankVariant;
use Illuminate\Contracts\Validation\Rule;

class BlankVariantAvailable implements Rule
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
        $blankVariant = BlankVariant::where('id', $value)->first();
        $blank = null;

        //Check if store has special access to the blank
        if(!$blankVariant->is_active){
            $storeBlanksAccessIds = AccountBlankAccess::getStoreBlankAccessIds();
            $blank =  $blank = Blank::available($storeBlanksAccessIds)->find($blankVariant->blank_id);
        }

        if (!$blankVariant->is_active && !$blank) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The blank variant is not available';
    }
}
