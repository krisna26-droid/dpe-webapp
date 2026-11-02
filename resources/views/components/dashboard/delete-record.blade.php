@props(['action', 'title' => 'Hapus data ini?'])
<form method="POST" action="{{ $action }}" data-confirm-form data-confirm-title="{{ $title }}" data-confirm-message="Data yang dipilih akan dihapus." data-confirm-action="Hapus">
    @csrf
    @method('DELETE')
    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">Hapus</button>
</form>
