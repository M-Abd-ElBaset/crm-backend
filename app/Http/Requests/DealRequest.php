<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\DealStage;
use Illuminate\Validation\Rule;

class DealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'stage' => ['required', Rule::enum(DealStage::class)],
            'expected_close_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lost_reason' => ['nullable', 'string', 'max:1000', 'required_if:stage,lost'],
        ];
    }
}
