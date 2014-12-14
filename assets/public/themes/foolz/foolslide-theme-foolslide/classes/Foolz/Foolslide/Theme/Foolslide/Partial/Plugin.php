<?php

namespace Foolz\Foolslide\Theme\Foolslide\Partial;

class Plugin extends \Foolz\Foolslide\View\View
{
    public function toString()
    {
        echo $this->getParamManager()->getParam('content');
    }
}
