<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketLink;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\TicketRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketService
{
    private const STORAGE_DISK = 'r2';

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     */
    public function create(array $data, User $creator, array $attachments = []): Ticket
    {
        return DB::transaction(function () use ($data, $creator, $attachments) {
            $slaHours = $this->firstResponseSlaHours();
            $status   = $data['assigned_to']
                ? 'in_progress'
                : TicketRules::DEFAULT_STATUS;

            $ticket = Ticket::create([
                'ticket_number'         => $this->generateTicketNumber(),
                'subject'               => $data['subject'],
                'description'           => $data['description'],
                'source_role'           => $data['source_role'],
                'category'              => $data['category'],
                'priority'              => $data['priority'],
                'status'                => $status,
                'sla_state'             => 'ok',
                'assigned_to'           => $data['assigned_to'],
                'reporter_name'         => $data['reporter_name'],
                'reporter_email'        => $data['reporter_email'] ?? null,
                'reporter_phone'        => $data['reporter_phone'] ?? null,
                'entity_type'           => $data['entity_type'] ?? null,
                'entity_id'             => $data['entity_id'] ?? null,
                'first_response_due_at' => now()->addHours($slaHours),
                'resolution_due_at'     => now()->addHours($slaHours * 3),
                'created_by'            => $creator->id,
            ]);

            TicketMessage::create([
                'ticket_id'   => $ticket->id,
                'visibility'  => 'public',
                'author_type' => 'admin',
                'author_id'   => $creator->id,
                'author_name' => $creator->name,
                'body'        => $data['description'],
            ]);

            if ($ticket->entity_type && $ticket->entity_id) {
                TicketLink::create([
                    'ticket_id'   => $ticket->id,
                    'entity_type' => $ticket->entity_type,
                    'entity_id'   => $ticket->entity_id,
                    'label'       => ucfirst($ticket->entity_type) . ' #' . $ticket->entity_id,
                ]);
            }

            foreach ($data['links'] as $link) {
                if (empty($link['entity_type']) || empty($link['entity_id'])) {
                    continue;
                }

                TicketLink::create([
                    'ticket_id'   => $ticket->id,
                    'entity_type' => $link['entity_type'],
                    'entity_id'   => $link['entity_id'],
                    'label'       => $link['label'] ?? ucfirst($link['entity_type']) . ' #' . $link['entity_id'],
                ]);
            }

            foreach ($attachments as $file) {
                $this->storeAttachment($ticket, $file, $creator->id);
            }

            activity()->log("Ticket {$ticket->ticket_number} created by admin#{$creator->id}");

            return $ticket->fresh([
                'assignee:id,name,email',
                'attachments',
                'links',
                'messages',
            ]);
        });
    }

    private function generateTicketNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "TKT-{$year}-";

        $latest = Ticket::query()
            ->where('ticket_number', 'like', $prefix . '%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $sequence = 1;

        if ($latest && preg_match('/TKT-\d{4}-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function firstResponseSlaHours(): int
    {
        $hours = DB::table('system_parameters')
            ->where('key', 'ticket_first_response_sla_hours')
            ->value('value');

        return max(1, (int) ($hours ?: 24));
    }

    private function storeAttachment(Ticket $ticket, UploadedFile $file, int $uploadedBy): TicketAttachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $safeName  = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $safeName  = $safeName !== '' ? $safeName : 'attachment';
        $storagePath = sprintf(
            'tickets/%d/%s_%s.%s',
            $ticket->id,
            now()->format('YmdHis'),
            Str::uuid()->toString(),
            $extension
        );

        Storage::disk(self::STORAGE_DISK)->putFileAs(
            dirname($storagePath),
            $file,
            basename($storagePath)
        );

        return TicketAttachment::create([
            'ticket_id'         => $ticket->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_path'      => $storagePath,
            'mime_type'         => $file->getMimeType() ?? $file->getClientMimeType(),
            'file_size_bytes'   => $file->getSize(),
            'uploaded_by'       => $uploadedBy,
        ]);
    }
}
