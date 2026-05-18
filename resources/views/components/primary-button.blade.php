<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2 bg-midnight-ink text-surface-white text-[14px] font-medium rounded-full transition-opacity hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-midnight-ink disabled:opacity-40']) }}>
    {{ $slot }}
</button>



