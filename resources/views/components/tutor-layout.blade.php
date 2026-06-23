{{--
    Tutor layout — delegates to the centralized app layout.
    The app layout already renders the tutor sidebar based on the
    authenticated user's role, so we just pass through the title/slot.
    This eliminates the previous duplication where tutor pages had a
    different sidebar style/width/nav items from the rest of the app.
--}}
@props(['title' => 'Tutor'])

<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    {{ $slot }}
</x-app-layout>
