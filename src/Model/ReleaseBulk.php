<?php

namespace Foolz\Foolslide\Model;

class ReleaseBulk implements \JsonSerializable
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
     * @param SeriesData $series
     * @return SeriesBulk
     */
    public static function forge(SeriesData $series, ReleaseData $release)
    {
        $new = new static();
        $new->series = $series;
        $new->release = $release;

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
            'release'=> $this->release->export()
        ];
    }

}