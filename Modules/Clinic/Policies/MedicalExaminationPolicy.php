<?php

namespace Modules\Clinic\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Clinic\Models\MedicalExamination;

/**
 * Combines the permission check with the examination lifecycle: a finalized
 * examination is part of the medical record and can no longer be edited or
 * deleted, whatever the user's permissions.
 */
class MedicalExaminationPolicy
{
    public function update(User $user, MedicalExamination $examination): Response
    {
        if (! $user->can('examinations.update')) {
            return Response::deny('غير مصرح لك بتعديل الفحص الطبي.');
        }

        return $examination->isDraft()
            ? Response::allow()
            : Response::deny('هذا الفحص معتمد ولا يمكن تعديله.');
    }

    public function delete(User $user, MedicalExamination $examination): Response
    {
        if (! $user->can('examinations.delete')) {
            return Response::deny('غير مصرح لك بحذف الفحص الطبي.');
        }

        return $examination->isDraft()
            ? Response::allow()
            : Response::deny('لا يمكن حذف فحص معتمد.');
    }
}
