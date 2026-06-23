@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'w-full px-md py-sm bg-surface-container-lowest text-on-surface text-body-lg border border-surface-border rounded-md placeholder:text-on-surface-variant focus:outline-none focus:border-secondary focus:ring-1 focus:ring-secondary disabled:opacity-40 transition-colors',
]) }}>
