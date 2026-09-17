<?php

namespace modules\stablestwigextensions\console\controllers;

use Craft;
use craft\console\Controller;
use modules\themepicker\services\BlockRules;
use modules\themepicker\services\ThemeRegistry;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * What a theme's block rules come to on this site: blocks offered, layouts hidden, fields hidden per block and per
 * nested type, and stale rules. For skills and for checking a theme file by hand. See
 * docs/theme-designer-blocks-spec.md §4.4.
 */
class BlocksController extends Controller
{
    /** Site handle whose stored theme to use. Defaults to the primary site. */
    public ?string $site = null;

    /** Theme to report on instead of the site's stored theme. */
    public ?string $theme = null;

    /** Print JSON (for skills) instead of a readable report. */
    public bool $json = false;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'site', 'theme', 'json'];
    }

    /**
     * Usage: php craft stablestwigextensions/blocks/offered [--theme=handle] [--site=handle] [--json]
     *
     * Exits 1 when a rule is stale, so a skill or a check can rely on it.
     */
    public function actionOffered(): int
    {
        $sites = Craft::$app->getSites();
        $site = $this->site !== null ? $sites->getSiteByHandle($this->site) : $sites->getPrimarySite();

        if ($site === null) {
            $this->stderr("Unknown site: {$this->site}\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $registry = new ThemeRegistry();
        $handle = $this->theme ?? $registry->getActiveThemeHandle($site->id);

        if (!preg_match('/^[a-z0-9\-]+$/', (string)$handle) || !$registry->isSiteTheme($handle)) {
            $this->stderr("\"{$handle}\" isn't a site theme.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $resolved = (new BlockRules('blockfields'))->resolve($handle);
        $report = ['theme' => $handle, 'site' => $site->handle, 'blocks' => [], 'stale' => $resolved['stale']];

        foreach ($resolved['blocks'] as $blockHandle => $block) {
            $report['blocks'][$blockHandle] = [
                'name' => $block['type']->name,
                'offered' => $block['available'],
                'offeredBecause' => $block['availableSource'] === 'config' ? 'rule' : ($block['available'] ? 'template found' : 'no template in this theme or _base'),
                'hiddenLayouts' => $block['layoutOptions'],
                'ownFields' => $block['own'],
                'childFields' => $block['children'],
            ];
        }

        if ($this->json) {
            $this->stdout(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        } else {
            $this->printReport($report);
        }

        return $resolved['stale'] === [] ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function printReport(array $report): void
    {
        $this->stdout("Blocks for theme \"{$report['theme']}\" (site {$report['site']})\n\n", Console::BOLD);

        foreach ($report['blocks'] as $handle => $block) {
            $this->stdout(sprintf('  %-14s', $handle), $block['offered'] ? Console::FG_GREEN : Console::FG_YELLOW);
            $this->stdout(($block['offered'] ? 'offered' : 'NOT offered') . " ({$block['offeredBecause']})\n");

            foreach ($block['hiddenLayouts'] as $field => $values) {
                $this->stdout("      layouts hidden  {$field}: " . implode(', ', $values) . "\n");
            }

            $this->printFieldRules('own', $block['ownFields']);

            foreach ($block['childFields'] as $child => $rules) {
                $this->printFieldRules($child, $rules);
            }
        }

        if ($report['stale'] !== []) {
            $this->stderr("\nStale rules (ignored):\n", Console::FG_YELLOW);

            foreach ($report['stale'] as $message) {
                $this->stderr("  {$message}\n", Console::FG_YELLOW);
            }
        }
    }

    private function printFieldRules(string $label, array $rules): void
    {
        if ($rules['hidden'] !== []) {
            $this->stdout("      {$label} hidden  " . implode(', ', $rules['hidden']) . "\n");
        }

        foreach ($rules['perLayout'] as $layoutField => $fields) {
            foreach ($fields as $field => $values) {
                $this->stdout("      {$label} {$field}  only on {$layoutField}: " . implode(', ', $values) . "\n");
            }
        }
    }
}
