<?php

use Phiki\Grammar\Grammar;
use Phiki\Highlighting\Highlighter;
use Phiki\Phiki;
use Phiki\Theme\Theme;

it('can be constructed', function () {
    $theme = Theme::parse([
        'name' => 'test',
        'colors' => [
            'editor.background' => '#000',
            'editor.foreground' => '#fff',
        ],
        'tokenColors' => [],
    ]);

    expect(new Highlighter(['default' => $theme]))->toBeInstanceOf(Highlighter::class);
});

it('highlights each token the same as matching its scopes against the theme', function (array $themes) {
    $code = <<<'PHP'
    <?php

    class Calculator
    {
        public function add(int $a, int $b): int
        {
            return $a + $b;
        }

        public function subtract(int $a, int $b): int
        {
            return $a - $b;
        }

        public function multiply(int $a, int $b): int
        {
            return $a * $b;
        }
    }
    PHP;

    $phiki = new Phiki;
    $tokens = $phiki->codeToTokens($code, Grammar::Php);
    $highlighted = $phiki->codeToHighlightedTokens($code, Grammar::Php, $themes);

    // The sample must repeat scope stacks, or it doesn't exercise reuse.
    $flat = array_merge(...$tokens);
    $stacks = array_unique(array_map(fn ($token) => implode(' ', $token->scopes), $flat));
    expect(count($stacks))->toBeLessThan(count($flat));

    $parsed = array_map(fn (Theme $theme) => $phiki->environment->themes->resolve($theme), $themes);

    foreach ($tokens as $i => $line) {
        foreach ($line as $j => $token) {
            $expected = [];

            foreach ($parsed as $id => $theme) {
                if ($matched = $theme->match($token->scopes)) {
                    $expected[$id] = $matched;
                }
            }

            expect($highlighted[$i][$j]->settings)->toEqual($expected);
        }
    }
})->with([
    'single theme' => [['default' => Theme::GithubDark]],
    'multiple themes' => [['light' => Theme::GithubLight, 'dark' => Theme::GithubDark, 'dracula' => Theme::Dracula]],
]);
