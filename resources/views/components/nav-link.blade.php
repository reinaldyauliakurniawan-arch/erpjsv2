@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-xs pt-xs border-b-2 border-secondary text-body-md font-medium leading-5 text-on-surface focus:outline-none focus:border-secondary transition-[border-color,color] duration-150 ease-in-out cursor-pointer'
            : 'inline-flex items-center px-xs pt-xs border-b-2 border-transparent text-body-md font-medium leading-5 text-on-surface-variant hover:text-on-surface hover:border-surface-border focus:outline-none focus:text-on-surface focus:border-surface-border transition-[border-color,color] duration-150 ease-in-out cursor-pointer';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
