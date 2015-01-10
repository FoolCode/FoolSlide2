<?php

namespace Foolz\Foolslide\Model;

use DateTime;

class ReleaseData extends Data
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $rls_id;

    /**
     * @var int
     */
    public $series_id;

    /**
     * @var int
     */
    public $volume;

    /**
     * @var int
     */
    public $volume_part;

    /**
     * @var int
     */
    public $chapter;

    /**
     * @var int
     */
    public $chapter_part;

    /**
     * @var int
     */
    public $extra;

    /**
     * @var int
     */
    public $language;

    /**
     * @var string
     */
    public $title;

    /**
     * @var Datetime
     */
    public $created;

    /**
     * @var Datetime
     */
    public $updated;

}