@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-sm pe-md py-sm border-l-4 border-secondary text-start text-base font-medium text-secondary bg-secondary-container focus:outline-none focus:text-on-secondary-container focus:bg-secondary-container focus:border-secondary transition-[border-color,color,background-color] duration-150 ease-in-out cursor-pointer'
            : 'block w-full ps-sm pe-md py-sm border-l-4 border-transparent text-start text-base font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low hover:border-surface-border focus:outline-none focus:text-on-surface focus:bg-surface-container-low focus:border-surface-border transition-[border-color,color,background-color] duration-150 ease-in-out cursor-pointer';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
