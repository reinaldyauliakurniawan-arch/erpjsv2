#!/usr/bin/env python3
"""
Wrap unwrapped HTML tables in .app-table-wrapper divs for rounded corners.

Per Brand Guide Section 2: "Fun & energik" personality — sharp 90° corners
feel sterile. HTML <table> default = sharp corners. This script finds
tables NOT already wrapped in a rounded container and wraps them in
<div class="app-table-wrapper">.

Skip tables that are:
- Already inside an .app-card--flush (already has rounded corners + overflow hidden)
- Already inside an element with 'rounded' class
- Inside a <dialog> (DaisyUI modal — already styled)
- Tabulator target divs (those use #id selectors, not <table> tags)

Run from project root:
    python3 scripts/wrap_tables.py [--apply]
"""
import re
import sys
from pathlib import Path

VIEWS = Path("resources/views")
APPLY = "--apply" in sys.argv


def find_unwrapped_tables(content: str):
    """Return list of (start, end) offsets for tables that need wrapping."""
    results = []
    for m in re.finditer(r"<table\b[^>]*>", content):
        # Look back 500 chars for a wrapper context
        ctx_start = max(0, m.start() - 500)
        ctx = content[ctx_start:m.start()]

        # Skip if already in a rounded wrapper or app-card--flush
        if "app-card--flush" in ctx:
            continue
        if "app-table-wrapper" in ctx:
            continue
        # Check for any rounded-* class in the immediate parent div
        # (look at the last opened div before the table)
        last_div = ctx.rfind("<div")
        if last_div != -1:
            div_ctx = ctx[last_div:]
            if re.search(r"\brounded(?:-lg|-xl|-md|-full|-sm)?\b", div_ctx):
                continue
            if "overflow-hidden" in div_ctx and "rounded" in div_ctx:
                continue
        # Skip tables inside <dialog> (modal — DaisyUI handles styling)
        # Check if we're inside an open <dialog> that hasn't closed
        dialog_opens = ctx.count("<dialog")
        dialog_closes = ctx.count("</dialog>")
        if dialog_opens > dialog_closes:
            continue

        # Find matching </table>
        end_match = re.search(r"</table>", content[m.end():])
        if not end_match:
            continue
        end_pos = m.end() + end_match.end()
        results.append((m.start(), end_pos))
    return results


def process_file(path: Path) -> int:
    original = path.read_text(encoding="utf-8")
    ranges = find_unwrapped_tables(original)
    if not ranges:
        return 0

    # Apply wraps in reverse order to keep offsets valid
    content = original
    changes = 0
    for start, end in reversed(ranges):
        table_block = content[start:end]
        # Preserve leading whitespace
        ws_match = re.match(r"^(\s*)", table_block)
        leading_ws = ws_match.group(1) if ws_match else ""
        table_no_ws = table_block[len(leading_ws):]
        new_block = f'{leading_ws}<div class="app-table-wrapper">\n{table_no_ws}\n</div>'
        content = content[:start] + new_block + content[end:]
        changes += 1

    if APPLY and changes:
        path.write_text(content, encoding="utf-8")
    return changes


def main():
    total = 0
    for blade in VIEWS.rglob("*.blade.php"):
        n = process_file(blade)
        if n:
            total += n
            action = "would wrap" if not APPLY else "wrapped"
            print(f"  {action}: {blade.relative_to('.')} (+{n} tables)")
    print()
    print(f"Total: {total} tables {'would be ' if not APPLY else ''}wrapped")
    if not APPLY:
        print("\n(dry run — re-run with --apply to make changes)")


if __name__ == "__main__":
    main()
