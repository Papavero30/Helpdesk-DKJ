<?php

namespace App\Livewire;

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

class NewTicket extends Component
{
    use WithFileUploads;

    public string $lokasi_id = '';

    public string $kategori_id = '';

    public string $deskripsi = '';

    public array $lampiran = [];

    public ?array $repetitiveHint = null;

    public bool $showSubmitConfirm = false;

    protected function rules(): array
    {
        return [
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
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isKaryawan()) {
            abort(403);
        }

        if ($user->karyawan?->id_lokasi) {
            $this->lokasi_id = (string) $user->karyawan->id_lokasi;
        }
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

        if (! $this->lokasi_id || ! $this->kategori_id) {
            return;
        }

        $closeId = StatusTiketModel::findByName('Close')?->id;
        if (! $closeId) {
            return;
        }

        $row = Tiket::query()
            ->selectRaw('COUNT(*) as count, MAX(id) as last_id, MAX(created_at) as last_date')
            ->where('id_pengguna', (int) Auth::id())
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

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->karyawan) {
            $this->dispatch('notify', type: 'error', content: 'Your account is not linked to an employee record.');

            return;
        }

        try {
            $tiket = $tiketService->buatTiket([
                'id_pengguna' => Auth::id(),
                'id_lokasi' => $this->lokasi_id,
                'id_kategori' => $this->kategori_id,
                'deskripsi' => $this->deskripsi,
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
                    content: "Ticket created. Recurring issue — occurrence #{$kejadianKe} total."
                );
            } else {
                $this->dispatch('notify', type: 'success', content: 'Ticket submitted successfully.');
            }

            $this->dispatch('close-modal', id: 'new-ticket-modal');
            $this->dispatch('ticket-created');

            $this->lokasi_id = (string) ($user->karyawan?->id_lokasi ?? '');
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
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $karyawan = $user->karyawan?->load(['divisi', 'lokasi']);

        return view('livewire.new-ticket', [
            'karyawan' => $karyawan,
            'daftarLokasi' => Lokasi::active()->orderBy('nama_lokasi')->get(),
            'daftarKategori' => Kategori::pilihanForm(),
            'nextTicketNumber' => (Tiket::max('id') ?? 0) + 1,
        ]);
    }
}
