@props(['href', 'active' => false, 'icon' => ''])

<a href="{{ $href }}"
   class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] transition-colors font-medium"
   style="{{ $active
       ? 'background:rgba(59,130,246,.15);color:#ffffff;border-left:3px solid #3B82F6;'
       : 'color:#9CA3AF;border-left:3px solid transparent;' }}"
   onmouseover="if({{ $active ? 'false' : 'true' }}) { this.style.background='rgba(255,255,255,.05)'; this.style.color='#ffffff'; }"
   onmouseout="if({{ $active ? 'false' : 'true' }}) { this.style.background='transparent'; this.style.color='#9CA3AF'; }">
    @if($icon)
        <span class="material-symbols-outlined" style="font-size:18px">{{ $icon }}</span>
    @endif
    <span>{{ $slot }}</span>
</a>



