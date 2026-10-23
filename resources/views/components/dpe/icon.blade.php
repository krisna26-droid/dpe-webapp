@props(['name' => 'book'])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('arrow') <path d="M5 12h14m-6-6 6 6-6 6"/> @break
        @case('back') <path d="M19 12H5m6-6-6 6 6 6"/> @break
        @case('pin') <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('message') <path d="M21 11.5a8.5 8.5 0 0 1-12 7.8L3 21l1.7-6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8 9h8m-8 4h5"/> @break
        @case('check') <path d="m5 12 4 4L19 6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/> @break
        @case('phone') <path d="m5 3 4 1 1 5-3 2a15 15 0 0 0 6 6l2-3 5 1 1 4c0 2-2 2-3 2A19 19 0 0 1 3 6c0-1 0-3 2-3Z"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('close') <path d="m6 6 12 12M6 18 18 6"/> @break
        @case('eye') <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/> @break
        @case('target') <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/> @break
        @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m0-16a3 3 0 0 1 0 6m3 4a6 6 0 0 1 3 6"/> @break
        @case('spark') <path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/> @break
        @case('external') <path d="M14 3h7v7m0-7L10 14m0-10H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-5"/> @break
        @default <path d="M12 5v15M3 4c4-1 7 0 9 2 2-2 5-3 9-2v15c-4-1-7 0-9 2-2-2-5-3-9-2V4Z"/>
    @endswitch
</svg>
