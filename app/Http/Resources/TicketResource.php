<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'ticket_number'  => $this->ticket_number,
            'subject'        => $this->subject,
            'description'    => $this->description,
            'source_role'    => $this->source_role,
            'category'       => $this->category,
            'priority'       => $this->priority,
            'status'         => $this->status,
            'sla_state'      => $this->sla_state,
            'assigned_to'    => $this->assigned_to,
            'assignee'       => $this->whenLoaded('assignee', fn () => [
                'id'    => $this->assignee->id,
                'name'  => $this->assignee->name,
                'email' => $this->assignee->email,
            ]),
            'reassigned_at'  => $this->reassigned_at,
            'reassigned_by'  => $this->reassigned_by,
            'reassigned_by_user' => $this->whenLoaded('reassignedByUser', fn () => [
                'id'    => $this->reassignedByUser->id,
                'name'  => $this->reassignedByUser->name,
                'email' => $this->reassignedByUser->email,
            ]),
            'reporter_name'  => $this->reporter_name,
            'customer_name'  => $this->reporter_name,
            'customer'       => $this->reporter_name,
            'assigned_to_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name, $this->assignee?->name),
            'sla_remaining'  => $this->sla_state,
            'reporter_email' => $this->reporter_email,
            'reporter_phone' => $this->reporter_phone,
            'entity_type'    => $this->entity_type,
            'entity_id'      => $this->entity_id,
            'created_by'     => $this->created_by,
            'creator'        => $this->whenLoaded('creator', fn () => [
                'id'    => $this->creator->id,
                'name'  => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            'first_response_due_at' => $this->first_response_due_at,
            'resolution_due_at'     => $this->resolution_due_at,
            'attachments'    => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id'                => $attachment->id,
                'original_filename' => $attachment->original_filename,
                'mime_type'         => $attachment->mime_type,
                'file_size_bytes'   => $attachment->file_size_bytes,
                'uploaded_by'       => $attachment->uploaded_by,
                'created_at'        => $attachment->created_at,
            ])),
            'links'          => $this->whenLoaded('links'),
            'messages'       => $this->whenLoaded('messages'),
            'sla'            => $this->when(isset($this->sla), fn () => $this->sla),
            'time_remaining' => $this->when(isset($this->sla), fn () => $this->sla['resolution_remaining'] ?? $this->sla['first_response_remaining'] ?? null),
            'target_at'      => $this->when(isset($this->sla), fn () => $this->sla['resolution_due_at'] ?? $this->sla['first_response_due_at'] ?? null),
            'progress_percent' => $this->when(isset($this->sla), function () {
                $remaining = (int) ($this->sla['resolution_remaining'] ?? 0);
                return $this->sla['is_breached'] ?? false ? 100 : min(100, max(0, 100 - min(100, $remaining)));
            }),
        ];
    }
}
