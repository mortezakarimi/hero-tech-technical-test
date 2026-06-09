<?php

namespace App\Http\Requests;

use App\Enums\PaymentOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'simulate' => ['required', Rule::enum(PaymentOutcome::class)],
        ];
    }
}
