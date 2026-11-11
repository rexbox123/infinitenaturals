<?php

return [

    /*
    | Store-wide constants. Business details that the owner edits at runtime
    | (address, phone, homepage copy, payment toggles...) live in the
    | `settings` table and are managed in Admin → Settings instead.
    */

    'currency' => env('STORE_CURRENCY', 'USD'),

    'order_number_prefix' => env('STORE_ORDER_PREFIX', 'IN-'),

    // How long a card checkout may stay unpaid before its reserved stock is released.
    'payment_timeout_minutes' => (int) env('STORE_PAYMENT_TIMEOUT_MINUTES', 30),

    'max_quantity_per_line' => 99,

    'products_per_page' => 12,

    /*
    | Roles and the permissions each one grants. "customer" has no admin access.
    */
    'roles' => [
        'admin' => 'Administrator',
        'manager' => 'Store manager',
        'staff' => 'Fulfillment staff',
        'customer' => 'Customer',
    ],

    'permissions' => [
        'admin.access' => ['admin', 'manager', 'staff'],
        'orders.view' => ['admin', 'manager', 'staff'],
        'orders.manage' => ['admin', 'manager', 'staff'],
        'orders.refund' => ['admin', 'manager'],
        'inventory.manage' => ['admin', 'manager', 'staff'],
        'catalog.manage' => ['admin', 'manager'],
        'customers.manage' => ['admin', 'manager'],
        'promotions.manage' => ['admin', 'manager'],
        'content.manage' => ['admin', 'manager'],
        'messages.view' => ['admin', 'manager', 'staff'],
        'settings.manage' => ['admin'],
        'users.manage' => ['admin'],
    ],

    /*
    | The current policy is "ships to all 50 U.S. states; no APO/FPO".
    | Military (AA/AE/AP) and territories are therefore intentionally absent.
    */
    'shipping_states' => [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
        'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
        'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
        'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ],

    'tracking_carriers' => [
        'usps' => ['name' => 'USPS', 'url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={number}'],
        'ups' => ['name' => 'UPS', 'url' => 'https://www.ups.com/track?tracknum={number}'],
        'fedex' => ['name' => 'FedEx', 'url' => 'https://www.fedex.com/fedextrack/?trknbr={number}'],
        'other' => ['name' => 'Other', 'url' => null],
    ],
];
