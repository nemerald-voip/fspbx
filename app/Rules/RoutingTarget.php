<?php

namespace App\Rules;

use App\Services\CallRoutingOptionsService;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RoutingTarget implements DataAwareRule, ImplicitRule
{
    private array $data = [];
    private string $error = '';

    public function __construct(private string $actionField = 'action', private bool $byDestination = false) {}

    public function setData($data): static
    {
        $this->data = $data;

        return $this;
    }

    public function passes($attribute, $value): bool
    {
        $prefix = str_contains($attribute, '.') ? Str::beforeLast($attribute, '.').'.' : '';
        $action = Arr::get($this->data, $prefix.$this->actionField);
        $service = new CallRoutingOptionsService;
        // The action field's rules report missing or unsupported types.
        if (! is_string($action) || ! in_array($action, array_column($service->routingTypes, 'value'), true)) {
            return true;
        }

        $model = $service->mapActionToModel($action);
        $target = null;
        if ($model) {
            $id = $this->byDestination ? $value : (is_array($value) ? ($value['value'] ?? null) : null);
            if ($this->byDestination && is_int($id)) {
                $id = (string) $id;
            }
            if (! is_string($id) || blank($id) || ((! $this->byDestination || $action === 'bridges') && ! Str::isUuid($id))) {
                $this->error = __('A target must be provided when action is selected.');

                return false;
            }
            $target = $service->findTarget($action, $id, $this->byDestination);
        }

        try {
            $service->actionForTarget($action, $target);
        } catch (ValidationException $e) {
            $this->error = $e->errors()['target'][0];

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->error;
    }
}
