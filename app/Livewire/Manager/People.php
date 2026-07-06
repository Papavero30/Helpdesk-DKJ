<?php

namespace App\Livewire\Manager;

use App\Application\Services\ActivityLogService;
use App\Application\Services\PersonImportService;
use App\Imports\PersonImport;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Karyawan;
use App\Models\Lokasi;
use App\Models\Tiket;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app', ['title' => 'People', 'description' => 'Manage employees and their login accounts'])]
class People extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** Password used when the form is submitted with a blank password on create. */
    public const DEFAULT_PASSWORD = 'karyawan123';

    public string $search = '';

    public string $filterRole = '';

    public string $filterStatus = '';

    public array $filterPlant = [];

    public array $filterDivisi = [];

    public bool $showModal = false;

    public ?int $editingId = null;   // User id being edited (null = create)

    // Person (Karyawan) fields
    public string $nama = '';

    public string $email = '';

    public string $noTelepon = '';

    public string $idDivisi = '';

    public string $idLokasi = '';

    public string $idJabatan = '';

    // Account (User) fields
    public string $peran = 'karyawan';

    public string $editingRole = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $statusAkun = 'active';

    // Bulk import (Excel)
    public $importFile = null;

    public bool $showImportModal = false;

    public string $importStage = 'upload'; // upload | preview | done

    /** @var array<int, array<string, mixed>> */
    public array $importPreview = [];

    public ?array $importResult = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403);
        }
    }

    /**
     * Admins may only manage employee (karyawan) accounts; managing admin/manager
     * accounts (and granting the admin role) stays manager-only. Managers can
     * manage anyone (creating managers is locked elsewhere).
     */
    private function actorCanManage(User $target): bool
    {
        $actor = Auth::user();

        return $actor instanceof User
            && ($actor->isManager() || $target->peran === 'karyawan');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->filterRole = '';
        $this->filterStatus = '';
        $this->filterPlant = [];
        $this->filterDivisi = [];
        $this->resetPage();
        $this->dispatch('filters-reset');
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->editingRole = '';
        $this->showModal = true;
    }

    public function openImport(): void
    {
        $this->reset(['importFile', 'importPreview', 'importResult']);
        $this->importStage = 'upload';
        $this->resetValidation();
        $this->showImportModal = true;
    }

    public function backToUpload(): void
    {
        $this->reset(['importFile', 'importPreview']);
        $this->importStage = 'upload';
        $this->resetValidation();
    }

    public function previewImport(PersonImportService $service): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403);
        }

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $import = new PersonImport;
        Excel::import($import, $this->importFile->getRealPath(), null, \Maatwebsite\Excel\Excel::XLSX);

        $this->importPreview = $service->resolveRows($import->rows);
        $this->importResult = null;
        $this->importStage = 'preview';
    }

    public function confirmImport(PersonImportService $service): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403);
        }

        if (empty($this->importPreview)) {
            return;
        }

        $result = $service->commit($this->importPreview);

        $this->importResult = [
            'created' => $result->created,
            'skipped' => $result->skipped,
            'invalid' => $result->invalid,
            'errors' => $result->errors,
        ];

        app(ActivityLogService::class)->catatKonfigurasi(
            'users_imported',
            Auth::id(),
            'user',
            null,
            "Imported {$result->created} account(s) from Excel ({$result->skipped} skipped, {$result->invalid} invalid)",
        );

        $this->reset(['importFile', 'importPreview']);
        $this->importStage = 'done';
        $this->resetPage();
        $this->dispatch('notify', type: 'success', content: "Import complete. {$result->created} created, {$result->skipped} skipped.");
    }

    public function openEdit(int $userId): void
    {
        $this->resetForm();

        $user = User::with('karyawan')->findOrFail($userId);

        if (! $this->actorCanManage($user)) {
            $this->dispatch('notify', type: 'warning', content: 'Admins can only manage employee (karyawan) accounts.');

            return;
        }

        $this->editingId = $user->id;
        $this->nama = $user->karyawan?->nama ?? '';
        $this->email = $user->karyawan?->email ?? '';
        $this->noTelepon = $user->karyawan?->no_telepon ?? '';
        $this->idDivisi = (string) ($user->karyawan?->id_divisi ?? '');
        $this->idLokasi = (string) ($user->karyawan?->id_lokasi ?? '');
        $this->idJabatan = (string) ($user->karyawan?->id_jabatan ?? '');
        $this->peran = $user->peran;
        $this->editingRole = $user->peran;
        $this->statusAkun = $user->status_akun;

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save(): void
    {
        $isEdit = $this->editingId !== null;
        $editingUser = $isEdit ? User::with('karyawan')->findOrFail($this->editingId) : null;
        $karyawanIdToIgnore = $editingUser?->karyawan?->id;

        $actorIsManager = Auth::user() instanceof User && Auth::user()->isManager();

        // Admins may only manage karyawan accounts.
        if ($isEdit && ! $this->actorCanManage($editingUser)) {
            $this->dispatch('notify', type: 'warning', content: 'Admins can only manage employee (karyawan) accounts.');

            return;
        }

        // Role lock: managers may assign karyawan/admin (an existing manager or your
        // own account keep their role); admins may only ever assign karyawan.
        $roleLocked = $isEdit && ($editingUser->id === Auth::id() || $editingUser->peran === 'manager');
        if (! $actorIsManager) {
            $allowedRoles = ['karyawan'];
        } else {
            $allowedRoles = $roleLocked ? [$editingUser->peran] : ['karyawan', 'admin'];
        }

        // Normalize the submitted role when it is locked (self/manager) or when the
        // actor is an admin (whose select only offers karyawan) — so a tampered
        // value reflects the preserved/forced role rather than escalating.
        if ($roleLocked) {
            $this->peran = $editingUser->peran;
        } elseif (! $actorIsManager) {
            $this->peran = 'karyawan';
        }

        $this->validate([
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:100', Rule::unique('karyawan', 'email')->ignore($karyawanIdToIgnore)],
            'noTelepon' => ['nullable', 'string', 'max:20'],
            'idDivisi' => ['required', 'exists:divisi,id'],
            'idLokasi' => ['required', 'exists:lokasi,id'],
            'idJabatan' => ['required', 'exists:jabatan,id'],
            'peran' => ['required', Rule::in($allowedRoles)],
            'statusAkun' => ['required', 'in:active,inactive'],
            // Blank password: on edit it keeps the current one, on create it
            // falls back to DEFAULT_PASSWORD, so it is never required.
            'password' => ['nullable', 'min:6', 'confirmed'],
        ]);

        $effectivePeran = $roleLocked ? $editingUser->peran : $this->peran;
        $effectiveStatus = ($isEdit && $editingUser->id === Auth::id()) ? $editingUser->status_akun : $this->statusAkun;

        $jab = Jabatan::findOrFail($this->idJabatan);

        DB::transaction(function () use ($isEdit, $editingUser, $effectivePeran, $effectiveStatus, $jab) {
            if ($isEdit) {
                $editingUser->karyawan->update([
                    'nama' => $this->nama,
                    'email' => $this->email,
                    'no_telepon' => $this->noTelepon ?: null,
                    'id_divisi' => $this->idDivisi,
                    'id_lokasi' => $this->idLokasi,
                    'id_jabatan' => $jab->id,
                    'jabatan' => $jab->nama_jabatan,
                ]);

                $userData = [
                    'peran' => $effectivePeran,
                    'status_akun' => $effectiveStatus,
                ];
                if ($this->password !== '') {
                    $userData['password'] = Hash::make($this->password);
                }
                $editingUser->update($userData);
                $targetUser = $editingUser;
            } else {
                $karyawan = Karyawan::create([
                    'nama' => $this->nama,
                    'email' => $this->email,
                    'no_telepon' => $this->noTelepon ?: null,
                    'id_divisi' => $this->idDivisi,
                    'id_lokasi' => $this->idLokasi,
                    'id_jabatan' => $jab->id,
                    'jabatan' => $jab->nama_jabatan,
                ]);

                $targetUser = User::create([
                    'id_karyawan' => $karyawan->id,
                    'password' => Hash::make($this->password !== '' ? $this->password : self::DEFAULT_PASSWORD),
                    'peran' => $effectivePeran,
                    'status_akun' => $effectiveStatus,
                ]);
            }

            app(ActivityLogService::class)->catatKonfigurasi(
                $isEdit ? 'user_updated' : 'user_created',
                Auth::id(),
                'user',
                $targetUser->id,
                '"'.$this->nama.'" account has been '.($isEdit ? 'updated' : 'created ('.$effectivePeran.')'),
            );
        });

        $this->dispatch('notify', type: 'success', content: $isEdit ? 'Person updated.' : 'Person created.');
        $this->closeModal();
    }

    public function toggleStatus(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->dispatch('notify', type: 'warning', content: 'You cannot change your own account status.');

            return;
        }

        $user = User::with('karyawan')->findOrFail($userId);

        if (! $this->actorCanManage($user)) {
            $this->dispatch('notify', type: 'warning', content: 'Admins can only manage employee (karyawan) accounts.');

            return;
        }

        $newStatus = $user->status_akun === 'active' ? 'inactive' : 'active';
        $user->update(['status_akun' => $newStatus]);

        app(ActivityLogService::class)->catatKonfigurasi(
            $newStatus === 'inactive' ? 'user_deactivated' : 'user_activated',
            Auth::id(),
            'user',
            $user->id,
            '"'.($user->karyawan?->nama ?? '#'.$user->id).'" account has been '.($newStatus === 'inactive' ? 'deactivated' : 'activated'),
        );

        $this->dispatch('notify', type: 'success', content: 'Account status updated.');
    }

    public function deleteIfUnused(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->dispatch('notify', type: 'warning', content: 'You cannot delete your own account.');

            return;
        }

        $user = User::with('karyawan')->findOrFail($userId);

        if (! $this->actorCanManage($user)) {
            $this->dispatch('notify', type: 'warning', content: 'Admins can only manage employee (karyawan) accounts.');

            return;
        }

        $usage = Tiket::where('id_pengguna', $userId)->orWhere('id_penanggung_jawab', $userId)->count();
        if ($usage > 0) {
            $this->dispatch('notify', type: 'warning', content: "Cannot delete: this account has {$usage} ticket(s). Deactivate instead.");

            return;
        }

        $nama = $user->karyawan?->nama ?? '#'.$user->id;
        DB::transaction(function () use ($user) {
            $karyawan = $user->karyawan;
            $user->delete();
            $karyawan?->delete();
        });

        app(ActivityLogService::class)->catatKonfigurasi(
            'user_deleted', Auth::id(), 'user', $userId, '"'.$nama.'" account has been deleted',
        );

        $this->dispatch('notify', type: 'success', content: 'Person deleted.');
    }

    private function resetForm(): void
    {
        $this->nama = '';
        $this->email = '';
        $this->noTelepon = '';
        $this->idDivisi = '';
        $this->idLokasi = '';
        $this->idJabatan = '';
        $this->peran = 'karyawan';
        $this->editingRole = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->statusAkun = 'active';
    }

    public function render()
    {
        $people = User::query()
            ->with(['karyawan.divisi', 'karyawan.lokasi'])
            ->whereHas('karyawan')
            ->when($this->search !== '', fn ($q) => $q->whereHas('karyawan', fn ($k) => $k->where('nama', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->filterRole !== '', fn ($q) => $q->where('peran', $this->filterRole))
            ->when($this->filterStatus !== '', fn ($q) => $q->where('status_akun', $this->filterStatus))
            ->when(! empty($this->filterPlant), fn ($q) => $q->whereHas('karyawan', fn ($k) => $k->whereIn('id_lokasi', $this->filterPlant)))
            ->when(! empty($this->filterDivisi), fn ($q) => $q->whereHas('karyawan', fn ($k) => $k->whereIn('id_divisi', $this->filterDivisi)))
            ->orderBy('id')
            ->paginate(15);

        $activeFilterCount = ($this->filterRole !== '' ? 1 : 0)
            + ($this->filterStatus !== '' ? 1 : 0)
            + count($this->filterPlant)
            + count($this->filterDivisi);

        return view('livewire.manager.people', [
            'people' => $people,
            'divisiOptions' => Divisi::active()->orderBy('nama_divisi')->get(),
            'lokasiOptions' => Lokasi::active()->orderBy('nama_lokasi')->get(),
            'jabatanOptions' => Jabatan::active()->orderBy('nama_jabatan')->get(),
            'activeFilterCount' => $activeFilterCount,
            'currentUserId' => Auth::id(),
            'currentUserIsManager' => Auth::user() instanceof User && Auth::user()->isManager(),
        ]);
    }
}
