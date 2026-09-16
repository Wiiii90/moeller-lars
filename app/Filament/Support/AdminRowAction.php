<?php

namespace App\Filament\Support;

enum AdminRowAction: string
{
    case MoveUp = 'move-up';
    case MoveDown = 'move-down';
    case Open = 'open';
    case Details = 'details';
    case Edit = 'edit';
    case Publish = 'publish';
    case Unpublish = 'unpublish';
    case Schedule = 'schedule';
    case CancelSchedule = 'cancel-schedule';
    case Restore = 'restore';
    case Archive = 'archive';
    case Delete = 'delete';
    case SkipHome = 'skip-home';
    case Pin = 'pin';
    case Unpin = 'unpin';
    case MarkRead = 'mark-read';
    case MarkUnread = 'mark-unread';
    case Download = 'download';

    public function icon(): AdminIcon
    {
        return match ($this) {
            self::MoveUp => AdminIcon::MoveUp,
            self::MoveDown => AdminIcon::MoveDown,
            self::Open => AdminIcon::OpenEntry,
            self::Details => AdminIcon::Details,
            self::Edit => AdminIcon::Edit,
            self::Publish => AdminIcon::Publish,
            self::Unpublish => AdminIcon::Unpublish,
            self::Schedule => AdminIcon::Schedule,
            self::CancelSchedule, self::Restore => AdminIcon::Refresh,
            self::Archive => AdminIcon::Archive,
            self::Delete => AdminIcon::Delete,
            self::SkipHome => AdminIcon::SkipHome,
            self::Pin => AdminIcon::Pin,
            self::Unpin => AdminIcon::Pinned,
            self::MarkRead => AdminIcon::MarkRead,
            self::MarkUnread => AdminIcon::MarkUnread,
            self::Download => AdminIcon::Download,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MoveUp => 'Move up',
            self::MoveDown => 'Move down',
            self::Open => 'Open',
            self::Details => 'Details',
            self::Edit => 'Edit',
            self::Publish => 'Publish',
            self::Unpublish => 'Unpublish',
            self::Schedule => 'Schedule',
            self::CancelSchedule => 'Cancel schedule',
            self::Restore => 'Restore',
            self::Archive => 'Archive',
            self::Delete => 'Delete',
            self::SkipHome => 'Skip Home',
            self::Pin => 'Pin',
            self::Unpin => 'Unpin',
            self::MarkRead => 'Read',
            self::MarkUnread => 'Unread',
            self::Download => 'Download',
        };
    }

    public function isOrderAction(): bool
    {
        return $this === self::MoveUp || $this === self::MoveDown;
    }

    public function isStateAction(): bool
    {
        return in_array($this, [
            self::Publish,
            self::Unpublish,
            self::CancelSchedule,
            self::Restore,
            self::SkipHome,
        ], true);
    }

    public function isDangerAction(): bool
    {
        return $this === self::Delete;
    }
}
