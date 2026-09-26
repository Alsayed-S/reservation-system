<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'resource_id' => [
                'sometimes',
                'integer',
                'exists:resources,id',
            ],

            'units' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'starts_at' => [
                'sometimes',
                'date',
            ],

            'ends_at' => [
                'sometimes',
                'date',
                'after:starts_at',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'resource_id.integer' => 'The resource ID must be an integer.',
            'resource_id.exists' => 'The selected resource does not exist.',

            'units.integer' => 'The number of units must be an integer.',
            'units.min' => 'The number of units must be at least 1.',

            'starts_at.date' => 'The start date and time must be a valid date.',

            'ends_at.date' => 'The end date and time must be a valid date.',
            'ends_at.after' => 'The end date and time must be after the start date and time.',
        ];
    }
}