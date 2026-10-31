<?php

return [
    /*
     | Payment methods offered when taking a payment or refund (key => label).
     | Add or remove entries to configure them; the key is what is stored.
     */
    'payment_methods' => [
        'cash' => 'Cash',
        'mobile_money' => 'Mobile money',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card',
        'cheque' => 'Cheque',
    ],

    /* School policy: may a sale be paid in instalments? When false every payment must clear the whole balance. */
    'allow_partial_payment' => env('SUPPLIES_ALLOW_PARTIAL_PAYMENT', true),

    /*
     | Explicit stock override. When false (default) a sale can never exceed the stock on hand.
     | When true, users holding supplies.stock.override may sell more than is on hand: stock stops at zero and the
     | shortfall is recorded as "backordered" on the sale item, to be handed over once stock arrives.
     */
    'allow_stock_override' => env('SUPPLIES_ALLOW_STOCK_OVERRIDE', false),

    /* May staff mark a class requirement as bought elsewhere (externally fulfilled)? */
    'allow_external_fulfilment' => env('SUPPLIES_ALLOW_EXTERNAL_FULFILMENT', true),
];
