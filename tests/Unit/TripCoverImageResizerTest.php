<?php

use App\Services\TripCovers\TripCoverImageResizer;
use Tests\TestCase;

uses(TestCase::class);

test('trip cover image resizer creates jpeg thumbnail bytes', function () {
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $jpegBytes = app(TripCoverImageResizer::class)->toJpegThumbnail($pngBytes, 640, 256);

    expect($jpegBytes)->not->toBeNull()
        ->and($jpegBytes)->toStartWith("\xFF\xD8\xFF");
});
