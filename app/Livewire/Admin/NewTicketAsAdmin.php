<?php

namespace App\Livewire\Admin;

use App\Application\Services\TiketService;
use App\Models\Kategori;
use App\Models\Lokasi;
use App\Models\StatusTiketModel;
use App\Models\Tiket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Admin variant of NewTicket — files a ticket ON BEHALF of another karyawan user.
 *
 * Use case: a supervisor reports an issue via WhatsApp and refuses to use the web app.
 * The admin captures the report officially by selecting the requester from a searchable list,
 * then filling out the same form fields as karyawan-side NewTicket. The activity log records
 * the admin as the actor (via `actor_id`) so audits can trace who filed the ticket vs who it
 * was filed for.
 */
class NewTicketAsAdmin extends Component
{
    use WithFileUploads;

    // Selected requester
    public ?int $selectedUserId = null;

    public ?array $selectedUserMeta = null;  // ['name', 'email', 'lokasi_name']

    // Searchable picker state — sort is always ASC by name (combobox UX, no sort toggle needed)
    public string $userSearch = '';

    // Standard ticket fields (mirror NewTicket karyawan-side)
    public string $lokasi_id = '';

    public string $kategori_id = '';

    public string $deskripsi = '';

    public array $lampiran = [];

    public ?array $repetitiveHint = null;

    public bool $showSubmitConfirm = false;

