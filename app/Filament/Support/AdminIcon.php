<?php

namespace App\Filament\Support;

enum AdminIcon: string
{
    case Dashboard = 'heroicon-o-home';
    case General = 'heroicon-o-globe-alt';
    case MediaFiles = 'heroicon-o-folder-open';
    case Pages = 'heroicon-o-rectangle-stack';
    case Home = 'heroicon-o-building-library';
    case Gallery = 'heroicon-o-photo';
    case Journal = 'heroicon-o-newspaper';
    case CustomPage = 'heroicon-o-identification';
    case NavigationNode = 'heroicon-o-folder';
    case Analytics = 'heroicon-o-chart-bar';
    case Activity = 'heroicon-o-clock';
    case Storage = 'heroicon-o-circle-stack';
    case Preview = 'heroicon-o-viewfinder-circle';
    case Details = 'heroicon-o-information-circle';
    case PreviewZoom = 'heroicon-o-magnifying-glass';
    case PreviewZoomOut = 'heroicon-o-magnifying-glass-minus';
    case PreviewPan = 'heroicon-o-hand-raised';
    case Commit = 'heroicon-o-check';
    case Artwork = 'heroicon-o-paint-brush';
    case BlogPost = 'heroicon-o-document';
    case CvEntry = 'heroicon-o-document-text';
    case Exhibition = 'heroicon-o-calendar';
    case Account = 'heroicon-o-user-circle';

    case Back = 'heroicon-o-arrow-left';
    case OpenPublic = 'heroicon-o-arrow-top-right-on-square';
    case OpenEntry = 'heroicon-o-inbox-arrow-down';
    case Redirect = 'heroicon-o-arrow-right';
    case SkipHome = 'heroicon-o-forward';
    case Publish = 'heroicon-o-eye';
    case Unpublish = 'heroicon-o-eye-slash';
    case Edit = 'heroicon-o-pencil-square';
    case Remove = 'heroicon-o-x-mark';
    case Delete = 'heroicon-o-trash';
    case Detach = 'heroicon-o-link-slash';
    case Inspect = 'heroicon-o-magnifying-glass-plus';
    case Upload = 'heroicon-o-arrow-up-tray';
    case Refresh = 'heroicon-o-arrow-path';
    case AddFromLibrary = 'heroicon-o-plus-circle';
    case MoveBetween = 'heroicon-o-arrows-right-left';
    case MoveUp = 'heroicon-o-arrow-up';
    case MoveDown = 'heroicon-o-arrow-down';
    case Download = 'heroicon-o-arrow-down-tray';
    case MarkRead = 'heroicon-o-envelope-open';
    case MarkUnread = 'heroicon-o-envelope';
    case Pin = 'heroicon-o-bookmark';
    case Pinned = 'heroicon-s-bookmark';
    case DeviceDesktop = 'heroicon-o-computer-desktop';
    case DeviceMobile = 'heroicon-o-device-phone-mobile';
    case ViewList = 'heroicon-o-list-bullet';
    case ViewGrid = 'heroicon-o-squares-2x2';
    case ViewDense = 'heroicon-o-bars-3';

    public function mini(): string
    {
        return str_replace('heroicon-o-', 'heroicon-m-', $this->value);
    }
}
