<?php

namespace App\Http\Requests\Ticket;

use App\Support\TicketRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories  = implode(',', TicketRules::CATEGORIES);
        $priorities  = implode(',', TicketRules::PRIORITIES);
        $sourceRoles = implode(',', TicketRules::SOURCE_ROLES);

        return [
            'subject'         => 'required|string|max:255',
            'description'     => 'required|string|max:5000',
            'priority'        => 'nullable|in:' . $priorities,
            'category'        => 'nullable|in:' . $categories,
            'assigned_to'     => 'nullable|integer|exists:users,id',
            'source_role'     => 'nullable|in:' . $sourceRoles,
            'reporter_name'   => 'nullable|string|max:255',
            'reporter_email'  => 'nullable|email|max:255',
            'reporter_phone'  => 'nullable|string|max:20',
            'entity_type'     => 'nullable|string|max:50',
            'entity_id'       => 'nullable|integer|min:1',
            'attachments'     => 'nullable|array|max:' . TicketRules::MAX_ATTACHMENTS,
            'attachments.*'   => TicketRules::attachmentValidationRule(),
            'links'           => 'nullable|array|max:5',
            'links.*.entity_type' => 'required_with:links|nullable|string|max:50',
            'links.*.entity_id'   => 'required_with:links|nullable|integer|min:1',
            'links.*.label'       => 'nullable|string|max:255',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('priority') && $this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }

        if ($this->has('category') && $this->input('category') === '') {
            $this->merge(['category' => null]);
        }
    }

    public function validatedPayload(): array
    {
        $user = $this->user();

        return [
            'subject'        => $this->input('subject'),
            'description'    => $this->input('description'),
            'priority'       => $this->input('priority', TicketRules::DEFAULT_PRIORITY),
            'category'       => $this->input('category', TicketRules::DEFAULT_CATEGORY),
            'assigned_to'    => $this->input('assigned_to'),
            'source_role'    => $this->input('source_role', TicketRules::DEFAULT_SOURCE_ROLE),
            'reporter_name'  => $this->input('reporter_name', $user?->name ?? 'Super Admin'),
            'reporter_email' => $this->input('reporter_email', $user?->email),
            'reporter_phone' => $this->input('reporter_phone'),
            'entity_type'    => $this->input('entity_type'),
            'entity_id'      => $this->input('entity_id'),
            'links'          => $this->input('links', []),
        ];
    }
}
