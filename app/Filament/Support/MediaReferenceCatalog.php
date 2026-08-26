<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaReferenceQuery;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\ArtworkCategory;
use App\Models\CustomPageSetting;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Pivot;

final class MediaReferenceCatalog
{
    /** @var EloquentCollection<int, SiteSection>|null */private ?EloquentCollection $nodes=null;
    public function __construct(private readonly SiteNodePresentation $presentation,private readonly MediaReferenceQuery $referenceQuery) {}

    /** @return list<array{label:string,options:list<array{value:string,label:string}>}> */
    public function destinationGroups():array
    {
        $groups=[SiteNodeType::Gallery->value=>['label'=>'Galleries','options'=>[]],SiteNodeType::Journal->value=>['label'=>'Journals','options'=>[]],SiteNodeType::CustomPage->value=>['label'=>'Custom pages','options'=>[]]];
        $broad=[SiteNodeType::Gallery->value=>'Any Gallery',SiteNodeType::Journal->value=>'Any Journal',SiteNodeType::CustomPage->value=>'Any Custom Page'];
        foreach($this->nodes() as $node){$type=$node->nodeType();if(isset($groups[$type->value])){$groups[$type->value]['options'][]=['value'=>'node:'.$node->getKey(),'label'=>$this->nodeLabel($node)];}}
        foreach($groups as $type=>&$group){if($group['options']!==[]){array_unshift($group['options'],['value'=>'kind:'.$type,'label'=>$broad[$type]]);}}unset($group);
        $groups['site']=['label'=>'Site','options'=>[['value'=>'site-identity','label'=>'Site identity']]];return array_values(array_filter($groups,fn(array $g):bool=>$g['options']!==[]));
    }

    /** @param Builder<MediaAsset> $query */public function applyUsageFilter(Builder $query,string $usage):void{if($usage==='all'){return;}if($usage==='in-use'){$this->referenceQuery->apply($query,true);return;}if($usage==='unreferenced'){$this->referenceQuery->apply($query,false);return;}$this->applyDestinationFilter($query,$usage);}
    /** @param Builder<MediaAsset> $query */
    public function applyDestinationFilter(Builder $query,string $destination):void
    {
        if($destination==='all'){return;}if($destination==='site-identity'){$query->whereHas('siteIdentitySettings');return;}
        if(str_starts_with($destination,'kind:')){$type=SiteNodeType::tryFrom(substr($destination,5));if($type===null||!in_array($type,[SiteNodeType::Gallery,SiteNodeType::Journal,SiteNodeType::CustomPage],true)){$query->whereRaw('1 = 0');return;}$this->applyKindDestination($query,$type);return;}
        $node=$this->nodeForDestination($destination);if(! $node instanceof SiteSection){$query->whereRaw('1 = 0');return;}$type=$node->nodeType();
        if($type===SiteNodeType::Gallery){$this->applyGalleryDestination($query,$node);return;}if($type===SiteNodeType::Journal){$this->applyJournalDestination($query,$node);return;}if($type===SiteNodeType::CustomPage){$this->applyCustomPageDestination($query,$node);return;}$query->whereRaw('1 = 0');
    }

    /** @return array{files:int,images:int,videos:int,audio:int,unreferenced:int,bytes:int} */public function libraryMetrics():array{$available=MediaAsset::query()->where('state','available')->whereIn('mime_type',MediaTypePolicy::acceptedMimeTypes());$unreferenced=clone $available;$this->referenceQuery->apply($unreferenced,false);return['files'=>(clone $available)->count(),'images'=>(clone $available)->whereIn('mime_type',MediaTypePolicy::IMAGE_MIME_TYPES)->count(),'videos'=>(clone $available)->whereIn('mime_type',MediaTypePolicy::VIDEO_MIME_TYPES)->count(),'audio'=>(clone $available)->whereIn('mime_type',MediaTypePolicy::AUDIO_MIME_TYPES)->count(),'unreferenced'=>$unreferenced->count(),'bytes'=>(int)(clone $available)->sum('byte_size')];}
    /** @param Builder<MediaAsset> $query */public function applyReferenceFilter(Builder $query,bool $referenced):void{$this->referenceQuery->apply($query,$referenced);}
    /** @param Builder<MediaAsset> $query */public function eagerLoad(Builder $query):void{$query->with(['variants','artworks.category.siteSection','journalEntryMedia.blogPost.siteSection','journalEntryMedia.exhibition.siteSection','siteIdentitySettings']);}
    public function loadAssetReferences(MediaAsset $asset):void{$asset->loadMissing(['artworks.category.siteSection','journalEntryMedia.blogPost.siteSection','journalEntryMedia.exhibition.siteSection','siteIdentitySettings']);}

