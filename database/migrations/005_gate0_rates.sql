-- TaxMate migration 005: Gate 0 verified tax rates (passed 2026-11-11).
-- Source of truth:
-- research_notes/taxmate-gate0-slab-verification-20261111-1152/report.md
-- (also docs/GATE0.md in this repo). Every figure below is copied from
-- that report, verified against primary sources (Finance Act gazettes,
-- FBR Withholding Tax Rate Cards, FBR Circulars). Do NOT edit a rate
-- from memory, a blog, or a calculator. Any future change needs a new
-- Gate 0 verification run first.
--
-- TY2026 salaried, Clause (2): 6 slabs, 0%-35%, surcharge 9% of tax
-- where taxable income exceeds Rs 10,000,000 (s.4AB proviso).
-- TY2027 salaried, Clause (2): 8 slabs, 0%-35%, NO surcharge for
-- salaried (withdrawn by Finance Act 2026).
-- Clause (1) (business + AOP, both years): 6 slabs, 0%-45%,
-- surcharge 10% of tax where taxable income exceeds Rs 10,000,000.
-- No verified rows for 'company': calculate still refuses for it.
-- All money is integer paisa (Rs 1 = 100 paisa). Slab bounds are
-- inclusive on both ends: Rs 600,000 is taxed at 0% (slab 1).
-- NOTE: no semicolons inside string literals (the migrate.php
-- splitter splits on semicolon + newline).

INSERT INTO tax_rules (tax_year_id, taxpayer_type, from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule, finance_act_source, is_verified) VALUES
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 0, 60000000, 0, 0, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 60000000, 120000000, 100, 0, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 120000000, 220000000, 1100, 600000, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 220000000, 320000000, 2300, 11600000, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 320000000, 410000000, 3000, 34600000, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'salaried', 410000000, NULL, 3500, 61600000, '{"threshold_paisa":1000000000,"rate_bps":900}', 'Finance Act 2025, First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2025', 1);

INSERT INTO tax_rules (tax_year_id, taxpayer_type, from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule, finance_act_source, is_verified) VALUES
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 0, 60000000, 0, 0, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 60000000, 120000000, 100, 0, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 120000000, 220000000, 1100, 600000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 220000000, 320000000, 2000, 11600000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 320000000, 410000000, 2500, 31600000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 410000000, 560000000, 2900, 54100000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 560000000, 700000000, 3200, 97600000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'salaried', 700000000, NULL, 3500, 142400000, NULL, 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(2), FBR WHT Rate Card 2027', 1);

INSERT INTO tax_rules (tax_year_id, taxpayer_type, from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule, finance_act_source, is_verified) VALUES
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 0, 60000000, 0, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 60000000, 120000000, 1500, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 120000000, 160000000, 2000, 9000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 160000000, 320000000, 3000, 17000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 320000000, 560000000, 4000, 65000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'business', 560000000, NULL, 4500, 161000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 0, 60000000, 0, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 60000000, 120000000, 1500, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 120000000, 160000000, 2000, 9000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 160000000, 320000000, 3000, 17000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 320000000, 560000000, 4000, 65000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2026'), 'aop', 560000000, NULL, 4500, 161000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2025, First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2025', 1);

INSERT INTO tax_rules (tax_year_id, taxpayer_type, from_paisa, to_paisa, rate_bps, base_tax_paisa, surcharge_rule, finance_act_source, is_verified) VALUES
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 0, 60000000, 0, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 60000000, 120000000, 1500, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 120000000, 160000000, 2000, 9000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 160000000, 320000000, 3000, 17000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 320000000, 560000000, 4000, 65000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'business', 560000000, NULL, 4500, 161000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 0, 60000000, 0, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 60000000, 120000000, 1500, 0, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 120000000, 160000000, 2000, 9000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 160000000, 320000000, 3000, 17000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 320000000, 560000000, 4000, 65000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1),
    ((SELECT id FROM tax_years WHERE label = 'TY2027'), 'aop', 560000000, NULL, 4500, 161000000, '{"threshold_paisa":1000000000,"rate_bps":1000}', 'Finance Act 2026 (Act XLIII of 2026), First Schedule Div I Pt I cl.(1), FBR WHT Rate Card 2027', 1);
