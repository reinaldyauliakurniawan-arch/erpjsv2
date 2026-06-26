<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center px-md py-sm bg-error text-on-error border border-transparent rounded-md font-semibold text-label-lg uppercase tracking-widest hover:opacity-90 active:opacity-100 focus:outline-none focus:ring-2 focus:ring-error focus:ring-offset-2 transition-[opacity] ease-in-out cursor-pointer',
]) }}>
    {{ $slot }}
</button>
