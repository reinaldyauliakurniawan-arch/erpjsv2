@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full px-3 py-2 bg-surface-white text-midnight-ink text-[14px] border border-[#0b363b33] rounded-[2px] placeholder-ash-cloud focus:outline-none focus:border-oceanic-deep focus:ring-1 focus:ring-oceanic-deep disabled:opacity-40 transition-colors']) }}>



