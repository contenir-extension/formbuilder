<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use function intdiv;
use function max;
use function min;
use function range;

/**
 * The pages of a form's entries, as the record lists' pagination shows them:
 * the page asked for, kept within those there are, which entries it shows,
 * and up to seven page links around it, with the first and last when they
 * fall outside.
 */
final readonly class EntryPages
{
    public const int PER_PAGE = 50;

    /**
     * How many page links show on each side of the current one.
     */
    private const int SIDE = 3;

    public int $page;

    public int $pages;

    public int $from;

    public int $to;

    /**
     * @var list<int>
     */
    public array $range;

    public ?int $first;

    public ?int $last;

    public function __construct(
        int $asked,
        public int $count,
    ) {
        $this->pages = max(1, intdiv($count + self::PER_PAGE - 1, self::PER_PAGE));
        $this->page  = min(max(1, $asked), $this->pages);
        $start       = max(1, min($this->page - self::SIDE, $this->pages - (2 * self::SIDE)));
        $end         = min($start + (2 * self::SIDE), $this->pages);
        $this->range = range($start, $end);
        $this->first = $start > 1 ? 1 : null;
        $this->last  = $end < $this->pages ? $this->pages : null;
        $this->from  = 0 === $count ? 0 : $this->offset() + 1;
        $this->to    = min($this->page * self::PER_PAGE, $count);
    }

    /**
     * How many entries come before the page's first.
     */
    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }
}
