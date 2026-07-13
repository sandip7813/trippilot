<?php

return [
    'golden_triangle' => [
        'label' => 'Golden Triangle',
        'description' => 'Classic Delhi, Agra, and Jaipur circuit — ideal for first-time North India visitors.',
        'returns_to_origin' => true,
        'waypoint_hints' => [
            'Delhi, India',
            'Agra, India',
            'Jaipur, India',
        ],
        'suggested_nights' => [3, 1, 2],
    ],

    'rajasthan_circuit' => [
        'label' => 'Rajasthan Circuit',
        'description' => 'Royal cities across Rajasthan with forts, lakes, and desert towns.',
        'returns_to_origin' => false,
        'waypoint_hints' => [
            'Jaipur, India',
            'Udaipur, India',
            'Jodhpur, India',
            'Bikaner, India',
        ],
        'suggested_nights' => [2, 2, 2, 1],
    ],

    'northeast_hills' => [
        'label' => 'Northeast Hills',
        'description' => 'Tea country and hill towns — plan road or rail connections between stops.',
        'returns_to_origin' => true,
        'waypoint_hints' => [
            'Darjeeling, India',
            'Gangtok, India',
        ],
        'suggested_nights' => [3, 2],
    ],
];
