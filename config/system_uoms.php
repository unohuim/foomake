<?php

return [
    'categories' => [
        [
            'key' => 'mass',
            'name' => 'Mass',
        ],
        [
            'key' => 'volume',
            'name' => 'Volume',
        ],
        [
            'key' => 'count',
            'name' => 'Count',
        ],
        [
            'key' => 'length',
            'name' => 'Length',
        ],
    ],
    'uoms' => [
        [
            'category_key' => 'mass',
            'name' => 'Gram',
            'symbol' => 'g',
        ],
        [
            'category_key' => 'mass',
            'name' => 'Kilogram',
            'symbol' => 'kg',
        ],
        [
            'category_key' => 'mass',
            'name' => 'Pound',
            'symbol' => 'lb',
        ],
        [
            'category_key' => 'mass',
            'name' => 'Ounce',
            'symbol' => 'oz',
        ],
        [
            'category_key' => 'volume',
            'name' => 'Milliliter',
            'symbol' => 'ml',
        ],
        [
            'category_key' => 'volume',
            'name' => 'Liter',
            'symbol' => 'l',
        ],
        [
            'category_key' => 'count',
            'name' => 'Each',
            'symbol' => 'ea',
        ],
        [
            'category_key' => 'count',
            'name' => 'Piece',
            'symbol' => 'pc',
        ],
        [
            'category_key' => 'length',
            'name' => 'Centimeter',
            'symbol' => 'cm',
        ],
        [
            'category_key' => 'length',
            'name' => 'Meter',
            'symbol' => 'm',
        ],
    ],
    'conversions' => [
        'mass' => [
            [
                'from' => 'kg',
                'to' => 'g',
                'multiplier' => '1000.00000000',
            ],
            [
                'from' => 'lb',
                'to' => 'oz',
                'multiplier' => '16.00000000',
            ],
        ],
        'volume' => [
            [
                'from' => 'l',
                'to' => 'ml',
                'multiplier' => '1000.00000000',
            ],
        ],
        'length' => [
            [
                'from' => 'm',
                'to' => 'cm',
                'multiplier' => '100.00000000',
            ],
        ],
    ],
];
