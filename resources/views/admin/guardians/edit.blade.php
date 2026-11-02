@extends('layouts.dashboard')

@section('title', 'Edit Nomor Telepon Wali')
@section('heading', 'Data Wali Siswa')
@section('description', 'Perbarui nomor telepon wali untuk siswa pada cabang yang Anda kelola.')

@section('content')
    <x-dashboard.form-panel
        title="Edit nomor telepon wali"
        description="Admin Cabang hanya dapat memperbarui nomor kontak. Nama, hubungan, penerima utama, dan pemindahan siswa dikelola oleh Super Admin."
        :close-url="route('admin.students.show', $student)"
    >
        @if ($errors->any())
            <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
                <x-dashboard.icon name="warning" />
                <span>Periksa kembali nomor telepon sebelum menyimpan.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.guardians.update', $guardian) }}">
            @csrf
            @method('PATCH')

            <div class="dpe-dashboard-form-grid">
                <div class="dpe-dashboard-field dpe-dashboard-field--full">
                    <label for="guardian_student">Siswa</label>
                    <input id="guardian_student" type="text" value="{{ $student->full_name }}" readonly>
                </div>

                <div class="dpe-dashboard-field">
                    <label for="guardian_name">Nama wali</label>
                    <input id="guardian_name" type="text" value="{{ $guardian->full_name }}" readonly>
                </div>

                <div class="dpe-dashboard-field">
                    <label for="guardian_relationship">Hubungan</label>
                    <input id="guardian_relationship" type="text" value="{{ $guardian->relationship_name ?: 'Belum diisi' }}" readonly>
                </div>

                <div class="dpe-dashboard-field dpe-dashboard-field--full">
                    <label for="whatsapp_number">Nomor telepon / WhatsApp</label>
                    <input id="whatsapp_number" name="whatsapp_number" type="tel" value="{{ old('whatsapp_number', $guardian->whatsapp_number) }}" maxlength="30" autocomplete="tel" required>
                    <p class="dpe-dashboard-field__hint">Gunakan nomor yang dapat dihubungi, termasuk kode negara bila diperlukan.</p>
                    @error('whatsapp_number')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="dpe-dashboard-form-actions">
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.students.show', $student) }}">Batal</a>
                <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
                    <x-dashboard.icon name="check" />
                    <span>Simpan nomor</span>
                </button>
            </div>
        </form>
    </x-dashboard.form-panel>
@endsection
