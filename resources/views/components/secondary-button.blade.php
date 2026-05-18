<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-5 py-2 bg-surface-white text-oceanic-deep text-[14px] font-medium rounded-full border border-spring-leaf transition-colors hover:bg-pale-mint focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-spring-leaf disabled:opacity-40']) }}>
    {{ $slot }}
</button>



