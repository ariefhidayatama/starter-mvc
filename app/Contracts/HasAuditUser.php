<?php

namespace App\Contracts;

/**
 * Model yang punya kolom "dibuat oleh" / "diubah oleh" (lihat App\Observers\FillAuditUser).
 */
interface HasAuditUser
{
    /**
     * @return array{created_by: string|null, updated_by: string|null, max: int}
     */
    public function auditUserColumns(): array;
}
