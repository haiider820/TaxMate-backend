<?php
// TaxMate API — slab engine interface.
// GATE 0 RULE: no verified tax rates exist in this codebase yet, by design.
// tax_rules rows may only be created with is_verified = 0 until Phase 0
// gazette verification is signed off. Until a verified row exists for a
// tax year + taxpayer type, taxmate_calculate_tax() MUST refuse, never guess.
// Test fixtures with TEST-ONLY rates belong in tests/docs only, never here.

declare(strict_types=1);

class TaxRatesNotVerified extends Exception
{
}

// Calculate tax from an explicit, already-verified rate table.
// $rates: array of ['from_paisa'=>int, 'to_paisa'=>int|null, 'rate_bps'=>int,
// 'base_tax_paisa'=>int], sorted by from_paisa ascending. All integer maths.
function taxmate_calculate_tax_from_rates(int $taxablePaisa, array $rates): int
{
    if ($rates === []) {
        throw new TaxRatesNotVerified('rates not verified (Gate 0 pending)');
    }
    foreach ($rates as $rate) {
        $from = (int)$rate['from_paisa'];
        $to = $rate['to_paisa'] === null ? null : (int)$rate['to_paisa'];
        if ($taxablePaisa >= $from && ($to === null || $taxablePaisa <= $to)) {
            $excess = $taxablePaisa - $from;
            // Integer maths only: excess * rate_bps / 10000, no float.
            $onExcess = (int)(($excess * (int)$rate['rate_bps']) / 10000);
            return (int)$rate['base_tax_paisa'] + $onExcess;
        }
    }
    throw new TaxRatesNotVerified('rates not verified (Gate 0 pending)');
}

// Load verified rates for a tax year + taxpayer type, or refuse.
function taxmate_calculate_tax(PDO $pdo, int $taxYearId, string $taxpayerType, int $taxablePaisa): int
{
    $stmt = $pdo->prepare(
        'SELECT from_paisa, to_paisa, rate_bps, base_tax_paisa FROM tax_rules ' .
        'WHERE tax_year_id = ? AND taxpayer_type = ? AND is_verified = 1 ' .
        'ORDER BY from_paisa ASC'
    );
    $stmt->execute([$taxYearId, $taxpayerType]);
    $rates = $stmt->fetchAll();
    if ($rates === []) {
        throw new TaxRatesNotVerified('rates not verified (Gate 0 pending)');
    }
    return taxmate_calculate_tax_from_rates($taxablePaisa, $rates);
}
