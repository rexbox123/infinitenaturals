<?php

namespace App\Http\Requests;

use App\Http\Controllers\CheckoutController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'state' => strtoupper(trim((string) $this->input('state'))),
            'postal_code' => trim((string) $this->input('postal_code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+().\-\s]{7,}$/'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address_line1' => ['required', 'string', 'max:255', 'not_regex:/\b(APO|FPO|DPO)\b/i'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100', 'not_regex:/^\s*(APO|FPO|DPO)\s*$/i'],
            'state' => ['required', Rule::in(array_keys(config('store.shipping_states')))],
            'postal_code' => ['required', 'regex:/^\d{5}(-\d{4})?$/'],
            'shipping_method_id' => ['required', 'integer', Rule::exists('shipping_methods', 'id')->where('is_active', true)],
            'payment_method' => ['required', Rule::in(array_keys(CheckoutController::availablePaymentMethods()))],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'expected_total' => ['nullable', 'integer', 'min:0'],
            'accept_terms' => [setting('checkout.require_terms', true) ? 'accepted' : 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'address_line1.not_regex' => 'Sorry, we do not ship to APO/FPO/DPO addresses.',
            'city.not_regex' => 'Sorry, we do not ship to APO/FPO/DPO addresses.',
            'state.in' => 'We currently ship to the 50 U.S. states only.',
            'postal_code.regex' => 'Please enter a valid 5-digit ZIP code.',
            'phone.regex' => 'Please enter a valid phone number.',
            'accept_terms.accepted' => 'Please accept the terms and conditions to place your order.',
            'payment_method.in' => 'Please choose an available payment method.',
        ];
    }
}
