<?php

namespace App\Exceptions;

use RuntimeException;

class TicketAlreadyAssignedException extends RuntimeException
{
    public function __construct(string $message = 'This ticket has already been taken by another agent.')
    {
        parent::__construct($message);
    }
}