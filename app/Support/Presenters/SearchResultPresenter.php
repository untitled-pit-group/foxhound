<?php declare(strict_types=1);
namespace App\Support\Presenters;
use App\Support\Id;

class SearchResultPresenter
{
    const
        START_SEL = '<<<',
        STOP_SEL = '>>>';
    /**
     * @return [string,[[int,int]]]
     */
    private function parseHeadline(string $headline): array
    {
        $text = '';
        $len = 0;
        $ranges = [];

        // TODO[pn]: This assumes the text file is in UTF-8. This should be
        // validated before indexing.
        while ($headline !== '') {
            $i = strpos($headline, self::START_SEL);
            if ($i === false) {
                $text .= $headline;
                break;
            } else if ($i !== 0) {
                $prefix = substr($headline, 0, $i);
                $text .= $prefix;
                $len += mb_strlen($prefix, 'UTF-8');
            }
            $headline = substr($headline, $i + strlen(self::START_SEL));
            $rangeStart = $len;

            $i = strpos($headline, self::STOP_SEL);
            if ($i === false) {
                $highlight = $headline;
                $headline = '';
            } else {
                $highlight = substr($headline, 0, $i);
            }
            $highlightLen = mb_strlen($highlight, 'UTF-8');

            $text .= $highlight;
            $len += $highlightLen;

            $ranges[] = [
                $rangeStart,
                $rangeStart + $highlightLen - 1,
            ];

            if ($headline !== '') {
                $headline = substr($headline, $i + strlen(self::STOP_SEL));
            }
        }

        return [$text, $ranges];
    }

    public function present(\stdClass $searchResult): array
    {
        [$headlineText, $ranges] = $this->parseHeadline($searchResult->headline);

        // TODO[pn]: Support for results with content locators
        return [
            'i' => Id::encode($searchResult->id),
            'f' => $headlineText,
            'r' => $ranges,
        ];
    }
}
