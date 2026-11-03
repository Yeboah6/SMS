<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditLogObserver
{
    public function __construct(private AuditLogService $auditLogs) {}

    public function created(Model $model): void
    {
        $this->auditLogs->recordModelChange($model, 'created', array_keys($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $this->auditLogs->recordModelChange($model, 'updated', array_keys($model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        $this->auditLogs->recordModelChange($model, 'deleted', array_keys($model->getAttributes()));
    }

    public function restored(Model $model): void
    {
        $this->auditLogs->recordModelChange($model, 'restored', ['deleted_at']);
    }
}
