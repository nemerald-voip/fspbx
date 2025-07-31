<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessHourHolidayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'uuid'        => $this->uuid,
            'holiday_type' => $this->holiday_type,
            'description' => $this->description,
            'human_date'  => $this->human_date,
            'action' => $this->action,
            'target'      => $this->whenLoaded('target', function () {
                return [
                    'uuid'      => $this->target->getKey(),
                    'extension' => $this->target->extension ?? null,  
                ];
            }),
            'target_label' => $this->target_label,
        ];
    }
}
