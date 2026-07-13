<?php

use App\Services\TripCovers\TripCoverPlacePhrase;
use Tests\TestCase;

uses(TestCase::class);

test('trip cover place phrase resolves primary search phrase', function () {
    $phrase = app(TripCoverPlacePhrase::class);

    expect($phrase->resolve([
        'label' => 'Puri',
        'country_code' => 'in',
    ]))->toBe('Puri, India');
});

test('trip cover search phrases include curated shantiniketan queries', function () {
    $queries = app(TripCoverPlacePhrase::class)->curatedQueries([
        'label' => 'Shantiniketan, West Bengal, India',
        'country_code' => 'in',
    ]);

    expect($queries)
        ->toContain('Visva Bharati University Shantiniketan India')
        ->toContain('Bolpur railway station West Bengal India');
});

test('trip cover search phrases include broader regional fallbacks', function () {
    $phrases = app(TripCoverPlacePhrase::class)->searchPhrases([
        'label' => 'Shantiniketan, West Bengal, India',
        'country_code' => 'in',
    ]);

    expect($phrases)
        ->toContain('Shantiniketan, West Bengal, India')
        ->toContain('Shantiniketan, India')
        ->toContain('West Bengal, India');
});

test('trip cover search phrases keep full autocomplete labels first', function () {
    $phrases = app(TripCoverPlacePhrase::class)->searchPhrases([
        'label' => 'Puri, Odisha, India',
        'country_code' => 'in',
    ]);

    expect($phrases[0])->toBe('Puri, Odisha, India')
        ->and($phrases)->toContain('Puri, India')
        ->and($phrases)->toContain('Odisha, India');
});
