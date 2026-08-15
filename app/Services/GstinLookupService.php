<?php

namespace App\Services;

/**
 * Demo GSTIN lookup for Phase 6 Screen 44.
 * Does not call a live government API — returns seeded / derived records for local testing.
 */
class GstinLookupService
{
    /** @var array<string, array<string, string>> */
    private const REGISTRY = [
        '27AAPFU0939F1ZV' => [
            'legal_name' => 'VIJAY SALES INDIA PRIVATE LIMITED',
            'trade_name' => 'Vijay Sales Andheri',
            'address'    => 'S.V. Road, Andheri West, Mumbai, Maharashtra 400058',
            'state'      => 'Maharashtra',
            'pincode'    => '400058',
            'status'     => 'Active',
        ],
        '07AABCU9603R1ZX' => [
            'legal_name' => 'INFINITI RETAIL LIMITED',
            'trade_name' => 'Croma Connaught Place',
            'address'    => 'Block A, Inner Circle, Connaught Place, New Delhi 110001',
            'state'      => 'Delhi',
            'pincode'    => '110001',
            'status'     => 'Active',
        ],
        '29AAGCB1234A1Z5' => [
            'legal_name' => 'BRIGHT ELECTRONICS LLP',
            'trade_name' => 'Bright Electronics Koramangala',
            'address'    => '80 Feet Road, Koramangala, Bengaluru, Karnataka 560034',
            'state'      => 'Karnataka',
            'pincode'    => '560034',
            'status'     => 'Active',
        ],
        '24AAACR5055K1Z8' => [
            'legal_name' => 'GADGET WORLD RETAIL PRIVATE LIMITED',
            'trade_name' => 'Gadget World Ahmedabad',
            'address'    => 'CG Road, Navrangpura, Ahmedabad, Gujarat 380009',
            'state'      => 'Gujarat',
            'pincode'    => '380009',
            'status'     => 'Active',
        ],
    ];

    public function lookup(string $gstin): array
    {
        $gstin = strtoupper(trim($gstin));

        if (isset(self::REGISTRY[$gstin])) {
            return array_merge(['gstin' => $gstin, 'source' => 'registry'], self::REGISTRY[$gstin]);
        }

        $pan = substr($gstin, 2, 10);

        return [
            'gstin'      => $gstin,
            'source'     => 'synthetic',
            'legal_name' => 'FIELD ONBOARDED MERCHANT ' . $pan,
            'trade_name' => 'New Merchant ' . substr($gstin, -4),
            'address'    => 'Address pending store geo-tag',
            'state'      => $this->stateFromGstin($gstin),
            'pincode'    => '000000',
            'status'     => 'Active',
        ];
    }

    private function stateFromGstin(string $gstin): string
    {
        $code = substr($gstin, 0, 2);
        $map = [
            '07' => 'Delhi',
            '24' => 'Gujarat',
            '27' => 'Maharashtra',
            '29' => 'Karnataka',
            '33' => 'Tamil Nadu',
            '36' => 'Telangana',
        ];

        return $map[$code] ?? 'Unknown';
    }
}
