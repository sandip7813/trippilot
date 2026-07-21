<?php

namespace App\Services\Knowledge;

class VectorSimilarity
{
    /**
     * @param  array<int, float>  $left
     * @param  array<int, float>  $right
     */
    public static function cosine(array $left, array $right): float
    {
        if ($left === [] || $right === [] || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = 0.0;
        $leftMagnitude = 0.0;
        $rightMagnitude = 0.0;

        foreach ($left as $index => $value) {
            $rightValue = $right[$index];
            $dot += $value * $rightValue;
            $leftMagnitude += $value * $value;
            $rightMagnitude += $rightValue * $rightValue;
        }

        if ($leftMagnitude <= 0.0 || $rightMagnitude <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude));
    }
}
