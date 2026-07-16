<?php

namespace modules\stablestwigextensions\twigextensions;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use modules\stablestwigextensions\Module;

use craft\elements\Entry;

class ModuleTwigExtensions extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // First argument is the filter name; second is a callable:
            new TwigFilter('total', 'array_sum'),
            new TwigFilter('getItemData', [$this, 'getItemData']),
            new TwigFilter('json_decode', [$this, 'jsonDecode']),
            new TwigFilter('privacyEmbedUrl', [$this, 'privacyEmbedUrl'])
        ];
    }

    /*
    * @return: the same video URL, rewritten to the provider's
    *   privacy-enhanced domain/params so it doesn't set tracking cookies
    *   until the visitor actually presses play — YouTube's -nocookie
    *   domain and Vimeo's dnt=1 (do-not-track) param both work this way.
    */
    public function privacyEmbedUrl($url)
    {
        if (!$url) {
            return $url;
        }

        $host = parse_url($url, PHP_URL_HOST) ?? '';

        if (preg_match('/(^|\.)youtu\.be$/i', $host)) {
            $id = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            return $id ? "https://www.youtube-nocookie.com/watch?v={$id}" : $url;
        }

        if (preg_match('/(^|\.)youtube\.com$/i', $host)) {
            return preg_replace('/youtube\.com/i', 'youtube-nocookie.com', $url, 1);
        }

        if (preg_match('/(^|\.)vimeo\.com$/i', $host) && !str_contains($url, 'dnt=1')) {
            $separator = str_contains($url, '?') ? '&' : '?';
            return $url . $separator . 'dnt=1';
        }

        return $url;
    }

    /*
    * @return: item data object
    *
    *   by default this will only pull basic data 
    *   - heading/title, intro, image, links and link text
    */
    public function getItemData($item){
        
        if(!$item)
            return;

        //entry defaults --- lazy** -> the 'Ternary Logic' was being weird
        $entryHeading = false;
        $entryIntro = false;
        $entryImage = false;
        $entryLinkUrl = false;
        
        //get related entry
        if (is_a($item, 'craft\elements\Entry')) {
            $entry =  $item;
        }
        else{
            $entry = isset($item->entry) ? $item->entry->one() : false;
        }

        //prep related entry data
        if($entry){
            $entryHeading = $this->getEntryHeading($entry);
            $entryPreHeading = $this->getEntryPreHeading($entry);
            $entrySubHeading = $this->getEntrySubHeading($entry);
            $entryIntro = $this->getEntryIntro($entry);
            $entryImage = $this->getEntryImage($entry);
            $entryLinkUrl = $entry->url;
        }

        //field data ( -- this is also used to override the entry data if not blank)
        $itemHeading = $this->getEntryHeading($item);
        $itemPreHeading = $this->getEntryPreHeading($item);
        $itemSubHeading = $this->getEntrySubHeading($item);
        $itemIntro = $this->getEntryIntro($item);
        $itemImage = $this->getEntryImage($item);
        $itemMobileImage = $this->getEntryMobileImage($item);
        $itemLinkUrl = $this->getEntryUrl($item);

        //return values
        $itemObj = (object) [
            "heading" => $itemHeading ? $itemHeading : $entryHeading,
            "preheading" => $itemPreHeading ? $itemPreHeading : $entryPreHeading,
            "subheading" => $itemSubHeading ? $itemSubHeading : $entrySubHeading,
            "intro" => $itemIntro ? $itemIntro : $entryIntro,
            "image" => $itemImage ? $itemImage : $entryImage,
            "mobileImage" => $itemMobileImage ? $itemMobileImage : false,
            "linkUrl" => $itemLinkUrl ? $itemLinkUrl : $entryLinkUrl,
            "linkText" => $item->linkText ?? 'Learn More',
            "tag" => $item->tag ?? false
        ];
        
        return $itemObj;
    }

    public function getEntryHeading($entry){
        $heading = false;

        if( isset($entry->heading) && $entry->heading){
            $heading= $entry->heading;
        }
        else {
            $heading = $entry->title;
        }

        return $heading;
    }

    public function getEntryPreHeading($entry){
        $heading = false;

        if( isset($entry->preheading) && $entry->preheading){
            $heading= $entry->preheading;
        }
        else if( isset($entry->preheading) && $entry->preheading){
            $heading = $entry->preheading;
        }

        return $heading;
    }


    public function getEntrySubHeading($entry){
        $heading = false;

        if( isset($entry->subheading) && $entry->subheading){
            $heading= $entry->subheading;
        }
        else if( isset($entry->subheading) && $entry->subheading){
            $heading = $entry->subheading;
        }

        return $heading;
    }

    public function getEntryIntro($entry){
        $intro = false;

        if( isset($entry->excerpt) && $entry->excerpt){
            $intro = $entry->excerpt;
        }
        else if( isset($entry->textPlain) && $entry->textPlain){
            $intro = $entry->textPlain;
        }
        else if( isset($entry->intro) && $entry->intro){
            $intro= $entry->intro;
        }
        else if( isset($entry->introSimple) && $entry->introSimple){
            $intro = $entry->introSimple;
        }

        return $intro;
    }

    public function getEntryUrl($entry){
        $url = false;

        // linkItem
        if( isset($entry->linkItem) && $entry->linkItem && !$entry->linkItem->isEmpty()){
            $url = $entry->linkItem;
        }
        // linkUrl
        else if( isset($entry->linkUrl) && $entry->linkUrl ){
            $url = $entry->linkUrl;
        }            

        return $url;
    }

    public function getEntryImage($entry){
        $image = false;
        
        if( isset($entry->image) ){
            $image = $entry->image->one() ?? false;
        }
        elseif( isset($entry->heroImage) ){
            $image = $entry->heroImage->one() ?? false;
        }

        return $image;
    }

    public function getEntryMobileImage($entry){
        $image = false;
        
        if( isset($entry->mobileImage) ){
            $image = $entry->mobileImage->one() ?? false;
        }

        return $image;
    }

    /*
    * @return: decoded json
    */
    public function jsonDecode($json)
    {
        return json_decode($json, true);
    }
}