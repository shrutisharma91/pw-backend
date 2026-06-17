<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Phase14Seeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'finzwork10@gmail.com')->first();
        $adminId = $admin?->id ?? 1;
        $merchant = Merchant::first();

        $tickets = [
            [
                'ticket_number' => 'TKT-2026-00001',
                'subject'       => 'Settlement amount mismatch',
                'description'   => 'Merchant reports settlement batch total does not match dashboard.',
                'source_role'   => 'merchant',
                'category'      => 'settlement',
                'priority'      => 'high',
                'status'        => 'open',
                'sla_state'     => 'at_risk',
                'assigned_to'   => null,
                'reporter_name' => 'Tech Superstore',
                'reporter_email'=> 'ops@techsuperstore.com',
                'entity_type'   => 'merchant',
                'entity_id'     => $merchant?->id,
            ],
            [
                'ticket_number' => 'TKT-2026-00002',
                'subject'       => 'KYC document rejected incorrectly',
                'description'   => 'PAN verification failed but document appears valid.',
                'source_role'   => 'merchant',
                'category'      => 'kyc',
                'priority'      => 'critical',
                'status'        => 'in_progress',
                'sla_state'     => 'ok',
                'assigned_to'   => $adminId,
                'reporter_name' => 'Tech Superstore',
                'reporter_email'=> 'kyc@techsuperstore.com',
                'entity_type'   => 'merchant',
                'entity_id'     => $merchant?->id,
            ],
            [
                'ticket_number' => 'TKT-2026-00003',
                'subject'       => 'Customer EMI bounce complaint',
                'description'   => 'Customer says EMI was debited twice for June.',
                'source_role'   => 'customer',
                'category'      => 'billing',
                'priority'      => 'medium',
                'status'        => 'waiting',
                'sla_state'     => 'ok',
                'assigned_to'   => $adminId,
                'reporter_name' => 'Rahul Sharma',
                'reporter_email'=> 'rahul@example.com',
                'entity_type'   => 'loan',
                'entity_id'     => 1,
            ],
            [
                'ticket_number' => 'TKT-2026-00004',
                'subject'       => 'Lender API timeout on disbursal',
                'description'   => 'FinBank API timed out during disbursal trigger.',
                'source_role'   => 'lender_ops',
                'category'      => 'technical',
                'priority'      => 'high',
                'status'        => 'escalated',
                'sla_state'     => 'breached',
                'assigned_to'   => $adminId,
                'reporter_name' => 'Lender Ops Team',
                'reporter_email'=> 'ops@finbank.com',
                'entity_type'   => 'loan',
                'entity_id'     => 1,
            ],
            [
                'ticket_number' => 'TKT-2026-00005',
                'subject'       => 'Agreement PDF not downloadable',
                'description'   => 'Merchant cannot download signed agreement from portal.',
                'source_role'   => 'store',
                'category'      => 'agreement',
                'priority'      => 'low',
                'status'        => 'resolved',
                'sla_state'     => 'ok',
                'assigned_to'   => $adminId,
                'reporter_name' => 'Store Manager Mumbai',
                'reporter_email'=> 'store@mumbai.techsuperstore.com',
                'entity_type'   => 'merchant',
                'entity_id'     => $merchant?->id,
            ],
        ];

        $slaHours = (int) (DB::table('system_parameters')->where('key', 'ticket_first_response_sla_hours')->value('value') ?: 24);

        foreach ($tickets as $data) {
            $ticket = Ticket::firstOrCreate(
                ['ticket_number' => $data['ticket_number']],
                array_merge($data, [
                    'first_response_due_at' => now()->subHours(2)->addHours($slaHours),
                    'resolution_due_at'     => now()->addHours($slaHours * 3),
                    'first_responded_at'    => in_array($data['status'], ['in_progress', 'waiting', 'escalated', 'resolved'], true)
                        ? now()->subHour()
                        : null,
                    'resolved_at'           => $data['status'] === 'resolved' ? now()->subHours(3) : null,
                    'resolution_category'   => $data['status'] === 'resolved' ? 'agreement' : null,
                    'resolution_note'       => $data['status'] === 'resolved' ? 'Regenerated agreement link and confirmed download.' : null,
                    'csat_score'            => $data['status'] === 'resolved' ? 5 : null,
                    'created_by'            => $adminId,
                ])
            );

            if (! $ticket->messages()->exists()) {
                TicketMessage::create([
                    'ticket_id'   => $ticket->id,
                    'visibility'  => 'public',
                    'author_type' => $data['source_role'] === 'customer' ? 'customer' : 'merchant',
                    'author_name' => $data['reporter_name'],
                    'body'        => $data['description'],
                ]);

                if ($ticket->assigned_to) {
                    TicketMessage::create([
                        'ticket_id'   => $ticket->id,
                        'visibility'  => 'public',
                        'author_type' => 'admin',
                        'author_id'   => $adminId,
                        'author_name' => $admin?->name ?? 'Super Admin',
                        'body'        => 'Thanks for reaching out. We are reviewing this ticket.',
                    ]);
                }

                if ($data['status'] === 'escalated') {
                    TicketMessage::create([
                        'ticket_id'   => $ticket->id,
                        'visibility'  => 'internal',
                        'author_type' => 'system',
                        'author_name' => 'System',
                        'body'        => 'Escalated to senior support due to SLA breach.',
                    ]);
                }
            }

            if ($ticket->entity_type && $ticket->entity_id && ! $ticket->links()->exists()) {
                TicketLink::create([
                    'ticket_id'   => $ticket->id,
                    'entity_type' => $ticket->entity_type,
                    'entity_id'   => $ticket->entity_id,
                    'label'       => ucfirst($ticket->entity_type) . ' #' . $ticket->entity_id,
                ]);
            }
        }
    }
}