    protected function rules(): array
    {
        return [
            'selectedUserId' => 'required|exists:pengguna,id',
            'lokasi_id' => 'required|exists:lokasi,id',
            'kategori_id' => 'required|exists:kategori,id',
            'deskripsi' => 'required|min:10|max:100',
            'lampiran' => 'array|max:5',
            'lampiran.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:2048',
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedUserId.required' => 'Please select the user this ticket is being filed for.',
            'lokasi_id.required' => 'Please select a location.',
            'kategori_id.required' => 'Please select a category.',
            'deskripsi.required' => 'Please describe the issue.',
            'deskripsi.min' => 'Description must be at least 10 characters.',
            'deskripsi.max' => 'Description must not exceed 100 characters.',
            'lampiran.max' => 'You may attach a maximum of 5 files.',
            'lampiran.*.mimes' => 'Only JPG, JPEG, PNG, WEBP, or PDF files are allowed.',
            'lampiran.*.max' => 'Each file must not exceed 2 MB.',
        ];
    }

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            abort(403);
        }
    }

    public function selectUser(int $userId): void
    {
        $user = User::query()
            ->where('peran', 'karyawan')
            ->with(['karyawan.lokasi'])
            ->find($userId);

        if (! $user || ! $user->karyawan) {
            return;
        }

        $this->selectedUserId = $user->id;
        $this->selectedUserMeta = [
            'name' => $user->karyawan->nama,
            'email' => $user->karyawan->email,
            'lokasi_name' => $user->karyawan->lokasi?->nama_lokasi ?? '—',
        ];

        // Auto-fill lokasi from the selected requester's karyawan record (admin may still override)
        if ($user->karyawan->id_lokasi) {
            $this->lokasi_id = (string) $user->karyawan->id_lokasi;
        }

        $this->userSearch = '';
        $this->cekRepetitive();
    }

    public function clearSelectedUser(): void
    {
        $this->selectedUserId = null;
        $this->selectedUserMeta = null;
        $this->lokasi_id = '';
        $this->repetitiveHint = null;
    }

    public function updatedLokasiId(): void
    {
        $this->cekRepetitive();
    }

    public function updatedKategoriId(): void
    {
        $this->cekRepetitive();
    }

    public function removeLampiran(int $index): void
    {
        array_splice($this->lampiran, $index, 1);
    }

    public function cekRepetitive(): void
    {
        $this->repetitiveHint = null;

        if (! $this->selectedUserId || ! $this->lokasi_id || ! $this->kategori_id) {
            return;
        }

        $closeId = StatusTiketModel::findByName('Close')?->id;
        if (! $closeId) {
            return;
        }

        $row = Tiket::query()
            ->selectRaw('COUNT(*) as count, MAX(id) as last_id, MAX(created_at) as last_date')
            ->where('id_pengguna', $this->selectedUserId)
            ->where('id_lokasi', (int) $this->lokasi_id)
            ->where('id_kategori', (int) $this->kategori_id)
            ->where('id_status_tiket', $closeId)
            ->first();

        if (! $row || ! $row->count) {
            return;
        }

        $this->repetitiveHint = [
            'count' => (int) $row->count,
            'last_id' => $row->last_id,
            'last_date' => $row->last_date
                ? Carbon::parse($row->last_date)->format('d M Y')
                : null,
        ];
    }

    public function openSubmitConfirm(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $errors = $e->validator->errors();
            $failedNames = [];

            foreach ($errors->keys() as $key) {
                if (preg_match('/^lampiran\.(\d+)$/', $key, $matches)) {
                    $idx = (int) $matches[1];
                    foreach ($errors->get($key) as $msg) {
                        if (str_contains($msg, 'failed to upload')) {
                            $file = $this->lampiran[$idx] ?? null;
                            $name = $file ? $file->getClientOriginalName() : "file #{$idx}";
                            $failedNames[] = $name;
                            break;
                        }
                    }
                    $errors->forget($key);
                }
            }

            foreach ($failedNames as $name) {
                $errors->add('lampiran', "{$name} failed to upload.");
            }

            throw $e;
        }

        $this->showSubmitConfirm = true;
    }

    public function confirmSubmit(TiketService $tiketService): void
    {
        $this->showSubmitConfirm = false;

        $admin = Auth::user();
        if (! $admin instanceof User || ! $admin->isAdmin()) {
            abort(403);
        }

        // Defense-in-depth: re-verify the requester is a valid karyawan
        $requester = User::where('peran', 'karyawan')->with('karyawan')->find($this->selectedUserId);
        if (! $requester || ! $requester->karyawan) {
            $this->dispatch('notify', type: 'error', content: 'The selected requester is not linked to a valid employee record.');

            return;
        }

        try {
            $tiket = $tiketService->buatTiket([
                'id_pengguna' => $requester->id,
                'id_lokasi' => $this->lokasi_id,
                'id_kategori' => $this->kategori_id,
                'deskripsi' => $this->deskripsi,
                'actor_id' => $admin->id,  // Audit: admin is actor, requester is owner
            ]);

            if (! empty($this->lampiran)) {
                $tiketService->simpanLampiran($tiket, $this->lampiran);
            }

            $tiket->load('grupTiketAsLatest');

            if ($tiket->grupTiketAsLatest) {
                $kejadianKe = $tiket->grupTiketAsLatest->jumlah;
                $this->dispatch(
                    'notify',
                    type: 'success',
                    content: "Ticket created for {$requester->karyawan->nama}. Recurring issue — occurrence #{$kejadianKe} total."
                );
            } else {
                $this->dispatch('notify', type: 'success', content: "Ticket submitted for {$requester->karyawan->nama}.");
            }

            $this->dispatch('close-modal', id: 'new-ticket-as-admin-modal');
            $this->dispatch('ticket-created');

            // Reset form for next entry
            $this->selectedUserId = null;
            $this->selectedUserMeta = null;
            $this->userSearch = '';
            $this->lokasi_id = '';
            $this->kategori_id = '';
            $this->deskripsi = '';
            $this->lampiran = [];
            $this->repetitiveHint = null;

        } catch (\DomainException $e) {
            $this->dispatch('notify', type: 'warning', content: $e->getMessage());
        }
    }

    public function render()
    {
        $admin = Auth::user();
        if (! $admin instanceof User || ! $admin->isAdmin()) {
            abort(403);
        }

        // Search candidates (only when no user is selected yet)
        $searchResults = collect();
        if ($this->selectedUserId === null) {
            $term = trim($this->userSearch);

            $searchResults = User::query()
                ->where('peran', 'karyawan')
                ->whereHas('karyawan')
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->whereHas('karyawan', function ($k) use ($like) {
                        $k->where('nama', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                })
                ->join('karyawan', 'pengguna.id_karyawan', '=', 'karyawan.id')
                ->orderBy('karyawan.nama', 'asc')
                ->select('pengguna.*')
                ->with(['karyawan.lokasi'])
                ->limit(20)
                ->get();
        }

        return view('livewire.admin.new-ticket-as-admin', [
            'searchResults' => $searchResults,
            'daftarLokasi' => Lokasi::active()->orderBy('nama_lokasi')->get(),
            'daftarKategori' => Kategori::pilihanForm(),
            'nextTicketNumber' => (Tiket::max('id') ?? 0) + 1,
        ]);
    }
}
