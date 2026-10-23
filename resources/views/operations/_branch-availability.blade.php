@if(isset($availabilitySlots))
    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Ketersediaan Teacher</h2><p>Maksimal 50 slot terdekat mulai hari ini pada cabang Anda. Slot yang sudah diisi diperiksa kembali saat menyimpan jadwal.</p></div></header>
        @if($availabilitySlots->isEmpty())
            <div class="dpe-dashboard-card__body"><p>Belum ada slot ketersediaan tersimpan. Jadwal tetap dapat disiapkan sesuai kesepakatan dengan Teacher.</p></div>
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Tanggal</th><th>Teacher</th><th>Cabang</th><th>Waktu</th><th>Jenis kelas</th><th>Status</th></tr></thead><tbody>
                @foreach($availabilitySlots as $slot)<tr><td>{{ $slot->available_on->format('d M Y') }}</td><td>{{ $slot->teacher?->user?->full_name ?: $slot->teacher?->user?->username ?: 'Teacher' }}</td><td>{{ $slot->branch?->name ?: '—' }}</td><td>{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</td><td>{{ $slot->class_type }}</td><td><x-dashboard.status :status="$slot->availability_status" :label="$slot->availability_status" /></td></tr>@endforeach
            </tbody></table></div>
        @endif
    </article>
@endif
