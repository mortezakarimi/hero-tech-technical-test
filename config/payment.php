<?php

return [
    'timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 1),

    'amount' => (int) env('PAYMENT_AMOUNT', 100),
];
