@props(['href', 'active' => false, 'icon' => ''])

<a href="{{ $href }}"
   class="sidebar-link {{ $active ? 'sidebar-link-active' : '' }}"
   @if($active) aria-current="page" @endif>
    @if($icon)
        <span class="material-symbols-outlined">{{ $icon }}</span>
    @endif
    <span>{{ $slot }}</span>
</a>
