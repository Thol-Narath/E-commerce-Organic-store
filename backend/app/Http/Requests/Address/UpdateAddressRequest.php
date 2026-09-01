<?php

namespace App\Http\Requests\Address;

class UpdateAddressRequest extends StoreAddressRequest
{
    /**
     * PATCH semantics: any field may be omitted and the existing value is kept.
     * When present, a field must still satisfy the base validation rules.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => $ruleSet) {
            $rules[$field] = array_merge(['sometimes'], $ruleSet);
        }

        return $rules;
    }
}