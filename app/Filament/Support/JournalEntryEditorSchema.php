<?php

namespace App\Filament\Support;

use App\Domain\Content\ExhibitionGeocodingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class JournalEntryEditorSchema
{
    public static function blog(Schema $schema): Schema { return $schema->components(self::blogComponents()); }
    public static function exhibition(Schema $schema): Schema { return $schema->components(self::exhibitionComponents()); }

    /** @return array<int,mixed> */
    public static function blogComponents(): array
    {
        return [AdminForm::section('Entry')->schema([self::title(), self::slug(220), Textarea::make('excerpt')->maxLength(1000)->nullable()->columnSpanFull()])->columns(2), self::contentSection(), self::mediaSection()];
    }

    /** @return array<int,mixed> */
    public static function exhibitionComponents(): array
    {
        return [
            AdminForm::section('Entry')->schema([self::title(), self::slug(180)])->columns(2), self::contentSection(), self::mediaSection(),
            AdminForm::section('Exhibition details')->schema([
                DatePicker::make('starts_on')->label('Starts')->nullable(), DatePicker::make('ends_on')->label('Ends')->afterOrEqual('starts_on')->nullable(),
                TextInput::make('date_text')->label('Display date override')->maxLength(160)->nullable()->columnSpanFull(),
                DateTimePicker::make('vernissage_at')->label('Vernissage')->seconds(false)->nullable()->columnSpanFull(),
                TextInput::make('venue')->label('Venue')->maxLength(240)->nullable()->columnSpanFull(), self::address(),
                Hidden::make('latitude'), Hidden::make('longitude'), Hidden::make('geocoded_at'),
                TextInput::make('external_url')->label('Venue / event website')->url()->maxLength(2048)->helperText('Optional link to the venue or event page.')->nullable()->columnSpanFull(),
            ])->columns(2),
        ];
    }

    private static function title(): TextInput
    {
        return TextInput::make('title')->required()->maxLength(240)->live(onBlur:true)->afterStateUpdated(function(?string $state,Set $set,Get $get):void { if(blank($get('slug'))&&filled($state)){$set('slug',Str::slug($state));} });
    }
    private static function slug(int $max): TextInput { return TextInput::make('slug')->label('Public URL slug')->required()->maxLength($max)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'); }

    private static function contentSection(): mixed
    {
        return AdminForm::section('Content')->schema([
            Builder::make('content_blocks')->label('Content')->blocks([
                Block::make('text')->label('Text')->schema([MarkdownEditor::make('markdown')->label('Content')->toolbarButtons([['bold','italic','link'],['bulletList','orderedList'],['undo','redo']])->extraAttributes(['class'=>'journal-entry-editor__content'])->nullable()->columnSpanFull()]),
                Block::make('image')->label('Insert image from Media Files')->schema([Hidden::make('embed_key'), MediaAssetSelect::makeId('media_asset_id','Inline image',imagesOnly:true)->required(), TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable()]),
            ])->default([['type'=>'text','data'=>['markdown'=>'']]])->addActionLabel('Add content')->blockNumbers(false)->reorderableWithButtons()->reorderableWithDragAndDrop(false)->columnSpanFull(),
        ]);
    }

    private static function mediaSection(): mixed
    {
        return AdminForm::section('Media')->schema([
            MediaAssetSelect::makeId('cover_media_asset_id','Cover image',imagesOnly:true)->nullable()->columnSpanFull(),
            TextInput::make('cover_alt_text_override')->label('Cover ALT override')->maxLength(500)->nullable()->columnSpanFull(),
            Repeater::make('gallery_images')->label('Gallery images')->schema([MediaAssetSelect::makeId('media_asset_id','Image',imagesOnly:true)->required(),TextInput::make('alt_text_override')->label('ALT override')->maxLength(500)->nullable()])
                ->addActionLabel('Add image')->reorderableWithButtons()->reorderableWithDragAndDrop(false)->columnSpanFull(),
        ]);
    }

    private static function address(): TextInput
    {
        return TextInput::make('location_text')->label('Address')->maxLength(500)->live(onBlur:true)
            ->afterStateUpdated(function(Set $set):void{$set('latitude',null);$set('longitude',null);$set('geocoded_at',null);})
            ->belowContent(Action::make('findAddress')->label('Find address')->modalHeading('Find address')->modalSubmitActionLabel('Use address')
                ->schema(function(Get $schemaGet):array {
                    $options=app(ExhibitionGeocodingService::class)->options((string)$schemaGet('location_text'));
                    if($options===[]){Notification::make()->title('Address could not be found')->warning()->send();}
                    return [Select::make('address_candidate')->label('Address result')->options($options)->required()];
                })
                ->action(function(array $data,Set $schemaSet):void{
                    $candidate=app(ExhibitionGeocodingService::class)->decode((string)($data['address_candidate']??''));
                    $schemaSet('location_text',$candidate['address']);$schemaSet('latitude',$candidate['latitude']);$schemaSet('longitude',$candidate['longitude']);$schemaSet('geocoded_at',now()->toIso8601String());
                }))
            ->nullable()->columnSpanFull();
    }
}
