<?php

namespace Phiki\TextMate;

/**
 * Remembers where each regex last matched in the current line, so a later search
 * further along the same line can reuse the result instead of scanning again.
 *
 * A match found at position M by searching from position S is also the first match
 * from any position between S and M, and a regex with no match from S has none
 * further along either. The one exception is `\G`, which anchors to wherever the
 * search starts, so regexes containing it are never cached.
 */
class SearchResultCache
{
    /**
     * The line the cached results belong to.
     */
    protected ?string $line = null;

    /**
     * The position each regex was searched from, and the [start, length] it found, or false.
     *
     * @var array<string, array{ 0: int, 1: array{ 0: int, 1: int }|false }>
     */
    protected array $results = [];

    /**
     * Start searching the given line, forgetting results from any other line.
     */
    public function useLine(string $line): void
    {
        if ($line !== $this->line) {
            $this->line = $line;
            $this->results = [];
        }
    }

    /**
     * Get the result of searching for the regex from the given position, or null if it isn't known.
     *
     * @return array{ 0: int, 1: int }|false|null
     */
    public function get(string $regex, int $position): array|false|null
    {
        if (! isset($this->results[$regex])) {
            return null;
        }

        [$from, $result] = $this->results[$regex];

        if ($position < $from || ($result !== false && $result[0] < $position)) {
            return null;
        }

        return $result;
    }

    /**
     * Remember the result of searching for the regex from the given position.
     *
     * @param  array{ 0: int, 1: int }|false  $result
     */
    public function put(string $regex, int $position, array|false $result): void
    {
        if (str_contains($regex, '\G')) {
            return;
        }

        $this->results[$regex] = [$position, $result];
    }
}
