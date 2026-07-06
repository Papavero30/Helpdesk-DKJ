<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfilePopup extends Component
{
    use WithFileUploads;

    #[Validate('nullable|image|max:1024')]
    public $newAvatar = null;

    public function uploadAvatar(): void
    {
        $this->validate();

        $user = auth()->user();

        if ($user->foto_profil) {
            Storage::disk('public')->delete($user->foto_profil);
        }

        $path = $this->newAvatar->store('avatars', 'public');
        $user->update(['foto_profil' => $path]);

        $this->newAvatar = null;
        $this->dispatch('avatar-updated');
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        if ($user->foto_profil) {
            Storage::disk('public')->delete($user->foto_profil);
            $user->update(['foto_profil' => null]);
        }

        $this->dispatch('avatar-updated');
    }

    public function render()
    {
        return view('livewire.profile-popup');
    }
}
