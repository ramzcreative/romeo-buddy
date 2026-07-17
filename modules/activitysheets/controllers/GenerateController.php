<?php

namespace modules\activitysheets\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\web\Controller;
use Dompdf\Dompdf;
use Dompdf\Options;
use modules\activitysheets\services\MazeGenerator;
use modules\activitysheets\services\WordSearchGenerator;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves the downloadable PDF for an 'Activity Sheet' pageBuilder block —
 * see _blocks/activitySheet.twig for the download link and
 * migrations/m260717_060753_addActivitySheetBlock.php for the block's
 * field shape. Public/anonymous by design (a kid downloading a coloring
 * sheet is never logged into Craft).
 *
 * Regenerates a fresh word-search/maze layout on every request rather than
 * caching one — same word list or difficulty, different puzzle each time,
 * so repeat visits/prints aren't just the same solved sheet.
 */
class GenerateController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(int $blockId): Response
    {
        // Entry::find() (not Craft::$app->getElements()->getElementById())
        // deliberately — getElementById()'s own docblock says "the
        // element's status will not be a factor," which would let a
        // disabled, drafted, or provisional-draft block be downloaded by
        // anyone who guesses/enumerates its (small, sequential) ID, ahead
        // of it actually being published. A plain query's defaults already
        // exclude drafts/provisional drafts/revisions; status(LIVE) also
        // excludes disabled/pending/expired.
        $block = Entry::find()
            ->id($blockId)
            ->status(Entry::STATUS_LIVE)
            ->one();

        if (!$block instanceof Entry || $block->type->handle !== 'activitySheet') {
            throw new NotFoundHttpException();
        }

        // A block can be individually "live" while the page it's nested on
        // is disabled/still a draft — the owning page's status has to be
        // checked separately.
        $owner = $block->getOwner();
        if (!$owner || $owner->getStatus() !== Entry::STATUS_LIVE) {
            throw new NotFoundHttpException();
        }

        $title = $block->title ?: 'Activity Sheet';
        $intro = trim(strip_tags((string)($block->activitySheetIntro ?? '')));
        $type = $block->activitySheetType?->value ?? 'wordSearch';

        if ($type === 'maze') {
            $html = $this->renderMaze($title, $intro, (string)($block->activitySheetDifficulty?->value ?? 'medium'));
        } else {
            $html = $this->renderWordSearch($title, $intro, $block->activitySheetWords ?? []);
        }

        return $this->outputPdf($html, $title);
    }

    private function renderWordSearch(string $title, string $intro, array $wordRows): string
    {
        $words = array_map(fn(array $row) => $row['word'] ?? '', $wordRows);
        $puzzle = (new WordSearchGenerator())->generate($words);

        return $this->getView()->renderTemplate('activitysheets/pdf/wordSearch', [
            'title' => $title,
            'intro' => $intro,
            'grid' => $puzzle['grid'],
            'words' => $puzzle['words'],
        ]);
    }

    private function renderMaze(string $title, string $intro, string $difficulty): string
    {
        $generator = new MazeGenerator();
        $maze = $generator->generate($difficulty);
        $mazeTable = $generator->renderHtmlTable($maze);

        return $this->getView()->renderTemplate('activitysheets/pdf/maze', [
            'title' => $title,
            'intro' => $intro,
            'mazeTable' => $mazeTable,
        ]);
    }

    private function outputPdf(string $html, string $title): Response
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false); // no fetching remote images/stylesheets — SSRF guard
        $options->setIsPhpEnabled(false); // no <script type="text/php"> execution — already Dompdf's default, set explicitly since this renders content sourced from CP fields

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $filename = ElementHelper::generateSlug($title) ?: 'activity-sheet';

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'application/pdf');
        $response->getHeaders()->set('Content-Disposition', "attachment; filename=\"{$filename}.pdf\"");
        $response->data = $dompdf->output();

        return $response;
    }
}
