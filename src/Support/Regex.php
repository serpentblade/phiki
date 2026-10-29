<?php

namespace Phiki\Support;

use Stringable;

class Regex implements Stringable
{
    protected bool $hasAnchor;

    protected array $anchorCache = [];

    protected string $pattern;

    public function __construct(string $pattern)
    {
        $length = strlen($pattern);
        $lastPushedPos = 0;
        $output = [];
        $hasAnchor = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $nextChar = $pattern[$i + 1];

                    if ($nextChar === 'z') {
                        $output[] = substr($pattern, $lastPushedPos, $i - $lastPushedPos);
                        $output[] = '$(?!\\n)(?<!\\n)';
                        $lastPushedPos = $i + 2;
                    } elseif ($nextChar === 'A' || $nextChar === 'G') {
                        $hasAnchor = true;
                    }

                    $i++;
                }
            }
        }

        $this->hasAnchor = $hasAnchor;

        if ($lastPushedPos === 0) {
            $this->pattern = $pattern;
        } else {
            $output[] = substr($pattern, $lastPushedPos);

            $this->pattern = implode('', $output);
        }

        if ($this->hasAnchor) {
            $this->anchorCache = $this->buildAnchorCache();
        }
    }

    /**
     * @param  array<array{ 0: string, 1: int }>|null  $references  The begin captures, for end and while patterns.
     */
    public function get(bool $allowA = false, bool $allowG = false, ?array $references = null): string
    {
        $pattern = $this->resolveAnchors($this->pattern, $allowA, $allowG);

        // Only end and while patterns refer back to the begin captures. Anywhere else,
        // `\1` is an ordinary back-reference to a group in the same pattern.
        if ($references === null) {
            return $pattern;
        }

        $pattern = preg_replace_callback('/\\\\(\d+)/', function ($matches) use ($references) {
            if (! isset($references[$matches[1]][0])) {
                return '';
            }

            // The captured text is matched literally, so escape anything the regex engine would read
            // as syntax. This is the same set of characters vscode-textmate escapes.
            return preg_replace('/[\-\\\\{}*+?|^$.,\[\]()#\s]/', '\\\\$0', $references[$matches[1]][0]);
        }, $pattern);

        return $pattern;
    }

    private function buildAnchorCache(): array
    {
        $A0_G0 = [];
        $A0_G1 = [];
        $A1_G0 = [];
        $A1_G1 = [];

        $len = strlen($this->pattern);

        for ($pos = 0; $pos < $len; $pos++) {
            $ch = $this->pattern[$pos];

            $A0_G0[$pos] = $ch;
            $A0_G1[$pos] = $ch;
            $A1_G0[$pos] = $ch;
            $A1_G1[$pos] = $ch;

            if ($ch === '\\') {
                if ($pos + 1 < $len) {
                    $nextCh = $this->pattern[$pos + 1];

                    if ($nextCh === 'A') {
                        $A0_G0[$pos + 1] = "\u{FFFF}";
                        $A0_G1[$pos + 1] = "\u{FFFF}";
                        $A1_G0[$pos + 1] = 'A';
                        $A1_G1[$pos + 1] = 'A';
                    } elseif ($nextCh === 'G') {
                        $A0_G0[$pos + 1] = "\u{FFFF}";
                        $A0_G1[$pos + 1] = 'G';
                        $A1_G0[$pos + 1] = "\u{FFFF}";
                        $A1_G1[$pos + 1] = 'G';
                    } else {
                        $A0_G0[$pos + 1] = $nextCh;
                        $A0_G1[$pos + 1] = $nextCh;
                        $A1_G0[$pos + 1] = $nextCh;
                        $A1_G1[$pos + 1] = $nextCh;
                    }

                    $pos++;
                }
            }
        }

        return [
            'A0_G0' => implode('', $A0_G0),
            'A0_G1' => implode('', $A0_G1),
            'A1_G0' => implode('', $A1_G0),
            'A1_G1' => implode('', $A1_G1),
        ];
    }

    private function resolveAnchors(string $pattern, bool $allowA, bool $allowG): string
    {
        if (! $this->hasAnchor || ! $this->anchorCache) {
            return $pattern;
        }

        return match (true) {
            $allowA && $allowG => $this->anchorCache['A1_G1'],
            $allowA => $this->anchorCache['A1_G0'],
            $allowG => $this->anchorCache['A0_G1'],
            default => $this->anchorCache['A0_G0'],
        };
    }

    public function __toString(): string
    {
        return $this->get();
    }
}
