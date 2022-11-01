<?php

namespace App\Rules;

use App\Models\Blanks\ArtworkStage\BlankStageCreateTypeBlankStage;
use Illuminate\Contracts\Validation\Rule;

class AccountImageRequirements implements Rule
{
    protected $parameters;

    /**
     * Create a new rule instance.
     *
     * @param $parameters
     */
    public function __construct($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $blankStageId = $this->parameters->blankStageId;
        $createTypeId = $this->parameters->createTypeId;
        $blankStageCreateTypeBlankStage = BlankStageCreateTypeBlankStage::where([['blank_stage_id', $blankStageId], ['create_type_id', $createTypeId]])->with('image_requirement', 'create_type')->first();
        if (!$blankStageCreateTypeBlankStage || !$blankStageCreateTypeBlankStage->image_requirement) {
            return true;
        }
        $imageRequirement = $blankStageCreateTypeBlankStage->image_requirement;
        //TODO: Unfinished
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
