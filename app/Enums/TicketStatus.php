<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public static function activeValues(): array
    {
        return [self::Open->value, self::InProgress->value, self::WaitingForCustomer->value];
    }

    public static function finishedValues(): array
    {
        return [self::Resolved->value, self::Closed->value];
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Resolved, self::Closed], true);
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}