<?php
/**
 * Cosine Similarity Algorithm Implementation
 * 
 * Formula:
 *                 A · B           Σ(Ai * Bi)
 *   cos(θ) = ------------- = ---------------------
 *            ||A|| * ||B||   √(Σ(Ai^2)) * √(Σ(Bi^2))
 */

declare(strict_types=1);

class CosineSimilarity
{
    /**
     * Computes the cosine similarity between two numeric vectors.
     *
     * @param float[] $vecA First embedding vector
     * @param float[] $vecB Second embedding vector
     * @return float Cosine similarity in range [-1.0, 1.0], or 0.0 if either vector has zero magnitude
     * @throws InvalidArgumentException If vectors have different dimensions or are empty
     */
    public static function calculate(array $vecA, array $vecB): float
    {
        $countA = count($vecA);
        $countB = count($vecB);

        if ($countA === 0 || $countB === 0) {
            throw new InvalidArgumentException("Vectors cannot be empty.");
        }

        if ($countA !== $countB) {
            throw new InvalidArgumentException("Vector dimensions mismatch: Vector A ({$countA}) vs Vector B ({$countB}).");
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $countA; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];

            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));

        // Clamp floating-point inaccuracies within [-1.0, 1.0]
        return max(-1.0, min(1.0, $similarity));
    }

    /**
     * Converts a raw cosine similarity score [-1.0, 1.0] to a percentage [0.0%, 100.0%].
     * Typically normalized: (score + 1) / 2 * 100 or max(0, score) * 100 for non-negative embeddings.
     * In semantic embeddings, similarity is typically in [0.0, 1.0], where 1.0 is 100%.
     * 
     * @param float $similarity Raw cosine score
     * @param bool $standardZeroToOne If true, clamps negative values to 0% and maps 0..1 to 0..100%
     * @return float Percentage (0.00 - 100.00)
     */
    public static function toPercentage(float $similarity, bool $standardZeroToOne = true): float
    {
        if ($standardZeroToOne) {
            $score = max(0.0, min(1.0, $similarity));
            return round($score * 100, 2);
        }

        // Bipolar normalization: [-1, 1] -> [0%, 100%]
        $normalized = ($similarity + 1.0) / 2.0;
        return round(max(0.0, min(1.0, $normalized)) * 100, 2);
    }
}
