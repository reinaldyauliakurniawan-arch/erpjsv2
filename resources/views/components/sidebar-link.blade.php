@props(['href', 'active' => false, 'icon' => ''])

<a href="{{ $href }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-colors font-medium sidebar-link {{ $active ? 'sidebar-link-active' : '' }}"
   style="border-left:3px solid {{ $active ? 'var(--sidebar-accent)' : 'transparent' }}">
    @if($icon)
        <span class="material-symbols-outlined" style="font-size:18px">{{ $icon }}</span>
    @endif
    <span>{{ $slot }}</span>
</a>
