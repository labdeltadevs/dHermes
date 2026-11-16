<?php

namespace App\Policies;

use App\Models\HeroSlide;
use App\Models\User;

/**
 * Los comunicados son contenido global de la plataforma: solo el
 * superadministrador puede gestionarlos.
 */
class HeroSlidePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function view(User $user, HeroSlide $heroSlide): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function update(User $user, HeroSlide $heroSlide): bool
    {
        return $this->isPlatformAdmin($user);
    }

    public function delete(User $user, HeroSlide $heroSlide): bool
    {
        return $this->isPlatformAdmin($user);
    }

    private function isPlatformAdmin(User $user): bool
    {
        return $user->active && $user->is_platform_admin;
    }
}
