@props(['status' => null, 'label' => null])
@php
    $code = is_bool($status) ? ($status ? 'active' : 'inactive')
        : (is_scalar($status) ? preg_replace('/[\s-]+/u', '_', strtolower(trim((string) $status))) : 'unknown');
    $statusTone = match ($code) {
        'active', 'aktif', 'approved', 'disetujui', 'validated', 'tervalidasi', 'paid', 'present', 'hadir',
        'completed', 'selesai', 'available', 'improving', 'ready', 'visible', 'ditampilkan', 'achieved' => 'success',
        'inactive', 'nonaktif', 'rejected', 'ditolak', 'cancelled', 'canceled', 'dibatalkan', 'absent', 'tidak_hadir',
        'needs_correction', 'revision', 'returned_for_revision', 'needs_improvement', 'overdue', 'blocked' => 'danger',
        'draft', 'scheduled', 'terjadwal', 'ongoing', 'in_progress', 'berlangsung', 'rescheduled',
        'recorded', 'stable', 'hidden', 'disembunyikan', 'other_owner' => 'info',
        default => 'warning',
    };
@endphp
<span {{ $attributes->class(['dpe-dashboard-badge', 'dpe-dashboard-badge--'.$statusTone, 'dpe-dashboard-status', 'dpe-dashboard-status--'.$statusTone]) }} data-status="{{ $code }}" data-status-tone="{{ $statusTone }}">{{ $label ?? $slot }}</span>
