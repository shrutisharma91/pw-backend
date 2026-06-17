<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'subject',
        'description',
        'source_role',
        'category',
        'priority',
        'status',
        'sla_state',
        'assigned_to',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'entity_type',
        'entity_id',
        'first_response_due_at',
        'resolution_due_at',
        'first_responded_at',
        'resolved_at',
        'closed_at',
        'resolution_category',
        'resolution_note',
        'csat_score',
        'csat_comment',
        'csat_requested_at',
        'escalated_at',
        'escalated_to',
        'created_by',
    ];

    protected $casts = [
        'first_response_due_at' => 'datetime',
        'resolution_due_at'     => 'datetime',
        'first_responded_at'    => 'datetime',
        'resolved_at'           => 'datetime',
        'closed_at'             => 'datetime',
        'csat_requested_at'     => 'datetime',
        'escalated_at'          => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(TicketLink::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function escalatedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }
}
