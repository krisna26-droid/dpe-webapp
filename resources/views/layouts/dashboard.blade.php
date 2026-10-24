@php
    $user = auth()->user();
    $roleCode = $user?->role_code ?? 'student';
    $roleMeta = [
        'superadmin' => [
            'label' => 'Super Admin',
            'short' => 'SA',
            'description' => 'Kontrol pusat seluruh cabang',
        ],
        'admin' => [
            'label' => 'Admin Cabang',
            'short' => 'AC',
            'description' => 'Operasional cabang belajar',
        ],
        'teacher' => [
            'label' => 'Teacher',
            'short' => 'GR',
            'description' => 'Ruang kerja pengajar',
        ],
        'student' => [
            'label' => 'Siswa / Parent',
            'short' => 'SP',
            'description' => 'Perjalanan belajar siswa',
        ],
    ];
    $role = $roleMeta[$roleCode] ?? $roleMeta['student'];
    $dashboardRoute = $roleCode.'.dashboard';
    $dashboardUrl = \Illuminate\Support\Facades\Route::has($dashboardRoute)
        ? route($dashboardRoute)
        : url('/');
    $userName = trim((string) ($user?->full_name ?: $user?->username ?: 'Pengguna'));
    $userEmail = trim((string) ($user?->email ?: ''));
    $initials = mb_strtoupper(mb_substr($userName, 0, 2));
    $profilePhotoId = $roleCode === 'teacher' && $user ? $user->teacherProfile()->value('photo_file_id') : null;
    $profilePhotoUrl = $profilePhotoId ? route('teacher.profile.photo', ['v' => $profilePhotoId]) : null;
    $productName = 'DPE Learning Center';

    $menus = [
        'superadmin' => [
            [
                'label' => 'Workspace',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'superadmin.dashboard'],
                ],
            ],
            [
                'label' => 'Operasional',
                'items' => [
                    ['label' => 'Cabang', 'icon' => 'building', 'route' => 'superadmin.branches.index', 'active' => ['superadmin.branches.*']],
                    ['label' => 'Pengguna', 'icon' => 'users', 'route' => 'superadmin.users.index', 'active' => ['superadmin.users.*']],
                    ['label' => 'Admin Cabang', 'icon' => 'teacher', 'route' => 'superadmin.branch-admin-assignments.index', 'active' => ['superadmin.branch-admin-assignments.*']],
                    ['label' => 'Guru & Penugasan', 'icon' => 'teacher', 'route' => 'superadmin.teacher-branch-assignments.index', 'active' => ['superadmin.teacher-branch-assignments.*']],
                    ['label' => 'Siswa & Pendaftaran', 'icon' => 'student', 'route' => 'superadmin.students.index', 'active' => ['superadmin.students.*', 'superadmin.student-enrollments.*', 'superadmin.student-teacher-assignments.*']],
                    ['label' => 'Kelompok Kelas', 'icon' => 'users', 'route' => 'superadmin.class-groups.index', 'active' => ['superadmin.class-groups.*', 'superadmin.class-group-memberships.*']],
                    ['label' => 'Sesi Belajar', 'icon' => 'calendar', 'route' => 'superadmin.lesson-sessions.index', 'active' => ['superadmin.lesson-sessions.*', 'superadmin.lesson-series.*']],
                    ['label' => 'Kehadiran Guru', 'icon' => 'clock', 'route' => 'admin.teacher-attendance.index', 'active' => ['admin.teacher-attendance.*']],
                    ['label' => 'Kehadiran Siswa', 'icon' => 'student', 'route' => 'superadmin.student-attendance.index', 'active' => ['superadmin.student-attendance.*']],
                    ['label' => 'Ketersediaan Guru', 'icon' => 'clock', 'route' => 'superadmin.teacher-availability.index', 'active' => ['superadmin.teacher-availability.*']],
                ],
            ],
            [
                'label' => 'Pembelajaran',
                'items' => [
                    ['label' => 'Catatan Belajar', 'icon' => 'book', 'route' => 'superadmin.learning-notes.index', 'active' => ['superadmin.learning-notes.*']],
                    ['label' => 'Perkembangan & Pesan', 'icon' => 'report', 'route' => 'superadmin.student-progress.index', 'active' => ['superadmin.student-progress.*']],
                    ['label' => 'Program Belajar', 'icon' => 'book', 'route' => 'superadmin.programs.index', 'active' => ['superadmin.programs.*']],
                    ['label' => 'Video Belajar', 'icon' => 'video', 'route' => 'superadmin.learning-videos.index', 'active' => ['superadmin.learning-videos.*']],
                    ['label' => 'Kompetensi & Skill', 'icon' => 'report', 'route' => 'superadmin.learning-skills.index', 'active' => ['superadmin.learning-skills.*']],
                    ['label' => 'Tantangan Siswa', 'icon' => 'report', 'route' => 'superadmin.student-challenges.index', 'active' => ['superadmin.student-challenges.*']],
                    ['label' => 'Pesan Guru', 'icon' => 'bell', 'route' => 'superadmin.teacher-messages.index', 'active' => ['superadmin.teacher-messages.*']],
                    ['label' => 'Wali Siswa', 'icon' => 'users', 'route' => 'superadmin.guardians.index', 'active' => ['superadmin.guardians.*']],
                ],
            ],
            [
                'label' => 'Laporan & Keuangan',
                'items' => [
                    ['label' => 'Siklus Laporan', 'icon' => 'report', 'route' => 'admin.report-cycles.index', 'active' => ['admin.report-cycles.*']],
                    ['label' => 'Laporan Bulanan', 'icon' => 'report', 'route' => 'superadmin.monthly-reports.index', 'active' => ['superadmin.monthly-reports.*', 'admin.monthly-reports.*']],
                    ['label' => 'Tagihan Bulanan', 'icon' => 'wallet', 'route' => 'superadmin.monthly-charges.index', 'active' => ['superadmin.monthly-charges.*']],
                    ['label' => 'Review Pembayaran', 'icon' => 'wallet', 'route' => 'superadmin.payment-review.index', 'active' => ['superadmin.payment-review.*']],
                    ['label' => 'Rapor Tiga Bulan', 'icon' => 'report', 'route' => 'superadmin.quarterly-reports.index', 'active' => ['superadmin.quarterly-reports.*', 'superadmin.report-template.*', 'admin.quarterly-reports.*']],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    ['label' => 'Konten Publik', 'icon' => 'globe', 'route' => 'superadmin.website.index', 'active' => ['superadmin.website.*']],
                    ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'inbox.index', 'active' => ['inbox.*']],
                    ['label' => 'Pengaturan Sistem', 'icon' => 'settings', 'route' => 'superadmin.settings.index', 'active' => ['superadmin.settings.*']],
                    ['label' => 'Pengaturan Portal', 'icon' => 'settings', 'route' => 'superadmin.portal-settings.index', 'active' => ['superadmin.portal-settings.*']],
                ],
            ],
        ],
        'admin' => [
            [
                'label' => 'Workspace',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard'],
                ],
            ],
            [
                'label' => 'Operasional Cabang',
                'items' => [
                    ['label' => 'Pengguna', 'icon' => 'users', 'route' => 'admin.users.index', 'active' => ['admin.users.*']],
                    ['label' => 'Data Siswa', 'icon' => 'student', 'route' => 'admin.students.index', 'active' => ['admin.students.*']],
                    ['label' => 'Data Guru', 'icon' => 'teacher', 'route' => 'admin.teachers.index', 'active' => ['admin.teachers.*']],
                    ['label' => 'Kelompok Kelas', 'icon' => 'users', 'route' => 'admin.class-groups.index', 'active' => ['admin.class-groups.*', 'admin.class-group-memberships.*']],
                    ['label' => 'Jadwal & Sesi', 'icon' => 'calendar', 'route' => 'admin.lesson-sessions.index', 'active' => ['admin.lesson-sessions.*', 'admin.lesson-series.*']],
                    ['label' => 'Kehadiran Guru', 'icon' => 'clock', 'route' => 'admin.teacher-attendance.index', 'active' => ['admin.teacher-attendance.*']],
                    ['label' => 'Kehadiran Siswa', 'icon' => 'student', 'route' => 'admin.student-attendance.index', 'active' => ['admin.student-attendance.*']],
                    ['label' => 'Catatan Belajar', 'icon' => 'book', 'route' => 'admin.learning-notes.index', 'active' => ['admin.learning-notes.*']],
                    ['label' => 'Perkembangan & Pesan', 'icon' => 'report', 'route' => 'admin.student-progress.index', 'active' => ['admin.student-progress.*']],
                ],
            ],
            [
                'label' => 'Laporan & Keuangan',
                'items' => [
                    ['label' => 'Siklus Laporan', 'icon' => 'report', 'route' => 'admin.report-cycles.index', 'active' => ['admin.report-cycles.*']],
                    ['label' => 'Tinjau Laporan Bulanan', 'icon' => 'report', 'route' => 'admin.monthly-reports.index', 'active' => ['admin.monthly-reports.*']],
                    ['label' => 'Tinjau Rapor Tiga Bulan', 'icon' => 'report', 'route' => 'admin.quarterly-reports.index', 'active' => ['admin.quarterly-reports.*']],
                    ['label' => 'Pembayaran', 'icon' => 'wallet', 'route' => 'admin.payments.index', 'active' => ['admin.payments.*']],
                ],
            ],
            [
                'label' => 'Lainnya',
                'items' => [
                    ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'inbox.index', 'active' => ['inbox.*']],
                    ['label' => 'Pengaturan Cabang', 'icon' => 'settings', 'route' => 'admin.branch-settings.index', 'active' => ['admin.branch-settings.*']],
                ],
            ],
        ],
        'teacher' => [
            [
                'label' => 'Workspace',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'teacher.dashboard'],
                ],
            ],
            [
                'label' => 'Kelas Saya',
                'items' => [
                    ['label' => 'Jadwal Mengajar', 'icon' => 'calendar', 'route' => 'teacher.schedule.index', 'active' => ['teacher.schedule.*', 'teacher.availability.*']],
                    ['label' => 'Siswa Binaan', 'icon' => 'student', 'route' => 'teacher.students.index', 'active' => ['teacher.students.*']],
                    ['label' => 'Absensi Siswa', 'icon' => 'student', 'route' => 'teacher.student-attendance.index', 'active' => ['teacher.student-attendance.*']],
                    ['label' => 'Kehadiran', 'icon' => 'clock', 'route' => 'teacher.attendance.index', 'active' => ['teacher.attendance.*']],
                    ['label' => 'Catatan Belajar', 'icon' => 'book', 'route' => 'teacher.learning-notes.index', 'active' => ['teacher.learning-notes.*']],
                ],
            ],
            [
                'label' => 'Materi & Laporan',
                'items' => [
                    ['label' => 'Perkembangan & Pesan', 'icon' => 'report', 'route' => 'teacher.student-progress.index', 'active' => ['teacher.student-progress.*']],
                    ['label' => 'Laporan Bulanan', 'icon' => 'report', 'route' => 'teacher.monthly-reports.index', 'active' => ['teacher.monthly-reports.*']],
                    ['label' => 'Rapor Tiga Bulan', 'icon' => 'report', 'route' => 'teacher.quarterly-reports.index', 'active' => ['teacher.quarterly-reports.*']],
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    ['label' => 'Profil Saya', 'icon' => 'teacher', 'route' => 'teacher.profile.show', 'active' => ['teacher.profile.*']],
                    ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'inbox.index', 'active' => ['inbox.*']],
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'route' => 'teacher.settings.index', 'active' => ['teacher.settings.*']],
                ],
            ],
        ],
        'student' => [
            [
                'label' => 'Workspace',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'student.dashboard'],
                ],
            ],
            [
                'label' => 'Perjalanan Belajar',
                'items' => [
                    ['label' => 'Jadwal Belajar', 'icon' => 'calendar', 'route' => 'student.schedule'],
                    ['label' => 'Riwayat Belajar', 'icon' => 'book', 'route' => 'student.history'],
                    ['label' => 'Absensi Tiga Bulan', 'icon' => 'calendar', 'route' => 'student.attendance'],
                    ['label' => 'Rapor Siswa', 'icon' => 'report', 'route' => 'student.reports.index', 'active' => ['student.reports.*']],
                ],
            ],
            [
                'label' => 'Pembayaran',
                'items' => [
                    ['label' => 'Pembayaran', 'icon' => 'wallet', 'route' => 'student.payments.index', 'active' => ['student.payments.*']],
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    ['label' => 'Profil Siswa', 'icon' => 'student', 'route' => 'student.profile'],
                    ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'inbox.index', 'active' => ['inbox.*']],
                    ['label' => 'Pengaturan', 'icon' => 'settings', 'route' => 'student.settings.index', 'active' => ['student.settings.*']],
                ],
            ],
        ],
    ];
    if (in_array($roleCode, ['superadmin', 'admin', 'teacher'], true)) {
        $menus[$roleCode][0]['items'][] = ['label' => 'Monitoring Laporan', 'icon' => 'report', 'route' => 'monitoring.index', 'active' => ['monitoring.*']];
        $menus[$roleCode][0]['items'][] = ['label' => 'Data & Ekspor', 'icon' => 'book', 'route' => 'exports.index', 'active' => ['exports.*']];
    }
    $menuGroups = $menus[$roleCode] ?? $menus['student'];
    $cssVersion = is_file(public_path('assets/dpe/dashboard.css')) ? filemtime(public_path('assets/dpe/dashboard.css')) : 1;
    $jsVersion = is_file(public_path('assets/dpe/dashboard.js')) ? filemtime(public_path('assets/dpe/dashboard.js')) : 1;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1f55c7">
    <title>@yield('title', 'Dashboard') — {{ $productName }}</title>
    <link rel="stylesheet" href="{{ asset('assets/dpe/dashboard.css') }}?v={{ $cssVersion }}">
    <link rel="stylesheet" href="{{ asset('assets/dpe/operations.css') }}?v={{ filemtime(public_path('assets/dpe/operations.css')) }}">
    @if (request()->routeIs('admin.class-groups.*', 'admin.class-group-memberships.*', 'admin.lesson-sessions.*'))
        <link rel="stylesheet" href="{{ asset('assets/dpe/branch-operations.css') }}?v={{ filemtime(public_path('assets/dpe/branch-operations.css')) }}">
    @endif
    @stack('head')
    @if (request()->routeIs('superadmin.settings.*', 'admin.branch-settings.*', 'admin.payments.*'))
        <link rel="stylesheet" href="{{ asset('assets/dpe/dashboard-settings.css') }}?v={{ filemtime(public_path('assets/dpe/dashboard-settings.css')) }}">
    @endif
    @if (request()->routeIs('teacher.profile.*', 'teacher.settings.*', 'student.settings.*'))
        <link rel="stylesheet" href="{{ asset('assets/dpe/own-account.css') }}?v={{ filemtime(public_path('assets/dpe/own-account.css')) }}">
    @endif
    @if (request()->routeIs('teacher.schedule.*', 'teacher.students.*', 'teacher.availability.*'))
        <link rel="stylesheet" href="{{ asset('assets/dpe/teacher-workspace.css') }}?v={{ filemtime(public_path('assets/dpe/teacher-workspace.css')) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('assets/dpe/status-badges.css') }}?v={{ filemtime(public_path('assets/dpe/status-badges.css')) }}">
    <x-dpe.branding-assets />
