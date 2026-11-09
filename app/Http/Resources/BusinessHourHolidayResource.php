<?php

namespace App\Http\Resources;

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
            'routing_summary' => $this->resource->routingSummary(),
        ];
    }
}
