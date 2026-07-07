<?php

use App\Support\BudgetBreakdownNormalizer;
use Tests\TestCase;

uses(TestCase::class);

test('budget breakdown normalizer extracts nested category amounts', function () {
    $normalized = BudgetBreakdownNormalizer::normalize([
        'currency' => 'INR',
        'estimated_total' => 25000,
        'breakdown' => [
            'accommodation' => 12000,
            'food' => 5000,
            'transport' => 4000,
            'activities' => 3000,
            'miscellaneous' => 1000,
        ],
    ]);

    expect($normalized['currency'])->toBe('INR')
        ->and($normalized['estimated_total'])->toBe(25000.0)
        ->and($normalized['breakdown'])->toMatchArray([
            'accommodation' => 12000.0,
            'food' => 5000.0,
            'transport' => 4000.0,
            'activities' => 3000.0,
            'miscellaneous' => 1000.0,
        ]);
});

test('budget breakdown normalizer supports list shaped breakdown items', function () {
    $normalized = BudgetBreakdownNormalizer::normalize([
        'estimated_total' => 9000,
        'breakdown' => [
            ['category' => 'lodging', 'amount' => 6000],
            ['category' => 'food', 'amount' => 3000],
        ],
    ]);

    expect($normalized['breakdown'])->toMatchArray([
        'lodging' => 6000.0,
        'food' => 3000.0,
    ]);
});

test('budget breakdown normalizer sums line items when total is missing', function () {
    $normalized = BudgetBreakdownNormalizer::normalize([
        'breakdown' => [
            'accommodation' => 7000,
            'food' => 3000,
        ],
    ]);

    expect($normalized['estimated_total'])->toBe(10000.0)
        ->and($normalized['currency'])->toBe('INR');
});
