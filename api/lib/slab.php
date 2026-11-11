<?php
// TaxMate API — slab engine interface.
// GATE 0 PASSED 2026-11-11: verified rates for TY2026 + TY2027 live in
// tax_rules (migration 005, is_verified = 1) with their Finance Act
// sources. taxmate_calculate_tax() computes slab tax + Section 4AB
// surcharge from those rows. Any year + type WITHOUT a verified row
// still refuses (TaxRatesNotVerified) — never guess. Test fixtures
// with TEST-ONLY rates belong in tests/docs only, never here.

declare(strict_types=1);

class TaxRatesNotVerified extends Exception
{
}

/// Primary sources for the Gate 0 verification (same list as the app's
/// verified_rates.dart and docs/GATE0.md). Returned by GET tax-rules.
function taxmate_gate0_sources(): array
{
    return [
        [
            'title' => 'FBR Withholding Tax Rate Card 2027 (Finance Act 2026)',
            'url' => 'https://download1.fbr.gov.pk/Docs/202681113864992WithholdingTaxRatesCard2027.pdf',
        ],
        [
            'title' => 'FBR Withholding Tax Rate Card 2025 (Finance Act 2025)',
            'url' => 'http://download1.fbr.gov.pk/Docs/20258181281745641WHT-RateCard.pdf',
        ],
        [
            'title' => 'Finance Act 2026 (Act XLIII of 2026), Gazette of Pakistan',
            'url' => 'http://na.gov.pk/uploads/documents/6a422720f05a0_626.pdf',
        ],
        [
            'title' => 'FBR Circular No. 02 of 2026-27 (Finance Act 2026)',
            'url' => 'https://www.fbr.gov.pk/Downloads/?id=90590&Type=Docs',
        ],
        [
            'title' => 'FBR Circular No. 01 of 2025-26 (Finance Act 2025)',
            'url' => 'https://download1.fbr.gov.pk/Docs/2025841183918948CircularNo01of2025-26IncomeTax.pdf',
        ],
        [
            'title' => 'Fakhri Associates — First Schedule, Finance Act 2025',
            'url' => 'http://fakhriassociates.com/Projects/Financebill2025-26/First Schedule 2025-26.pdf',
        ],
        [
            'title' => 'RASG Tax Card Tax Year 2026 (Finance Act 2025)',
            'url' => 'https://www.rasgco.com/website/images/pdf/Tax_Card_2026.pdf',
        ],
    ];
}

// Section 4AB surcharge on top of the slab tax. $surchargeRuleJson is
// the tax_rules.surcharge_rule value: {"threshold_paisa":int,
// "rate_bps":int}, or null when the table has no surcharge. Integer
// maths only. Zero when there is no rule, the rule is malformed, the
// rate is not positive, or the taxable income does not exceed the
// threshold (exactly at it pays no surcharge).
function taxmate_apply_surcharge(int $slabTax, int $taxablePaisa, ?string $surchargeRuleJson): int
{
    if ($surchargeRuleJson === null || $surchargeRuleJson === '') {
        return 0;
    }
    $rule = json_decode($surchargeRuleJson, true);
    if (!is_array($rule) || !isset($rule['threshold_paisa'], $rule['rate_bps'])) {
        return 0;
    }
    $threshold = (int)$rule['threshold_paisa'];
    $rateBps = (int)$rule['rate_bps'];
    if ($taxablePaisa <= $threshold || $rateBps <= 0) {
        return 0;
    }
    return intdiv($slabTax * $rateBps, 10000);
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

// Load verified rates for a tax year + taxpayer type and return the
// breakdown (slab tax, surcharge, total tax payable). Refuses when no
// verified row exists for the year + type.
function taxmate_calculate_tax_breakdown(PDO $pdo, int $taxYearId, string $taxpayerType, int $taxablePaisa): array
{
    $stmt = $pdo->prepare(
        'SELECT from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule FROM tax_rules ' .
        'WHERE tax_year_id = ? AND taxpayer_type = ? AND is_verified = 1 ' .
        'ORDER BY from_paisa ASC'
    );
    $stmt->execute([$taxYearId, $taxpayerType]);
    $rates = $stmt->fetchAll();
    if ($rates === []) {
        throw new TaxRatesNotVerified('rates not verified (Gate 0 pending)');
    }
    $slabTax = taxmate_calculate_tax_from_rates($taxablePaisa, $rates);
    $surchargeRule = null;
    foreach ($rates as $rate) {
        if (!empty($rate['surcharge_rule'])) {
            $surchargeRule = $rate['surcharge_rule'];
            break;
        }
    }
    $surcharge = taxmate_apply_surcharge($slabTax, $taxablePaisa, $surchargeRule);
    return [
        'slab_tax_paisa' => $slabTax,
        'surcharge_paisa' => $surcharge,
        'tax_payable_paisa' => $slabTax + $surcharge,
    ];
}

// Load verified rates for a tax year + taxpayer type, or refuse.
// Returns the total tax payable (slab tax + surcharge).
function taxmate_calculate_tax(PDO $pdo, int $taxYearId, string $taxpayerType, int $taxablePaisa): int
{
    $breakdown = taxmate_calculate_tax_breakdown($pdo, $taxYearId, $taxpayerType, $taxablePaisa);
    return $breakdown['tax_payable_paisa'];
}
