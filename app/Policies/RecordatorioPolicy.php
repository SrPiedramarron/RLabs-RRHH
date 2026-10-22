<?php

namespace App\Policies;

use App\Models\Recordatorio;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecordatorioPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_recordatorio');
    }

    public function view(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('view_recordatorio');
    }

    public function create(User $user): bool
    {
        return $user->can('create_recordatorio');
    }

    public function update(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('update_recordatorio');
    }

    public function delete(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('delete_recordatorio');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_recordatorio');
    }

    public function forceDelete(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('force_delete_recordatorio');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_recordatorio');
    }

    public function restore(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('restore_recordatorio');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_recordatorio');
    }

    public function replicate(User $user, Recordatorio $recordatorio): bool
    {
        return $user->can('replicate_recordatorio');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_recordatorio');
    }
}
