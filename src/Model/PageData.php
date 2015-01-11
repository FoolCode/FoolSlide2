<?php

namespace Foolz\Foolslide\Model;

use DateTime;

class PageData extends Data
{

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $release_id;

    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @var int
     */
    public $filesize;

    /**
     * @var string
     */
    public $filename;

    /**
     * @var string
     */
    public $extension;

    /**
     * @var string
     */
    public $hash;

    /**
     * @var Datetime
     */
    public $created;

    /**
     * @var Datetime
     */
    public $updated;

}