<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex items-center justify-center px-lg py-sm bg-surface-container-lowest text-secondary text-body-lg font-medium rounded-full border border-surface-border transition-colors hover:bg-surface-container-low focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary disabled:opacity-40 cursor-pointer',
]) }}>
    {{ $slot }}
</button>
