<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center px-lg py-sm bg-primary text-on-primary text-body-lg font-medium rounded-full transition-opacity hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-40 cursor-pointer',
]) }}>
    {{ $slot }}
</button>
