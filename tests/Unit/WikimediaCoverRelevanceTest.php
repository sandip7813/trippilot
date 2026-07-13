<?php

use App\Services\TripCovers\WikimediaCoverRelevance;

test('wikimedia cover relevance rejects unrelated university campuses', function () {
    $relevance = app(WikimediaCoverRelevance::class);

    $destination = [
        'label' => 'Shantiniketan, West Bengal, India',
        'country_code' => 'in',
    ];

    expect($relevance->matchesDestination(
        $destination,
        'File:Central walkway of Shiv Nadar University campus, Greater Noida.jpg',
    ))->toBeFalse();
});

test('wikimedia cover relevance accepts destination matching titles', function () {
    $relevance = app(WikimediaCoverRelevance::class);

    $destination = [
        'label' => 'Shantiniketan, West Bengal, India',
        'country_code' => 'in',
    ];

    expect($relevance->matchesDestination(
        $destination,
        'File:Visva Bharati University, Shantiniketan.jpg',
        'Visva Bharati University in Shantiniketan',
    ))->toBeTrue();
});
