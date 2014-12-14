<?php

namespace Foolz\Foolslide\Theme\Admin\Partial\Reader;

use Foolz\Foolslide\Model\SeriesBulk;

class Manage extends \Foolz\Foolframe\View\View
{
    public function toString()
    { ?>
<div class="admin-container">
    <div class="admin-container-header">
        <?= _i('Series') ?>
    </div>

    <div class="pull-right">
        <a class="btn btn-success btn-mini" href="<?= $this->getUri()->create('/admin/reader/add_series/') ?>">
            <i class="icon-plus" style="color: #FFFFFF"></i> <?= _i('Add Series') ?>
        </a>
    </div>

    <table class="table table-hover table-condensed">
        <thead>
            <tr>
                <th class="span1"><?= _i('ID') ?></th>
                <th class="span4"><?= _i('Title') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->getParamManager()->getParam('series_bulk') as $series_bulk) :
                /** @var SeriesBulk $series_bulk */
                ?>
            <tr>
                <td><?= $series_bulk->series->id ?></td>
                <td>
                    <a href="<?= $this->getUri()->create('admin/reader/series/'.$series_bulk->series->id) ?>"><?= e($series_bulk->series->title) ?></a>

                    <div class="btn-group pull-right">
                        <a class="btn btn-mini btn-primary" href="<?= $this->getUri()->create('admin/reader/series/'.$series_bulk->series->id) ?>">
                            <?= _i('Edit') ?>
                        </a>

                        <button class="btn btn-mini btn-primary dropdown-toggle" data-toggle="dropdown">
                            <span class="caret"></span>
                        </button>

                        <ul class="dropdown-menu">
                            <li>
                                <a href="<?= $this->getUri()->create('admin/reader/delete_series/'.$series_bulk->series->id) ?>"><?= _i('Delete') ?></a>
                            </li>
                        </ul>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
    }
}
