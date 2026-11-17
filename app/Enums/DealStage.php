<?php

namespace App\Enums;

enum DealStage: string
{
    case Lead = 'lead';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    public static function openValues(): array
    {
        $openStages = array_filter(self::cases(), fn (self $s) => $s->isOpen());
        
        return array_map(
            fn (self $stage) => $stage->value,
            $openStages
        );
    }
}