<?php

namespace modules\activitysheets\services;

/**
 * Builds a word-search grid — forward-only directions (right, down,
 * down-right) since these sheets target young readers (the book's
 * suggested age range is 4-8); backwards/upward words are a much harder
 * search for that age and add nothing but frustration.
 */
class WordSearchGenerator
{
    private const DIRECTIONS = [
        [0, 1],  // right
        [1, 0],  // down
        [1, 1],  // down-right
    ];

    private const MAX_ATTEMPTS_PER_WORD = 200;

    // Both the word list and each word's length come from a CP-editable
    // Table field, not directly from the public request — but this
    // controller's route is public and unauthenticated, and generates a
    // fresh grid on every hit, so these caps exist to keep one oversized
    // entry (a mistake, or a compromised CP account) from ballooning the
    // grid to something that costs real CPU/memory on every download.
    private const MAX_WORDS = 40;
    private const MAX_WORD_LENGTH = 20;
    private const MAX_GRID_SIZE = 30;

    /**
     * @param string[] $words
     * @return array{grid: string[][], words: string[]} $grid is size x size, every cell a single uppercase letter.
     *   $words is the subset that was actually placed (a word that can't fit after MAX_ATTEMPTS_PER_WORD is dropped
     *   rather than forcing an oversized/broken grid).
     */
    public function generate(array $words, int $size = 15): array
    {
        $words = $this->normalizeWords($words);
        $size = min(self::MAX_GRID_SIZE, max($size, $this->minGridSize($words)));

        // Longest first — placed while the grid is emptiest, so it doesn't
        // get crowded out by short words claiming the easy spots first.
        usort($words, fn(string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        $grid = array_fill(0, $size, array_fill(0, $size, null));
        $placed = [];

        foreach ($words as $word) {
            if ($this->tryPlace($grid, $word, $size)) {
                $placed[] = $word;
            }
        }

        $this->fillRemaining($grid, $size);

        return ['grid' => $grid, 'words' => $placed];
    }

    /** @return string[] */
    private function normalizeWords(array $words): array
    {
        $normalized = [];

        foreach (array_slice($words, 0, self::MAX_WORDS) as $word) {
            $clean = mb_strtoupper(preg_replace('/[^a-zA-Z]/', '', (string)$word));
            $clean = mb_substr($clean, 0, self::MAX_WORD_LENGTH);
            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        return array_slice(array_values(array_unique($normalized)), 0, self::MAX_WORDS);
    }

    private function minGridSize(array $words): int
    {
        $longest = 0;
        foreach ($words as $word) {
            $longest = max($longest, mb_strlen($word));
        }

        return min(self::MAX_GRID_SIZE, max(10, $longest + 2));
    }

    private function tryPlace(array &$grid, string $word, int $size): bool
    {
        $letters = mb_str_split($word);
        $length = count($letters);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS_PER_WORD; $attempt++) {
            [$dRow, $dCol] = self::DIRECTIONS[array_rand(self::DIRECTIONS)];

            $maxRow = $dRow ? $size - $length : $size - 1;
            $maxCol = $dCol ? $size - $length : $size - 1;
            if ($maxRow < 0 || $maxCol < 0) {
                continue;
            }

            $row = random_int(0, $maxRow);
            $col = random_int(0, $maxCol);

            if ($this->fits($grid, $letters, $row, $col, $dRow, $dCol)) {
                $this->place($grid, $letters, $row, $col, $dRow, $dCol);
                return true;
            }
        }

        return false;
    }

    private function fits(array $grid, array $letters, int $row, int $col, int $dRow, int $dCol): bool
    {
        foreach ($letters as $i => $letter) {
            $existing = $grid[$row + $dRow * $i][$col + $dCol * $i] ?? null;
            if ($existing !== null && $existing !== $letter) {
                return false;
            }
        }

        return true;
    }

    private function place(array &$grid, array $letters, int $row, int $col, int $dRow, int $dCol): void
    {
        foreach ($letters as $i => $letter) {
            $grid[$row + $dRow * $i][$col + $dCol * $i] = $letter;
        }
    }

    private function fillRemaining(array &$grid, int $size): void
    {
        $alphabet = range('A', 'Z');

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($grid[$r][$c] === null) {
                    $grid[$r][$c] = $alphabet[array_rand($alphabet)];
                }
            }
        }
    }
}
