<?php

namespace Foolz\Foolslide\Model;

class PageBulk implements \JsonSerializable
{
    /**
     * @var SeriesData
     */
    public $series = null;

    /**
     * @var ReleaseData
     */
    public $release = null;

    /**
     * @var PageData
     */
    public $page = null;

    /**
     * @param SeriesData $series
     * @param ReleaseData $release
     * @param PageData $page
     * @return SeriesBulk
     */
    public static function forge(SeriesData $series, ReleaseData $release, PageData $page)
    {
        $new = new static();
        $new->series = $series;
        $new->release = $release;
        $new->page = $page;

        return $new;
    }

    /**
     * Implements \JsonSerializable interface
     *
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return [
            'series' => $this->series->export(),
            'release'=> $this->release->export(),
            'page'=> $this->page->export()
        ];
    }

}