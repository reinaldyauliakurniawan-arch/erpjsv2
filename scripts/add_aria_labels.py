#!/usr/bin/env python3
"""
Add aria-label to icon-only buttons (buttons where the only visible content
is a Material Symbols Outlined icon span, which uses ligatures — the icon
name text inside the span becomes the rendered icon glyph, so screen readers
announce nothing useful).

For each icon-only button, we extract the icon name from the span text
(e.g. "edit", "delete", "close") and use it as the aria-label.

Run from project root:
    python3 scripts/add_aria_labels.py [--apply]
"""
import re
import sys
from pathlib import Path

VIEWS = Path("resources/views")
APPLY = "--apply" in sys.argv

BUTTON_RE = re.compile(r"<button\b[^>]*?>.*?</button>", re.DOTALL)
ICON_SPAN_RE = re.compile(
    r'<span\s+[^>]*class="[^"]*material-symbols-outlined[^"]*"[^>]*>([^<]*)</span>'
)

# Friendly Indonesian labels for common Material Symbols icon names
ICON_LABELS = {
    "edit": "Edit",
    "delete": "Hapus",
    "remove": "Hapus",
    "close": "Tutup",
    "clear": "Bersihkan",
    "add": "Tambah",
    "save": "Simpan",
    "cancel": "Batal",
    "menu": "Buka menu",
    "search": "Cari",
    "filter": "Filter",
    "visibility": "Lihat detail",
    "open_in_new": "Buka di tab baru",
    "download": "Unduh",
    "upload": "Unggah",
    "person_add": "Tambah user",
    "settings": "Pengaturan",
    "logout": "Keluar",
    "logout_rounded": "Keluar",
    "manage_accounts": "Profil",
    "expand_more": "Lihat lebih banyak",
    "expand_less": "Lihat lebih sedikit",
    "chevron_left": "Sebelumnya",
    "chevron_right": "Berikutnya",
    "navigate_before": "Sebelumnya",
    "navigate_next": "Berikutnya",
    "arrow_back": "Kembali",
    "arrow_forward": "Lanjut",
    "check": "Pilih",
    "check_circle": "Selesai",
    "warning": "Peringatan",
    "error": "Error",
    "info": "Informasi",
    "help": "Bantuan",
    "more_vert": "Opsi lainnya",
    "more_horiz": "Opsi lainnya",
    "refresh": "Muat ulang",
    "sync": "Sinkronkan",
    "print": "Cetak",
    "share": "Bagikan",
    "content_copy": "Salin",
    "link": "Buka tautan",
    "tune": "Atur filter",
    "restart_alt": "Reset",
    "first_page": "Halaman pertama",
    "last_page": "Halaman terakhir",
    "fullscreen": "Layar penuh",
    "fullscreen_exit": "Keluar layar penuh",
}


def get_icon_label(inner: str) -> str | None:
    """Return a human-readable label based on the icon name inside the span."""
    m = ICON_SPAN_RE.search(inner)
    if not m:
        return None
    icon_name = m.group(1).strip()
    return ICON_LABELS.get(icon_name, icon_name.capitalize() if icon_name else None)


def process_file(path: Path) -> int:
    original = path.read_text(encoding="utf-8")
    content = original
    changes = 0

    def fix_button(m: re.Match) -> str:
        nonlocal changes
        full_tag = m.group(0)
        first_gt = full_tag.index(">")
        attrs_part = full_tag[:first_gt + 1]
        inner = full_tag[first_gt + 1:full_tag.rindex("</button>")]

        # Skip if already labeled
        if "aria-label" in attrs_part or "title=" in attrs_part:
            return full_tag

        # Strip material-symbols-outlined spans entirely to check for visible text
        stripped = re.sub(
            r'<span\s+[^>]*class="[^"]*material-symbols-outlined[^"]*"[^>]*>[^<]*</span>',
            '',
            inner,
            flags=re.DOTALL,
        )
        stripped = re.sub(r'<svg\b[^>]*>.*?</svg>', '', stripped, flags=re.DOTALL)
        stripped = re.sub(r"@\w+(?:\([^)]*\))?", '', stripped)
        stripped = re.sub(r"\{\{[^}]*\}\}", '', stripped)
        text_only = re.sub(r"<[^>]+>", "", stripped).strip()

        has_icon = "material-symbols-outlined" in inner
        if not (has_icon and not text_only):
            return full_tag

        # Get label
        label = get_icon_label(inner)
        if not label:
            return full_tag

        # Add aria-label right after <button
        new_attrs = attrs_part.replace(
            "<button",
            f'<button aria-label="{label}"',
            1,
        )
        changes += 1
        return new_attrs + full_tag[first_gt + 1:]

    new_content = BUTTON_RE.sub(fix_button, content)
    if new_content != content:
        if APPLY:
            path.write_text(new_content, encoding="utf-8")
        return changes
    return 0


def main():
    total = 0
    for blade in VIEWS.rglob("*.blade.php"):
        n = process_file(blade)
        if n:
            total += n
            action = "would update" if not APPLY else "updated"
            print(f"  {action}: {blade.relative_to('.')} (+{n} aria-label)")
    print()
    print(f"Total: {total} icon-only buttons {'would be ' if not APPLY else ''}labeled")
    if not APPLY:
        print("\n(dry run — re-run with --apply to make changes)")


if __name__ == "__main__":
    main()
