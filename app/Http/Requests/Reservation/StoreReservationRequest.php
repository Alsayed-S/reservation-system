<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
    
            'resource_id' => [
                'required',
                'integer',
                'exists:resources,id',
            ],
    
            'units' => [
                'required',
                'integer',
                'min:1',
            ],
    
            'start_time' => [
                'required',
                'date',
            ],
    
            'end_time' => [
                'required',
                'date',
                'after:start_time',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'resource_id.required' => 'The resource is required.',
            'resource_id.integer' => 'The resource ID must be an integer.',
            'resource_id.exists' => 'The selected resource does not exist.',

            'units.required' => 'The number of units is required.',
            'units.integer' => 'The number of units must be an integer.',
            'units.min' => 'The number of units must be at least 1.',

            'starts_at.required' => 'The start date and time is required.',
            'starts_at.date' => 'The start date and time must be a valid date.',
            'starts_at.after_or_equal' => 'The start date and time cannot be in the past.',

            'ends_at.required' => 'The end date and time is required.',
            'ends_at.date' => 'The end date and time must be a valid date.',
            'ends_at.after' => 'The end date and time must be after the start date and time.',
        ];
    }
}