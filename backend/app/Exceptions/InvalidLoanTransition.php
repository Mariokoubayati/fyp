<?php

namespace App\Exceptions;

use App\Enums\LoanStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InvalidLoanTransition extends ConflictHttpException
{
    public function __construct(LoanStatus $from, LoanStatus $to)
    {
        parent::__construct("A {$from->value} loan cannot become {$to->value}.");
    }
}
