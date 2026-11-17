<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'title' => $this->title,
            'value' => $this->value, // exact string like "1500.50"
            'stage' => $this->stage->value,
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'description' => $this->description,
            'lost_reason' => $this->lost_reason,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