</head>
<body class="dpe-dashboard-page">
    <a class="dpe-dashboard-skip" href="#dashboard-main">Lewati ke konten</a>

    <div class="dpe-dashboard-shell">
        <div class="dpe-dashboard-overlay" data-sidebar-overlay hidden></div>

        <aside class="dpe-dashboard-sidebar" id="dpe-dashboard-sidebar" data-sidebar data-sidebar-scroll-key="{{ hash('sha256', $roleCode.':'.($user?->id ?? '')) }}" aria-label="Navigasi dashboard">
            <div class="dpe-dashboard-sidebar__head">
                <a class="dpe-dashboard-brand" href="{{ $dashboardUrl }}" aria-label="Dharma Private English — Kembali ke dashboard">
                    <x-dpe.logo />
                    <span class="dpe-dashboard-brand__copy">
                        <strong>Learning Center</strong>
                        <small>Portal operasional</small>
                    </span>
                </a>
                <button class="dpe-dashboard-icon-button dpe-dashboard-icon-button--sidebar-close" type="button" data-sidebar-close aria-label="Tutup navigasi">
                    <x-dashboard.icon name="close" />
                </button>
            </div>

            <div class="dpe-dashboard-role-card">
                <span class="dpe-dashboard-role-card__badge" aria-hidden="true">{{ $role['short'] }}@if($roleCode === 'teacher')<img data-account-avatar-image @if($profilePhotoUrl) src="{{ $profilePhotoUrl }}" @else hidden @endif alt="" width="38" height="38">@endif</span>
                <span>
                    <small>Masuk sebagai</small>
                    <strong>{{ $role['label'] }}</strong>
                </span>
            </div>

            <nav class="dpe-dashboard-nav">
                @foreach ($menuGroups as $group)
                    <div class="dpe-dashboard-nav__group">
                        <p class="dpe-dashboard-nav__label">{{ $group['label'] }}</p>
                        <div class="dpe-dashboard-nav__items">
                            @foreach ($group['items'] as $item)
                                @php
                                    $routeName = $item['route'] ?? null;
                                    $available = ($item['available'] ?? true)
                                        && $routeName
                                        && \Illuminate\Support\Facades\Route::has($routeName);
                                    $patterns = $item['active'] ?? [];
                                    if ($routeName) {
                                        $patterns[] = $routeName;
                                    }
                                    $isActive = false;
                                    foreach ($patterns as $pattern) {
                                        if (request()->routeIs($pattern)) {
                                            $isActive = true;
                                            break;
                                        }
                                    }
                                    $itemUrl = $available ? route($routeName) : '#';
                                @endphp
                                <a
                                    class="dpe-dashboard-nav__item{{ $isActive ? ' is-active' : '' }}{{ $available ? '' : ' is-disabled' }}"
                                    href="{{ $itemUrl }}"
                                    @if ($isActive) aria-current="page" @endif
                                    @if (!$available) aria-disabled="true" data-planned="true" title="Modul tersedia pada tahap berikutnya" @endif
                                >
                                    <x-dashboard.icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
                                    @if (!$available)
                                        <small>Segera</small>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="dpe-dashboard-sidebar__footer">
                <div class="dpe-dashboard-sidebar__footer-copy">
                    <x-dashboard.icon name="info" />
                    <span>Data dashboard akan tampil setelah koneksi modul aktif.</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dpe-dashboard-logout" type="submit">
                        <x-dashboard.icon name="logout" />
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>
        <script src="{{ asset('assets/dpe/sidebar-scroll.js') }}?v={{ filemtime(public_path('assets/dpe/sidebar-scroll.js')) }}"></script>

        <div class="dpe-dashboard-main-shell">
            <header class="dpe-dashboard-topbar">
                <div class="dpe-dashboard-topbar__left">
                    <button class="dpe-dashboard-icon-button dpe-dashboard-icon-button--menu" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="dpe-dashboard-sidebar" aria-label="Buka navigasi">
                        <x-dashboard.icon name="menu" />
                    </button>
                    <div class="dpe-dashboard-topbar__heading">
                        <p class="dpe-dashboard-eyebrow">{{ $role['label'] }} <span aria-hidden="true">/</span> Workspace</p>
                        <h1>@yield('heading', 'Dashboard')</h1>
                        <p>@yield('description', $role['description'])</p>
                    </div>
                </div>

                <div class="dpe-dashboard-topbar__right">
                    <div class="dpe-dashboard-topbar__actions">
                        @yield('page-actions')
                    </div>
                    <div class="dpe-dashboard-notifications">
                        <button class="dpe-dashboard-icon-button dpe-dashboard-icon-button--notification" type="button" data-notification-toggle aria-expanded="false" aria-controls="dpe-dashboard-notification-panel" aria-label="{{ $notificationHeader['available'] ? 'Buka notifikasi, '.$notificationHeader['unread'].' belum dibaca' : 'Notifikasi belum tersedia' }}" @disabled(! $notificationHeader['available'])>
                            <x-dashboard.icon name="bell" />
                            @if ($notificationHeader['unread'] > 0)<span class="dpe-inbox-count" aria-hidden="true">{{ $notificationHeader['unread'] > 99 ? '99+' : $notificationHeader['unread'] }}</span>@endif
                        </button>
                        <div class="dpe-dashboard-popover dpe-dashboard-notification-panel" id="dpe-dashboard-notification-panel" data-notification-panel hidden>
                            <div class="dpe-dashboard-popover__head">
                                <div>
                                    <strong>Notifikasi</strong>
                                    <span>{{ $notificationHeader['unread'] }} belum dibaca</span>
                                </div>
                                <button class="dpe-dashboard-popover__close" type="button" data-notification-close aria-label="Tutup notifikasi">
                                    <x-dashboard.icon name="close" />
                                </button>
                            </div>
                            @if ($notificationHeader['items']->isNotEmpty())
                                <div class="dpe-inbox-preview">@foreach ($notificationHeader['items'] as $item)
                                    @if ($item['path'])<form method="POST" action="{{ route('inbox.open', $item['key']) }}">@csrf<button type="submit"><strong>{{ $item['title'] }}</strong><span>{{ $item['body'] }}</span></button></form>
                                    @else<div><strong>{{ $item['title'] }}</strong><p>{{ $item['body'] }}</p></div>@endif
                                @endforeach</div>
                            @else<x-dashboard.empty title="Tidak ada notifikasi belum dibaca" message="Buka semua notifikasi untuk melihat riwayat." icon="bell" />@endif
                            <div class="dpe-inbox-footer"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('inbox.index') }}">Lihat semua notifikasi</a></div>
                        </div>
                    </div>

                    <div class="dpe-dashboard-account">
                        <button class="dpe-dashboard-account__toggle" type="button" data-account-toggle aria-expanded="false" aria-controls="dpe-dashboard-account-menu">
                            <span class="dpe-dashboard-avatar" aria-hidden="true">{{ $initials }}@if($roleCode === 'teacher')<img data-account-avatar-image @if($profilePhotoUrl) src="{{ $profilePhotoUrl }}" @else hidden @endif alt="" width="42" height="42">@endif</span>
                            <span class="dpe-dashboard-account__copy">
                                <strong>{{ $userName }}</strong>
                                <small>{{ $role['label'] }}</small>
                            </span>
                            <x-dashboard.icon name="chevron-down" />
                        </button>
                        <div class="dpe-dashboard-account__menu" id="dpe-dashboard-account-menu" data-account-menu hidden>
                            <div class="dpe-dashboard-account__menu-user">
                                <strong>{{ $userName }}</strong>
                                @if ($userEmail)<span>{{ $userEmail }}</span>@endif
                            </div>
                            <a href="{{ $dashboardUrl }}"><x-dashboard.icon name="dashboard" /> Dashboard</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><x-dashboard.icon name="logout" /> Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="dpe-dashboard-main" id="dashboard-main" tabindex="-1">
                @if (session('success'))
                    <div class="dpe-dashboard-alert dpe-dashboard-alert--success" role="status">
                        <x-dashboard.icon name="check" />
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
                        <x-dashboard.icon name="warning" />
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="dpe-dashboard-footer">
                <span>© {{ date('Y') }} {{ $productName }}</span>
                <span>{{ $role['label'] }} workspace</span>
            </footer>
        </div>
    </div>

    <div class="dpe-dashboard-toast" data-dashboard-toast role="status" aria-live="polite" hidden></div>

    <div class="dpe-dashboard-confirm" data-dashboard-confirm hidden>
        <div class="dpe-dashboard-confirm__backdrop" data-confirm-backdrop></div>
        <section class="dpe-dashboard-confirm__dialog" role="dialog" aria-modal="true" aria-labelledby="dpe-dashboard-confirm-title" aria-describedby="dpe-dashboard-confirm-message">
            <div class="dpe-dashboard-confirm__icon"><x-dashboard.icon name="warning" /></div>
            <div class="dpe-dashboard-confirm__copy">
                <p class="dpe-dashboard-kicker">Konfirmasi tindakan</p>
                <h2 id="dpe-dashboard-confirm-title" data-confirm-title>Konfirmasi</h2>
                <p id="dpe-dashboard-confirm-message" data-confirm-message>Periksa kembali tindakan ini sebelum melanjutkan.</p>
            </div>
            <div class="dpe-dashboard-confirm__actions">
                <button class="dpe-dashboard-button dpe-dashboard-button--outline" type="button" data-confirm-cancel>Batal</button>
                <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="button" data-confirm-submit>Konfirmasi</button>
            </div>
        </section>
    </div>

    <script src="{{ asset('assets/dpe/dashboard.js') }}?v={{ $jsVersion }}" defer></script>
    @if (request()->routeIs('admin.class-groups.*', 'admin.class-group-memberships.*', 'admin.lesson-sessions.*'))
        <script src="{{ asset('assets/dpe/branch-operations.js') }}?v={{ filemtime(public_path('assets/dpe/branch-operations.js')) }}" defer></script>
    @endif
    @stack('scripts')
</body>
</html>
