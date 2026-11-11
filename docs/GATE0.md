# TaxMate — Gate 0: verified tax rates (PASSED 2026-11-11)

Gate 0 is the rule that no final tax figure may be computed until the
slab rates are verified against the Finance Act gazette. It passed on
2026-11-11. The full research report (primary sources, discrepancy
notes, limitations) is:

`research_notes/taxmate-gate0-slab-verification-20261111-1152/report.md`

The app carries the same tables in `lib/features/fbr/verified_rates.dart`.
Do NOT edit a rate in either place from memory, a blog, or a
calculator. Any future change needs a new Gate 0 verification run and
an updated report first.

## TY2026 — Finance Act 2025 (1 Jul 2025 – 30 Jun 2026), salaried, Clause (2), Division I, Part I, First Schedule

| Annual taxable income (Rs) | Tax |
|---|---|
| Does not exceed 600,000 | 0% |
| Exceeds 600,000 but does not exceed 1,200,000 | 1% of the amount exceeding Rs 600,000 |
| Exceeds 1,200,000 but does not exceed 2,200,000 | Rs 6,000 + 11% of the amount exceeding Rs 1,200,000 |
| Exceeds 2,200,000 but does not exceed 3,200,000 | Rs 116,000 + 23% of the amount exceeding Rs 2,200,000 |
| Exceeds 3,200,000 but does not exceed 4,100,000 | Rs 346,000 + 30% of the amount exceeding Rs 3,200,000 |
| Exceeds 4,100,000 | Rs 616,000 + 35% of the amount exceeding Rs 4,100,000 |

Surcharge: 9% of the income tax where taxable income exceeds
Rs 10,000,000 (Section 4AB proviso, salaried limb).

## TY2027 — Finance Act 2026 (1 Jul 2026 – 30 Jun 2027), salaried, Clause (2), Division I, Part I, First Schedule

| Annual taxable income (Rs) | Tax |
|---|---|
| Does not exceed 600,000 | 0% |
| Exceeds 600,000 but does not exceed 1,200,000 | 1% of the amount exceeding Rs 600,000 |
| Exceeds 1,200,000 but does not exceed 2,200,000 | Rs 6,000 + 11% of the amount exceeding Rs 1,200,000 |
| Exceeds 2,200,000 but does not exceed 3,200,000 | Rs 116,000 + 20% of the amount exceeding Rs 2,200,000 |
| Exceeds 3,200,000 but does not exceed 4,100,000 | Rs 316,000 + 25% of the amount exceeding Rs 3,200,000 |
| Exceeds 4,100,000 but does not exceed 5,600,000 | Rs 541,000 + 29% of the amount exceeding Rs 4,100,000 |
| Exceeds 5,600,000 but does not exceed 7,000,000 | Rs 976,000 + 32% of the amount exceeding Rs 5,600,000 |
| Exceeds 7,000,000 | Rs 1,424,000 + 35% of the amount exceeding Rs 7,000,000 |

Surcharge: NONE for salaried individuals (the 9% surcharge was
withdrawn by Finance Act 2026 — Gazette: "no surcharge shall be
payable"). 10% continues only for non-salaried individuals and AOPs.

## Clause (1) — individuals and AOPs except a salaried individual, both years

Unchanged by Finance Act 2026 (which amended only the Clause (2) table).

| Annual taxable income (Rs) | Tax |
|---|---|
| Does not exceed 600,000 | 0% |
| Exceeds 600,000 but does not exceed 1,200,000 | 15% of the amount exceeding Rs 600,000 |
| Exceeds 1,200,000 but does not exceed 1,600,000 | Rs 90,000 + 20% of the amount exceeding Rs 1,200,000 |
| Exceeds 1,600,000 but does not exceed 3,200,000 | Rs 170,000 + 30% of the amount exceeding Rs 1,600,000 |
| Exceeds 3,200,000 but does not exceed 5,600,000 | Rs 650,000 + 40% of the amount exceeding Rs 3,200,000 |
| Exceeds 5,600,000 | Rs 1,610,000 + 45% of the amount exceeding Rs 5,600,000 |

Surcharge: 10% of the income tax where taxable income exceeds
Rs 10,000,000, in both years.

## Salaried test

Clause (2) applies only where income chargeable under the head
"salary" exceeds 75% of taxable income. Below that, the Clause (1)
table applies to the whole taxable income. The return's taxpayer
type is the user's declaration of that.

## Resolved discrepancy

FBR Circular No. 01 of 2025-26 (OCR text) reads Rs 345,000 /
Rs 615,000 for TY2026 rows 5–6. The correct fixed amounts are
Rs 346,000 / Rs 616,000 (FBR WHT Rate Card 2025 + First Schedule +
arithmetic). Do not use 345,000 / 615,000.

## Sources

1. FBR, Withholding Income Tax Rate Card — Updated up to June 30, 2026 as per Finance Act, 2026 (TY2027) — https://download1.fbr.gov.pk/Docs/202681113864992WithholdingTaxRatesCard2027.pdf
2. FBR, Withholding Income Tax Rate Card — Updated up to June 30, 2025 as per Finance Act, 2025 (TY2026) — http://download1.fbr.gov.pk/Docs/20258181281745641WHT-RateCard.pdf
3. Finance Act, 2026 (Act No. XLIII of 2026), Gazette of Pakistan (Extraordinary), Part I, 26 June 2026 — http://na.gov.pk/uploads/documents/6a422720f05a0_626.pdf
4. FBR, Circular No. 02 of 2026-27, Finance Act 2026 — Explanation of Important Amendments, 8 September 2026 — https://www.fbr.gov.pk/Downloads/?id=90590&Type=Docs
5. FBR, Circular No. 01 of 2025-26, Finance Act 2025 — Explanation of Important Amendments, 2 August 2025 — https://download1.fbr.gov.pk/Docs/2025841183918948CircularNo01of2025-26IncomeTax.pdf
6. Fakhri Associates, First Schedule — Finance Act 2025 (clauses (1) and (2), Division I, Part I) — http://fakhriassociates.com/Projects/Financebill2025-26/First Schedule 2025-26.pdf
7. Riaz Ahmad, Saqib, Gohar & Co. (RASG), Tax Card Tax Year 2026 (Finance Act 2025) — https://www.rasgco.com/website/images/pdf/Tax_Card_2026.pdf

## What Gate 0 does NOT cover

Tax credits, rebates, allowances, pension taxation (s.149(IA)), and
the s.4C super tax (separate Division IIB levy, income above
Rs 150,000,000) are outside these slab tables and were not verified
as a complete set. The app computes slab tax + surcharge only.
