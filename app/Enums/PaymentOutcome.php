<?php

namespace App\Enums;

enum PaymentOutcome: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
