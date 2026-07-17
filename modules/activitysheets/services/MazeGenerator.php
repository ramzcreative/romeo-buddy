<?php

namespace modules\activitysheets\services;

/**
 * Randomized-depth-first-search ("recursive backtracker") perfect-maze
 * generator — every cell reachable from every other cell via exactly one
 * path, no loops, no isolated pockets. Iterative (explicit stack) rather
 * than recursive so grid size isn't bounded by PHP's call stack.
 */
class MazeGenerator
{
    private const SIZES = [
        'easy' => 10,
        'medium' => 15,
        'hard' => 20,
    ];

    private const OPPOSITE = ['N' => 'S', 'S' => 'N', 'E' => 'W', 'W' => 'E'];
    private const DELTAS = ['N' => [-1, 0], 'S' => [1, 0], 'E' => [0, 1], 'W' => [0, -1]];

    /**
     * @return array{size: int, walls: array<int, array<int, array<string, bool>>>, start: array{0: int, 1: int}, end: array{0: int, 1: int}}
     *   walls[row][col][direction] === true means a wall is present on that side of that cell.
     */
    public function generate(string $difficulty = 'medium'): array
    {
        $size = self::SIZES[$difficulty] ?? self::SIZES['medium'];

        $walls = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $walls[$r][$c] = ['N' => true, 'S' => true, 'E' => true, 'W' => true];
            }
        }

        $visited = array_fill(0, $size, array_fill(0, $size, false));
        $visited[0][0] = true;
        $stack = [[0, 0]];

        while ($stack) {
            [$row, $col] = end($stack);

            $unvisitedNeighbors = [];
            foreach (self::DELTAS as $dir => [$dRow, $dCol]) {
                $r = $row + $dRow;
                $c = $col + $dCol;
                if ($r >= 0 && $r < $size && $c >= 0 && $c < $size && !$visited[$r][$c]) {
                    $unvisitedNeighbors[] = [$dir, $r, $c];
                }
            }

            if (!$unvisitedNeighbors) {
                array_pop($stack);
                continue;
            }

            [$dir, $nextRow, $nextCol] = $unvisitedNeighbors[array_rand($unvisitedNeighbors)];

            $walls[$row][$col][$dir] = false;
            $walls[$nextRow][$nextCol][self::OPPOSITE[$dir]] = false;

            $visited[$nextRow][$nextCol] = true;
            $stack[] = [$nextRow, $nextCol];
        }

        // Entrance/exit: force these two boundary segments open regardless
        // of what the carve left them as, so the maze always has a clear
        // "in" (top-left) and "out" (bottom-right).
        $walls[0][0]['N'] = false;
        $walls[$size - 1][$size - 1]['S'] = false;

        return [
            'size' => $size,
            'walls' => $walls,
            'start' => [0, 0],
            'end' => [$size - 1, $size - 1],
        ];
    }

    /**
     * HTML table with CSS borders standing in for maze walls — every cell
     * always declares a border of the same width on every side, just
     * toggling color (black = wall, white = open), so cell sizing/
     * collapsing behaves identically everywhere and adjoining cells never
     * fight over a shared edge's style (their wall state is always in sync
     * by construction — see generate()).
     *
     * Deliberately not SVG: inline <svg> rendering is unreliable in Dompdf
     * (confirmed — a minimal 2-line test SVG and this maze's ~200-line SVG
     * produced near-identical, essentially empty PDF output; this is a
     * known limitation in dompdf/php-svg-lib, not something fixable here).
     * Table borders are Dompdf's most reliable rendering path — the same
     * technique already used for the word-search grid.
     */
    public function renderHtmlTable(array $maze, int $cellSize = 24): string
    {
        $walls = $maze['walls'];
        $rows = '';

        foreach ($walls as $row) {
            $cells = '';
            foreach ($row as $cell) {
                $cells .= sprintf(
                    '<td style="width:%1$dpx; height:%1$dpx; %2$s"></td>',
                    $cellSize,
                    $this->cellBorderStyle($cell)
                );
            }
            $rows .= "<tr>{$cells}</tr>";
        }

        return "<table class=\"maze-grid\">{$rows}</table>";
    }

    private function cellBorderStyle(array $cell): string
    {
        $side = fn(bool $isWall) => $isWall ? '3px solid black' : '3px solid white';

        return sprintf(
            'border-top:%s; border-right:%s; border-bottom:%s; border-left:%s;',
            $side($cell['N']),
            $side($cell['E']),
            $side($cell['S']),
            $side($cell['W']),
        );
    }
}
