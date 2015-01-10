<?php

namespace Foolz\Foolslide\Theme\Admin\Partial\Reader;

use Foolz\Foolslide\Model\SeriesBulk;

class ManageReleases extends \Foolz\Foolframe\View\View
{
    public function toString()
    {
        /** @var SeriesBulk $series_bulk */
        $series_bulk = $this->getParamManager()->getParam('series_bulk');

        ?>
<div class="admin-container">
    <div class="admin-container-header">
        <?= _i('Releases for series %s', $series_bulk->series->title) ?>
    </div>

    <div class="pull-right">
        <a class="btn btn-success btn-mini" href="<?= $this->getUri()->create('/admin/reader/add_release/'.$series_bulk->series->id) ?>">
            <i class="icon-plus" style="color: #FFFFFF"></i> <?= _i('Add Release') ?>
        </a>
    </div>

    <table class="table table-hover table-condensed">
        <thead>
            <tr>
                <th class="span1"><?= _i('ID') ?></th>
                <th class="span4"><?= _i('Volume') ?></th>
                <th class="span4"><?= _i('Chapter') ?></th>
                <th class="span4"><?= _i('Extra') ?></th>
                <th class="span4"><?= _i('Language') ?></th>
                <th class="span4"><?= _i('Title') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($series_bulk->release_array as $release) : ?>
            <tr>
                <td><?= $release->id ?></td>
                <td><?= $release->volume.'.'.$release->volume_part ?></td>
                <td><?= $release->chapter.'.'.$release->chapter_part ?></td>
                <td><?= $release->extra ?></td>
                <td><?= $release->language ?></td>
                <td><?= $release->title ?></td>
                <td>
                    <div class="btn-group pull-right">

                        <a class="btn btn-mini btn-primary" href="<?= $this->getUri()->create('admin/reader/manage_pages/'.$release->id) ?>">
                            <?= _i('Manage pages') ?>
                        </a>

                        <a class="btn btn-mini btn-primary" href="<?= $this->getUri()->create('admin/reader/edit_release/'.$release->id) ?>">
                            <?= _i('Edit release') ?>
                        </a>

                        <a class="btn btn-mini btn-danger" href="<?= $this->getUri()->create('admin/reader/delete_release/'.$release->id) ?>">
                            <?= _i('Delete release') ?>
                        </a>
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
