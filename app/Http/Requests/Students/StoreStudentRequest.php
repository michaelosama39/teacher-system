<?php

namespace App\Http\Requests\Students;

use App\Enums\ParentRelation;
use App\Http\Requests\Concerns\NormalizesPhones;
use App\Models\Student;
use App\Rules\EgyptianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    use NormalizesPhones;

    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /**
     * At least one phone is required so someone can log in and be contacted.
     * The student phone must be unused, unless it is the same as the parent phone
     * (then only the parent account is created).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $studentPhoneRules = ['nullable', 'string', 'required_without:parent_phone', new EgyptianPhone];

        if ($this->input('phone') !== $this->input('parent_phone')) {
            $studentPhoneRules[] = Rule::unique('users', 'phone');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => $studentPhoneRules,
            'parent_phone' => ['nullable', 'string', 'required_without:phone', new EgyptianPhone],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_relation' => ['nullable', Rule::enum(ParentRelation::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function phoneFields(): array
    {
        return ['phone', 'parent_phone'];
    }
}
