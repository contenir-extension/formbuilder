<?php

declare(strict_types=1);

namespace Contenir\Extension\Formbuilder\Admin\Entries;

use Contenir\FormBuilder\Repository\EntryRepositoryInterface;

/**
 * What the Entries tab calls each status and how it marks it, and the
 * statuses someone may move an entry to, as the built-in module had them.
 */
final class EntryStatus
{
    /**
     * @var array<string, array{string, string}> Label and badge tone, by status
     */
    public const array LABELS = [
        EntryRepositoryInterface::STATUS_PENDING  => ['Pending', 'warning-muted'],
        EntryRepositoryInterface::STATUS_COMPLETE => ['Complete', 'success-muted'],
        EntryRepositoryInterface::STATUS_SPAM     => ['Spam', 'destructive-muted'],
        EntryRepositoryInterface::STATUS_ARCHIVE  => ['Archive', 'secondary'],
        EntryRepositoryInterface::STATUS_REDACTED => ['Redacted', 'outline'],
    ];

    /**
     * @var array<string, array{string, string}> Label and icon, by the status an entry moves to
     */
    public const array CHANGES = [
        EntryRepositoryInterface::STATUS_COMPLETE => ['Mark complete', 'circle-check'],
        EntryRepositoryInterface::STATUS_SPAM     => ['Mark spam', 'alert-octagon'],
        EntryRepositoryInterface::STATUS_ARCHIVE  => ['Archive', 'archive'],
    ];
}
