<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 14 — Screen 57: Ticket Detail "Reassign Ticket" action.
 *
 * Validates the target assignee for a single-ticket ownership transfer.
 * Authorization (permission gate) is enforced in the controller middleware.
 */
class ReassignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignee_id' => 'required|integer|exists:users,id',
            'note'        => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'assignee_id.required' => 'A target assignee is required to reassign the ticket.',
            'assignee_id.exists'   => 'The selected assignee does not exist.',
        ];
    }
}
