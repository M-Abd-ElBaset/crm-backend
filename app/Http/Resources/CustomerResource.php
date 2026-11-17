<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'industry' => $this->industry,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'deals_count' => $this->whenCounted('deals'),
            'deals' => DealResource::collection($this->whenLoaded('deals')),
            'created_at' => $this->created_at,
        ];
    }
}
