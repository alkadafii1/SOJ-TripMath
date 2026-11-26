<?php

namespace App\Livewire\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class AuditIndex extends Component
{
    use WithPagination;

    public string $entity = '';
    public string $action = '';
    public string $actor = '';
    public string $recordId = '';
    public string $from = '';
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('audit.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['entity', 'action', 'actor', 'recordId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        // Recheck on every Livewire request, not only the initial page load.
        abort_unless(Gate::allows('audit.view'), 403);
        $validator = validator([
            'entity' => $this->entity, 'action' => $this->action,
            'actor' => $this->actor, 'recordId' => $this->recordId,
            'from' => $this->from, 'to' => $this->to,
        ], [
            'entity' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'integer', 'min:1'],
            'recordId' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_filter(['nullable', 'date_format:Y-m-d', $this->from !== '' ? 'after_or_equal:from' : null]),
        ]);
        $query = AuditLog::query()->with('user:id,name');
        if ($validator->fails()) {
            $query->whereRaw('1 = 0');
        } else {
            foreach (['entity' => 'entity', 'action' => 'action', 'actor' => 'user_id', 'recordId' => 'entity_id'] as $property => $column) {
                if ($this->{$property} !== '') {
                    $query->where($column, $this->{$property});
                }
            }
            if ($this->from !== '') {
                $query->where('created_at', '>=', $this->from.' 00:00:00');
            }
            if ($this->to !== '') {
                $query->where('created_at', '<', \Illuminate\Support\Carbon::parse($this->to)->addDay()->toDateString().' 00:00:00');
            }
        }

        return view('livewire.audit.index', [
            'logs' => $query->orderByDesc('id')->paginate(20),
            'entities' => AuditLog::query()->distinct()->orderBy('entity')->pluck('entity'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'actors' => User::query()->orderBy('name')->get(['id', 'name']),
            'filterErrors' => $validator->errors()->all(),
        ]);
    }
}
