<?php

namespace App\Http\Requests\Concerns;

use App\Support\Phone;

/**
 * Normalize phone inputs (to 01XXXXXXXXX) before validation and lookup.
 */
trait NormalizesPhones
{
    /**
     * @return list<string>
     */
    abstract protected function phoneFields(): array;

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ($this->phoneFields() as $field) {
            $value = $this->input($field);

            if (is_string($value) && trim($value) !== '') {
                $normalized[$field] = Phone::normalize($value);
            } elseif ($value === '') {
                $normalized[$field] = null;
            }
        }

        $this->merge($normalized);
    }
}
