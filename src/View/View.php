<?php

namespace Foolz\Foolslide\View;

use Foolz\Foolslide\Model\Radix;
use Foolz\Foolslide\Model\RadixCollection;
use Foolz\Foolslide\Model\ReportCollection;

class View extends \Foolz\Foolframe\View\View
{
    /**
     * @return Radix
     */
    public function getRadix()
    {
        return $this->getBuilderParamManager()->getParam('radix');
    }

    /**
     * @return RadixCollection
     */
    public function getRadixColl()
    {
        return $this->getBuilderParamManager()->getParam('context')->getService('foolslide.radix_collection');
    }

    /**
     * @return ReportCollection
     */
    public function getReportColl()
    {
        return $this->getBuilderParamManager()->getParam('context')->getService('foolslide.report_collection');
    }
}