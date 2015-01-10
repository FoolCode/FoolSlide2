<?php

namespace Foolz\Foolslide\Model;

class SeriesBulk implements \JsonSerializable
{
    /**
     * @var SeriesData
     */
    public $series = null;

    /**
     * @var ReleaseData[]
     */
    public $release_array = null;

    /**
     * @param SeriesData $series
     * @param ReleaseData[]|null $release_array
     * @return SeriesBulk
     */
    public static function forge(SeriesData $series, $release_array = null)
    {
        $new = new static();
        $new->series = $series;

        if ($release_array !== null) {
            $new->release_array = $release_array;
        }

        return $new;
    }

    /**
     * Implements \JsonSerializable interface
     *
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        $array = ['series' => $this->series->export()];

        if ($this->release_array !== null) {
            $array['releases'] = [];

            foreach ($this->release_array as $release) {
                $array['releases'][] = $release->export();
            }
        }

        return $array;
    }

}