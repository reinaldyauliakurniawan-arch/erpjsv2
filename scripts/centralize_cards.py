#!/usr/bin/env python3
"""
Centralize card patterns in Blade views.

Replaces repeated utility-class card patterns with the centralized
`.app-card` component class defined in resources/css/app.css.

Per Brand Personality Guide Section 13:
  Card pattern = bg-surface-bright + border + radius-lg + shadow-sm + p-lg

Strategy: replace ONLY the exact 4-class substring (keeping any other
classes intact), then optionally collapse the redundant `p-lg` /
`overflow-hidden` follow-up classes.

Run from project root:
    python3 scripts/centralize_cards.py
"""
import re
import sys
from pathlib import Path

VIEWS_DIR = Path("resources/views")
DRY_RUN = "--dry-run" in sys.argv

# Patterns to replace — order matters (longer patterns first)
PATTERNS = [
    # 1) Card with p-lg (default padding matches .app-card)
    (
        re.compile(
            r"bg-surface-container-lowest\s+border\s+border-surface-border\s+rounded-lg\s+shadow-sm\s+p-lg"
        ),
        "app-card",
    ),
    # 2) Card with overflow-hidden (use flush variant — no padding so children can fill)
    (
        re.compile(
            r"bg-surface-container-lowest\s+border\s+border-surface-border\s+rounded-lg\s+shadow-sm\s+overflow-hidden"
        ),
        "app-card app-card--flush",
    ),
    # 3) Bare card (no padding, no overflow)
    (
        re.compile(
            r"bg-surface-container-lowest\s+border\s+border-surface-border\s+rounded-lg\s+shadow-sm"
        ),
        "app-card",
    ),
    # 4) Card header pattern (very common in admin views)
    (
        re.compile(
            r"px-lg\s+py-md\s+border-b\s+border-surface-border\s+flex\s+justify-between\s+items-center\s+bg-surface-container-low"
        ),
        "app-card__header",
    ),
    # 5) Card header with `bg-surface-container-low` first (variant)
    (
        re.compile(
            r"bg-surface-container-low\s+px-lg\s+py-md\s+border-b\s+border-surface-border\s+flex\s+justify-between\s+items-center"
        ),
        "app-card__header",
    ),
    # 6) Card footer pattern
    (
        re.compile(
            r"px-lg\s+py-md\s+bg-surface-container-low\s+border-t\s+border-surface-border"
        ),
        "app-card__footer",
    ),
    # 7) Decorative green-tinted icon containers — per Brand Guide Section 3,
    #    green is identity not decoration. Replace with neutral .app-icon-badge
    (
        re.compile(r"w-10\s+h-10\s+rounded-lg\s+bg-secondary/10\s+flex\s+items-center\s+justify-center"),
        "app-icon-badge",
    ),
    (
        re.compile(r"w-10\s+h-10\s+rounded-lg\s+bg-secondary/20\s+flex\s+items-center\s+justify-center"),
        "app-icon-badge",
    ),
    # 8) Decorative green-tinted avatars (32px) in tables
    (
        re.compile(r"w-8\s+h-8\s+rounded-full\s+bg-secondary/20\s+flex\s+items-center\s+justify-center\s+font-bold\s+text-secondary\s+text-xs\s+shrink-0"),
        "app-avatar app-avatar--sm",
    ),
    (
        re.compile(r"w-9\s+h-9\s+rounded-full\s+bg-secondary/20\s+flex\s+items-center\s+justify-center\s+font-bold\s+text-secondary\s+text-xs\s+shrink-0"),
        "app-avatar app-avatar--sm",
    ),
]


def cleanup_class_attrs(content: str) -> str:
    """Collapse multiple spaces and trim inside class="..." attributes."""
    def fix(match: re.Match) -> str:
        prefix, val, suffix = match.group(1), match.group(2), match.group(3)
        val = re.sub(r"\s+", " ", val).strip()
        return f"{prefix}{val}{suffix}"

    return re.sub(
        r'(class=["\'])\s*([^"\']*?)\s*(["\'])',
        fix,
        content,
    )


def process_file(path: Path) -> int:
    """Returns number of replacements made."""
    original = path.read_text(encoding="utf-8")
    content = original
    total_replacements = 0

    for pattern, replacement in PATTERNS:
        new_content, count = pattern.subn(replacement, content)
        if count:
            total_replacements += count
            content = new_content

    if total_replacements == 0:
        return 0

    content = cleanup_class_attrs(content)

    if not DRY_RUN:
        path.write_text(content, encoding="utf-8")

    return total_replacements


def main() -> None:
    total_files = 0
    total_replacements = 0
    for blade_file in VIEWS_DIR.rglob("*.blade.php"):
        n = process_file(blade_file)
        if n:
            total_files += 1
            total_replacements += n
            rel = blade_file.relative_to(".")
            action = "would update" if DRY_RUN else "updated"
            print(f"  {action}: {rel} ({n} replacements)")

    print()
    print(f"Total files {'that would be ' if DRY_RUN else ''}updated: {total_files}")
    print(f"Total replacements: {total_replacements}")
    if DRY_RUN:
        print("\n(dry run — no files were modified)")


if __name__ == "__main__":
    main()