    /** @return list<array{type:string,label:string,url:?string}> */
    public function references(MediaAsset $asset):array
    {
        $rows=[];$kind=MediaTypePolicy::kind((string)$asset->getAttribute('mime_type'));$noun=match($kind){'video'=>'video','audio'=>'audio',default=>'image'};
        foreach($asset->getRelation('artworks') as $artwork){$category=$artwork->getRelationValue('category');$node=$category instanceof ArtworkCategory?$category->getRelationValue('siteSection'):null;$gallery=$node instanceof SiteSection?$this->nodeLabel($node):trim((string)($category?->getAttribute('name')??'Gallery'));$pivot=$artwork->getRelationValue('pivot');$role=$pivot instanceof Pivot?(string)$pivot->getAttribute('role'):'additional';$rows[]=['type'=>'Gallery: '.$gallery,'label'=>(string)$artwork->getAttribute('title').' — '.($role==='primary'?'Primary ':'Additional ').$noun,'url'=>$node instanceof SiteSection?$this->presentation->workspaceUrl($node):null];}
        foreach($asset->getRelation('journalEntryMedia') as $usage){if(! $usage instanceof JournalEntryMedia){continue;}$entry=$usage->getRelationValue('blogPost');$template='Blog';if($entry===null){$entry=$usage->getRelationValue('exhibition');$template='Exhibitions';}if($entry===null){continue;}$node=$entry->getRelationValue('siteSection');$role=match((string)$usage->getAttribute('role')){JournalEntryMedia::ROLE_COVER=>'Cover image',JournalEntryMedia::ROLE_INLINE=>'Inline image',JournalEntryMedia::ROLE_GALLERY=>'Gallery image',default=>'Image'};$rows[]=['type'=>'Journal: '.$template,'label'=>(string)$entry->getAttribute('title').' — '.$role,'url'=>$node instanceof SiteSection?$this->presentation->workspaceUrl($node):null];}
        foreach($this->customPageNodes() as $node){$settings=$node->getRelationValue('customPageSetting');if($settings instanceof CustomPageSetting&&$this->referenceQuery->customPageReferencesAsset($settings,(int)$asset->getKey())){$rows[]=['type'=>'Custom Page: '.$this->nodeLabel($node),'label'=>'Image component','url'=>$this->presentation->workspaceUrl($node)];}}
        foreach($asset->getRelation('siteIdentitySettings') as $setting){$rows[]=['type'=>'Site identity','label'=>'Favicon','url'=>PublicContentSettingResource::getUrl('edit',['record'=>$setting->getKey()])];}
        $unique=[];foreach($rows as $row){$unique[implode('|',[$row['type'],$row['label'],$row['url']??''])]=$row;}return array_values($unique);
    }

    /** @return EloquentCollection<int, SiteSection> */private function nodes():EloquentCollection{if($this->nodes instanceof EloquentCollection){return $this->nodes;}$nodes=SiteSection::query()->with('customPageSetting')->orderBy('position')->orderBy('id')->get();return $this->nodes=$nodes;}
    /** @return EloquentCollection<int, SiteSection> */private function customPageNodes():EloquentCollection{return $this->nodes()->filter(fn(SiteSection $node):bool=>$node->nodeType()===SiteNodeType::CustomPage);}
    private function nodeForDestination(string $destination):?SiteSection{if(!preg_match('/^node:(\d+)$/',$destination,$matches)){return null;}return $this->nodes()->first(fn(SiteSection $node):bool=>(int)$node->getKey()===(int)$matches[1]);}
    /** @param Builder<MediaAsset> $query */private function applyKindDestination(Builder $query,SiteNodeType $type):void
    {
        if($type===SiteNodeType::Gallery){$ids=$this->nodes()->filter(fn(SiteSection $n):bool=>$n->nodeType()===SiteNodeType::Gallery)->pluck('artwork_category_id')->filter(fn($id):bool=>is_numeric($id))->map(fn($id):int=>(int)$id)->values()->all();$ids===[]?$query->whereRaw('1 = 0'):$query->whereHas('artworks',fn(Builder $a)=>$a->whereIn('artwork_category_id',$ids));return;}
        if($type===SiteNodeType::Journal){$ids=$this->nodes()->filter(fn(SiteSection $n):bool=>$n->nodeType()===SiteNodeType::Journal)->modelKeys();if($ids===[]){$query->whereRaw('1 = 0');return;}$query->whereHas('journalEntryMedia',function(Builder $u)use($ids):void{$u->where(function(Builder $o)use($ids):void{$o->whereHas('blogPost',fn(Builder $p)=>$p->whereIn('site_section_id',$ids))->orWhereHas('exhibition',fn(Builder $e)=>$e->whereIn('site_section_id',$ids));});});return;}
        if($type===SiteNodeType::CustomPage){$ids=[];foreach($this->customPageNodes() as $node){$s=$node->getRelationValue('customPageSetting');if($s instanceof CustomPageSetting){$ids=array_merge($ids,$this->referenceQuery->mediaIdsForCustomPage($s));}}$ids=array_values(array_unique($ids));$ids===[]?$query->whereRaw('1 = 0'):$query->whereIn('media_assets.id',$ids);return;}$query->whereRaw('1 = 0');
    }
    /** @param Builder<MediaAsset> $query */private function applyGalleryDestination(Builder $query,SiteSection $node):void{$id=$node->getAttribute('artwork_category_id');if(!is_numeric($id)){$query->whereRaw('1 = 0');return;}$query->whereHas('artworks',fn(Builder $a)=>$a->where('artwork_category_id',(int)$id));}
    /** @param Builder<MediaAsset> $query */private function applyJournalDestination(Builder $query,SiteSection $node):void{$template=$node->journalTemplate();if(!in_array($template,[JournalTemplate::Blog,JournalTemplate::Exhibitions],true)){$query->whereRaw('1 = 0');return;}$query->whereHas('journalEntryMedia',function(Builder $u)use($node,$template):void{$relation=$template===JournalTemplate::Blog?'blogPost':'exhibition';$u->whereHas($relation,fn(Builder $e)=>$e->where('site_section_id',$node->getKey()));});}
    /** @param Builder<MediaAsset> $query */private function applyCustomPageDestination(Builder $query,SiteSection $node):void{$s=$node->getRelationValue('customPageSetting');if(! $s instanceof CustomPageSetting){$query->whereRaw('1 = 0');return;}$ids=$this->referenceQuery->mediaIdsForCustomPage($s);$ids===[]?$query->whereRaw('1 = 0'):$query->whereIn('media_assets.id',$ids);}
    private function nodeLabel(SiteSection $node):string{return trim((string)($node->getAttribute('navigation_label')?:$node->getAttribute('title')));}
}
