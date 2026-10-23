@props(['name' => 'dashboard'])

<svg {{ $attributes->class(['dpe-dashboard-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('building')
            <path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h3a1 1 0 0 1 1 1v11M2 21h20M8 7h2m-2 4h2m-2 4h2m4-8h0m0 4h0m0 4h0"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m0-13a3 3 0 0 1 0 6m3 1a6 6 0 0 1 3 6"/>
            @break
        @case('student')
            <path d="m3 9 9-5 9 5-9 5-9-5Z"/><path d="M7 11v5c2 2 8 2 10 0v-5M21 10v6"/>
            @break
        @case('teacher')
            <circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0M12 11v5m-3 0h6"/>
            @break
        @case('calendar')
            <rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 9h18M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('book')
            <path d="M4 4c3-1 6 0 8 2 2-2 5-3 8-2v16c-3-1-6 0-8 2-2-2-5-3-8-2V4Z"/><path d="M12 6v14"/>
            @break
        @case('video')
            <rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3M8 10l4 2-4 2v-4Z"/>
            @break
        @case('report')
            <path d="M5 3h10l4 4v14H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M15 3v5h5M7 13h8M7 17h6M7 9h2"/>
            @break
        @case('wallet')
            <path d="M4 5h15a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M2 7h17M16 13h5v4h-5a2 2 0 0 1 0-4Z"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
            @break
        @case('settings')
            <path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1a2 2 0 1 1-2.8 2.8l-.1-.1a2 2 0 0 0-3.4 1.4v.2a2 2 0 1 1-4 0v-.2a2 2 0 0 0-3.4-1.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A2 2 0 0 0 3.7 12a2 2 0 0 0-.1-.7m.1-2.8-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A2 2 0 0 0 9.9 4.3h.2a2 2 0 1 1 4 0v.2a2 2 0 0 0 3.4 1.4l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1A2 2 0 0 0 20.3 12c0 .2 0 .5.1.7"/>
            @break
        @case('globe')
            <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('close')
            <path d="m6 6 12 12M6 18 18 6"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('chevron-right')
            <path d="m9 6 6 6-6 6"/>
            @break
        @case('arrow')
            <path d="M5 12h14m-6-6 6 6-6 6"/>
            @break
        @case('back')
            <path d="M19 12H5m6-6-6 6 6 6"/>
            @break
        @case('logout')
            <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5M15 16l4-4-4-4M19 12H9"/>
            @break
        @case('refresh')
            <path d="M20 11a8 8 0 0 0-14.8-4L3 9m0 0V4m0 5h5M4 13a8 8 0 0 0 14.8 4L21 15m0 0v5m0-5h-5"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>
            @break
        @case('warning')
            <path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v4m0 4h.01"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>
            @break
        @case('more')
            <circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
        @case('edit')
            <path d="m4 16.5-.8 3.3 3.3-.8L18.8 6.7a2.1 2.1 0 0 0-3-3L4 16.5Z"/><path d="m14.5 5.5 4 4"/>
            @break
        @case('camera')
            <path d="M4 7h3l1.5-2h7L17 7h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.5"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('trash')
            <path d="M4 7h16m-10 4v6m4-6v6M9 7V4h6v3m-9 0 1 14h10l1-14"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8v5m0 3h.01"/>
    @endswitch
</svg>
