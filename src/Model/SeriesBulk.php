<?php

namespace Foolz\Foolslide\Model;

class SeriesBulk implements \JsonSerializable
{
    /**
     * @var SeriesData
     */
    public $series = null;

    /**
     * @param SeriesData $series
     * @return SeriesBulk
     */
    public static function forge(SeriesData $series)
    {
        $new = new static();
        $new->series = $series;

        return $new;
    }

    /**
     * Implements \JsonSerializable interface
     *
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return $this->series->export();
    }

}