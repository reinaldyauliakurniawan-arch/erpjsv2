#!/usr/bin/env python3
"""Apply text-xl font-bold → text-headline-md font-bold changes across 9 view files."""
import re
from pathlib import Path

FILES = [
    "resources/views/admin/reports/balance_sheet.blade.php",
    "resources/views/admin/reports/profit_loss.blade.php",
    "resources/views/admin/reports/trial_balance.blade.php",
    "resources/views/admin/reports/adjusted_trial_balance.blade.php",
    "resources/views/admin/reports/general_ledger.blade.php",
    "resources/views/admin/reports/cash_flow.blade.php",
    "resources/views/admin/reports/equity_statement.blade.php",
    "resources/views/admin/reports/fixed_assets.blade.php",
    "resources/views/admin/rab-realisasi/index.blade.php",
    "resources/views/admin/rab/index.blade.php",
]

total_changes = 0
for f in FILES:
    p = Path(f)
    if not p.exists():
        print(f"  SKIP (not found): {f}")
        continue
    original = p.read_text(encoding="utf-8")
    # Replace 'text-xl font-bold' → 'text-headline-md font-bold' (word-boundary safe)
    new_content, n = re.subn(r'\btext-xl font-bold\b', 'text-headline-md font-bold', original)
    if n:
        p.write_text(new_content, encoding="utf-8")
        print(f"  updated {f}: {n} replacement(s)")
        total_changes += n
    else:
        print(f"  no changes: {f}")

print(f"\nTotal: {total_changes} replacements across {len(FILES)} files")
