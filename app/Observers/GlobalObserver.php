<?php

namespace App\Observers;

use App\Services\ActivityLogger;

use App\Models\ActivityLog;

class GlobalObserver
{
    protected $oldData = [];

    public function updating($model)
    {
        if ($this->skip($model)) return;
        $this->oldData[$model->getKey()] =
            array_intersect_key(
                $model->getOriginal(),
                $model->getDirty()
            );
    }

    public function updated($model)
    {
        if ($this->shouldSkip()) {
            return;
        }
        if (app()->runningInConsole()) {
            return;
        }
        if ($this->skip($model)) return;
        // dd(
        //     $this->oldData,
        //     $model->getOriginal(),
        //     $model->getChanges(),
        //     $model->wasChanged()
        // );
        if (!$model->wasChanged()) return;

        ActivityLogger::log([
            'action' => 'updated',
            'model'  => class_basename($model),
            'old'    => $model->getOriginal() ?? [],
            'new'    => $model->getChanges(),
        ]);

        unset($this->oldData[$model->getKey()]);
    }

    private function shouldSkip()
    {
        return
            app()->runningInConsole() ||
            request()->ajax() ||
            request()->isMethod('GET') ||
            request()->routeIs('kunjungan.get-data');
    }

    private function skip($model)
    {
        return $model instanceof ActivityLog;
    }
}
