<?php

namespace App\Livewire\Manager;

use App\Application\Services\ActivityLogService;
use App\Models\Divisi;
use App\Models\Jabatan;
use App\Models\Kategori;
use App\Models\Lokasi;
use App\Models\Tiket;
use App\Models\User;
use App\Notifications\ReferenceArchivedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Master Data', 'description' => 'Manage categories, plants, and divisions'])]
class MasterData extends Component
{
    public string $tab = 'categories';

    public bool $showModal = false;

    public bool $showArchived = false;

    public bool $showArchiveModal = false;

    public ?int $confirmingArchiveId = null;

    public array $archiveImpact = [];

    public ?int $editingId = null;

    public string $nama = '';

    public string $batasJamSla = '';

    public string $urgensi = '';

    public string $warnaGrafik = '#0E4260';

    public string $contoh = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            abort(403);
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['categories', 'plants', 'divisions', 'jabatan'], true)) {
            return;
        }
        $this->tab = $tab;
        $this->closeModal();
    }

    public function toggleArchived(): void
    {
        $this->showArchived = ! $this->showArchived;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $this->resetForm();
        $this->editingId = $id;

        if ($this->tab === 'categories') {
            $k = Kategori::findOrFail($id);
            $this->nama = $k->nama_kategori;
            $this->batasJamSla = (string) $k->batas_jam_sla;
            $this->urgensi = (string) $k->urgensi;
            $this->warnaGrafik = $k->warna_grafik;
            $this->contoh = $k->contoh ?? '';
        } elseif ($this->tab === 'plants') {
            $this->nama = Lokasi::findOrFail($id)->nama_lokasi;
        } elseif ($this->tab === 'jabatan') {
            $this->nama = Jabatan::findOrFail($id)->nama_jabatan;
        } else {
            $this->nama = Divisi::findOrFail($id)->nama_divisi;
        }

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
        match ($this->tab) {
            'categories' => $this->saveCategory(),
            'plants' => $this->savePlant(),
            'divisions' => $this->saveDivision(),
            'jabatan' => $this->saveJabatan(),
        };
    }

    private function saveCategory(): void
    {
        $isEdit = $this->editingId !== null;

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('kategori', 'nama_kategori')->ignore($this->editingId)],
            'batasJamSla' => ['required', 'integer', 'min:1'],
            'warnaGrafik' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contoh' => ['nullable', 'string', 'max:255'],
        ]);

        // Snapshot the pre-edit state for a field-level audit detail.
        $old = $isEdit ? Kategori::find($this->editingId) : null;

        // Temporary urgensi just to satisfy the NOT NULL column; reindexUrgencyBySla()
        // below re-derives every category's rank from SLA, so this value is overwritten.
        $tempUrgensi = $isEdit
            ? (int) $old->urgensi
            : ((int) (Kategori::max('urgensi') ?? 0) + 1);

        $detail = $this->kategoriDetail($isEdit, $old, $validated);

        $kategori = Kategori::updateOrCreate(
            ['id' => $this->editingId],
            [
                'nama_kategori' => $validated['nama'],
                'batas_jam_sla' => (int) $validated['batasJamSla'],
                'urgensi' => $tempUrgensi,
                'warna_grafik' => $validated['warnaGrafik'],
                'contoh' => $validated['contoh'] ?? null,
            ],
        );

        // Urgency is derived from SLA (shorter SLA = higher priority) → rerank all.
        Kategori::reindexUrgencyBySla();
        $kategori->refresh();

        $this->auditSave('kategori', $kategori, $isEdit, $detail);
        $this->afterSave();
    }

    private function savePlant(): void
    {
        $isEdit = $this->editingId !== null;

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('lokasi', 'nama_lokasi')->ignore($this->editingId)],
        ]);

        $old = $isEdit ? Lokasi::find($this->editingId)?->nama_lokasi : null;
        $lokasi = Lokasi::updateOrCreate(['id' => $this->editingId], ['nama_lokasi' => $validated['nama']]);

        $this->auditSave('lokasi', $lokasi, $isEdit, $this->renameDetail('lokasi', $isEdit, $old, $validated['nama']));
        $this->afterSave();
    }

    private function saveDivision(): void
    {
        $isEdit = $this->editingId !== null;

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('divisi', 'nama_divisi')->ignore($this->editingId)],
        ]);

        $old = $isEdit ? Divisi::find($this->editingId)?->nama_divisi : null;
        $divisi = Divisi::updateOrCreate(['id' => $this->editingId], ['nama_divisi' => $validated['nama']]);

        $this->auditSave('divisi', $divisi, $isEdit, $this->renameDetail('divisi', $isEdit, $old, $validated['nama']));
        $this->afterSave();
    }

    private function saveJabatan(): void
    {
        $isEdit = $this->editingId !== null;

        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('jabatan', 'nama_jabatan')->ignore($this->editingId)],
        ]);

        $old = $isEdit ? Jabatan::find($this->editingId)?->nama_jabatan : null;
        $jabatan = Jabatan::updateOrCreate(['id' => $this->editingId], ['nama_jabatan' => $validated['nama']]);

        $this->auditSave('jabatan', $jabatan, $isEdit, $this->renameDetail('jabatan', $isEdit, $old, $validated['nama']));
        $this->afterSave();
    }

    private function auditSave(string $entityType, $row, bool $isEdit, ?string $detail = null): void
    {
        $keterangan = $detail
            ?? '"'.$this->entityName($entityType, $row).'" '.strtolower($this->entityLabel($entityType)).' has been '.($isEdit ? 'updated' : 'created');

        app(ActivityLogService::class)->catatKonfigurasi(
            $entityType.($isEdit ? '_updated' : '_created'),
            Auth::id(),
            $entityType,
            $row->id,
            $keterangan,
        );
    }

    /** Human-readable SLA duration, e.g. "1 hour" / "3 hours". */
    private function slaPhrase(int $hours): string
    {
        return $hours.' hour'.($hours === 1 ? '' : 's');
    }

    /**
     * Field-level audit detail for a category save, e.g.
     * '"Security" category: SLA updated from 12 hours to 3 hours, color updated'.
     * Returns null when nothing meaningful changed (caller falls back to generic).
     */
    private function kategoriDetail(bool $isEdit, ?Kategori $old, array $validated): ?string
    {
        $name = $validated['nama'];
        $newSla = (int) $validated['batasJamSla'];

        if (! $isEdit || ! $old) {
            return '"'.$name.'" category created with SLA '.$this->slaPhrase($newSla);
        }

        $changes = [];
        if ($old->nama_kategori !== $name) {
            $changes[] = 'renamed from "'.$old->nama_kategori.'"';
        }
        if ((int) $old->batas_jam_sla !== $newSla) {
            $changes[] = 'SLA updated from '.$this->slaPhrase((int) $old->batas_jam_sla).' to '.$this->slaPhrase($newSla);
        }
        if ($old->warna_grafik !== $validated['warnaGrafik']) {
            $changes[] = 'color updated';
        }
        if (($old->contoh ?? '') !== ($validated['contoh'] ?? '')) {
            $changes[] = 'examples updated';
        }

        return empty($changes) ? null : '"'.$name.'" category: '.implode(', ', $changes);
    }

    /** Audit detail for a rename-only entity (plant/division/position). */
    private function renameDetail(string $entityType, bool $isEdit, ?string $old, string $new): ?string
    {
        if (! $isEdit || $old === null || $old === $new) {
            return null;
        }

        return '"'.$new.'" '.strtolower($this->entityLabel($entityType)).' renamed from "'.$old.'"';
    }

    private function afterSave(): void
    {
        $this->dispatch('notify', type: 'success', content: $this->editingId ? 'Updated successfully.' : 'Created successfully.');
        $this->closeModal();
    }

    public function confirmArchive(int $id): void
    {
        if (in_array($this->tab, ['divisions', 'jabatan'], true)) {
            $usage = $this->modelForTab()::find($id)?->karyawan()->count() ?? 0;
            $this->archiveImpact = ['type' => $this->tab, 'usage' => $usage, 'blocked' => $usage > 0];
        } elseif ($this->tab === 'plants') {
            // Plant is hybrid: blocked by employees (persistent — must be reassigned),
            // while active tickets are allowed to drain (transient — they self-close).
            $karyawanCount = Lokasi::find($id)?->karyawan()->count() ?? 0;
            $active = $this->activeTicketsFor('plants', $id);
            $adminNames = (clone $active)->whereNotNull('id_penanggung_jawab')
                ->with('assignedAdmin.karyawan')->get()
                ->pluck('assignedAdmin')->filter()->unique('id')
                ->map(fn ($a) => $a->karyawan?->nama ?? $a->name)->values()->all();

            $this->archiveImpact = [
                'type' => 'plants',
                'usage' => $karyawanCount,
                'blocked' => $karyawanCount > 0,
                'active' => (clone $active)->count(),
                'admins' => $adminNames,
                'unassigned' => (clone $active)->whereNull('id_penanggung_jawab')->count(),
            ];
        } else {
            $active = $this->activeTicketsFor($this->tab, $id);
            $adminNames = (clone $active)->whereNotNull('id_penanggung_jawab')
                ->with('assignedAdmin.karyawan')->get()
                ->pluck('assignedAdmin')->filter()->unique('id')
                ->map(fn ($a) => $a->karyawan?->nama ?? $a->name)->values()->all();

            $this->archiveImpact = [
                'type' => $this->tab,
                'active' => (clone $active)->count(),
                'admins' => $adminNames,
                'unassigned' => (clone $active)->whereNull('id_penanggung_jawab')->count(),
                'blocked' => false,
            ];
        }

        $this->confirmingArchiveId = $id;
        $this->showArchiveModal = true;
    }

    public function archive(): void
    {
        $id = $this->confirmingArchiveId;
        if (! $id) {
            return;
        }

        if ($this->archiveImpact['blocked'] ?? false) {
            $this->dispatch('notify', type: 'warning', content: 'Reassign employees to another divisi first.');

            return;
        }

        $entityType = $this->entityTypeForTab();
        $row = $this->modelForTab()::find($id);
        $name = $this->entityName($entityType, $row);

        if (! in_array($this->tab, ['divisions', 'jabatan'], true)) {
            $activeCount = $this->activeTicketsFor($this->tab, $id)->count();
            $admins = $this->activeTicketsFor($this->tab, $id)
                ->whereNotNull('id_penanggung_jawab')
                ->with('assignedAdmin')->get()
                ->pluck('assignedAdmin')->filter()->unique('id');

            $label = $this->entityLabel($entityType);
            foreach ($admins as $admin) {
                $admin->notify(new ReferenceArchivedNotification($label, $name, $activeCount));
            }
        }

        $this->modelForTab()::whereKey($id)->update(['is_active' => false]);

        app(ActivityLogService::class)->catatKonfigurasi(
            $entityType.'_archived',
            Auth::id(),
            $entityType,
            $id,
            '"'.$name.'" '.strtolower($this->entityLabel($entityType)).' has been archived',
        );

        $this->dispatch('notify', type: 'success', content: 'Archived.');
        $this->closeArchiveModal();
    }

    public function closeArchiveModal(): void
    {
        $this->showArchiveModal = false;
        $this->confirmingArchiveId = null;
        $this->archiveImpact = [];
    }

    public function restore(int $id): void
    {
        $entityType = $this->entityTypeForTab();
        $this->modelForTab()::whereKey($id)->update(['is_active' => true]);
        $row = $this->modelForTab()::find($id);

        app(ActivityLogService::class)->catatKonfigurasi(
            $entityType.'_restored',
            Auth::id(),
            $entityType,
            $id,
            '"'.$this->entityName($entityType, $row).'" '.strtolower($this->entityLabel($entityType)).' has been restored',
        );

        $this->dispatch('notify', type: 'success', content: 'Restored.');
    }

    public function deleteIfUnused(int $id): void
    {
        $usage = $this->usageCount($id);
        if ($usage > 0) {
            $this->dispatch('notify', type: 'warning', content: "Cannot delete: {$usage} record(s) use this. Archive it instead.");

            return;
        }

        $entityType = $this->entityTypeForTab();
        $row = $this->modelForTab()::find($id);
        $name = $this->entityName($entityType, $row);

        $this->modelForTab()::whereKey($id)->delete();

        if ($entityType === 'kategori') {
            Kategori::reindexUrgencyBySla();
        }

        app(ActivityLogService::class)->catatKonfigurasi(
            $entityType.'_deleted',
            Auth::id(),
            $entityType,
            $id,
            '"'.$name.'" '.strtolower($this->entityLabel($entityType)).' has been deleted',
        );

        $this->dispatch('notify', type: 'success', content: 'Deleted.');
    }

    /** Singular English label for the active tab, used in the create/edit modal heading. */
    #[Computed]
    public function singularLabel(): string
    {
        return $this->entityLabel($this->entityTypeForTab());
    }

    private function entityTypeForTab(): string
    {
        return match ($this->tab) {
            'categories' => 'kategori',
            'plants' => 'lokasi',
            'divisions' => 'divisi',
            'jabatan' => 'jabatan',
        };
    }

    private function entityLabel(string $entityType): string
    {
        return match ($entityType) {
            'kategori' => 'Category',
            'lokasi' => 'Plant',
            'divisi' => 'Division',
            'jabatan' => 'Position',
        };
    }

    private function entityName(string $entityType, $row): string
    {
        return match ($entityType) {
            'kategori' => $row->nama_kategori,
            'lokasi' => $row->nama_lokasi,
            'divisi' => $row->nama_divisi,
            'jabatan' => $row->nama_jabatan,
        };
    }

    private function activeTicketsFor(string $tab, int $id): Builder
    {
        $column = $tab === 'categories' ? 'id_kategori' : 'id_lokasi';

        return Tiket::where($column, $id)
            ->whereHas('statusTiket', fn ($q) => $q->whereIn('nama_status', ['Open', 'In Progress']));
    }

    private function usageCount(int $id): int
    {
        return match ($this->tab) {
            'categories' => Kategori::find($id)?->tikets()->count() ?? 0,
            'plants' => ($l = Lokasi::find($id)) ? $l->tiket()->count() + $l->karyawan()->count() : 0,
            'divisions' => Divisi::find($id)?->karyawan()->count() ?? 0,
            'jabatan' => Jabatan::find($id)?->karyawan()->count() ?? 0,
        };
    }

    /** @return class-string */
    private function modelForTab(): string
    {
        return match ($this->tab) {
            'categories' => Kategori::class,
            'plants' => Lokasi::class,
            'divisions' => Divisi::class,
            'jabatan' => Jabatan::class,
        };
    }

    private function resetForm(): void
    {
        $this->nama = '';
        $this->batasJamSla = '';
        $this->urgensi = '';
        $this->warnaGrafik = '#0E4260';
        $this->contoh = '';
    }

    public function render()
    {
        $categories = Kategori::withCount('tikets')
            ->when(! $this->showArchived, fn ($q) => $q->where('is_active', true))
            ->orderBy('urgensi')->get();

        $plants = Lokasi::withCount(['tiket', 'karyawan'])
            ->when(! $this->showArchived, fn ($q) => $q->where('is_active', true))
            ->orderBy('nama_lokasi')->get();

        $divisions = Divisi::withCount('karyawan')
            ->when(! $this->showArchived, fn ($q) => $q->where('is_active', true))
            ->orderBy('nama_divisi')->get();

        $jabatans = Jabatan::withCount('karyawan')
            ->when(! $this->showArchived, fn ($q) => $q->where('is_active', true))
            ->orderBy('nama_jabatan')->get();

        return view('livewire.manager.master-data', compact('categories', 'plants', 'divisions', 'jabatans'));
    }
}
