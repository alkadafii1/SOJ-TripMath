<?php

namespace App\Livewire\Concerns;

use App\Support\Options;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

trait LoadsActiveMasters
{
    // Request-local only: rates are neither persisted in Livewire state nor cached across users.
    protected array $activeMasterRows = [];

    protected function activeMasters(string $type, bool $withVendor = false): Collection
    {
        $key = $type.':'.(int) $withVendor;
        if (isset($this->activeMasterRows[$key])) {
            return $this->activeMasterRows[$key];
        }
        $class = array_key_exists($type, Options::ITEM_TYPES) ? Relation::getMorphedModel($type) : null;
        if (! $class) {
            return new Collection;
        }
        $query = $class::active();
        if ($withVendor && method_exists(new $class, 'vendor')) {
            $query->with('vendor');
        }

        return $this->activeMasterRows[$key] = $query->get();
    }
}
