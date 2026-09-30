<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PlanillaQuincena;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanillaQuincenaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_planilla::quincena');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('view_planilla::quincena');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_planilla::quincena');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('update_planilla::quincena');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('delete_planilla::quincena');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_planilla::quincena');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('force_delete_planilla::quincena');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_planilla::quincena');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('restore_planilla::quincena');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_planilla::quincena');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, PlanillaQuincena $planillaQuincena): bool
    {
        return $user->can('replicate_planilla::quincena');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_planilla::quincena');
    }
}
