<?php

namespace Foolz\Foolslide\Theme\Admin\Partial\Reader;

use Foolz\Foolslide\Model\SeriesBulk;

class ManagePages extends \Foolz\Foolframe\View\View
{
    public function toString()
    {
        /** @var SeriesBulk $series_bulk */
        $release_bulk = $this->getParamManager()->getParam('release_bulk');
        $form = $this->getForm();
        ?>

<div class="admin-container">
    <div class="admin-container-header">
        <?= _i('Pages for series %s, chapter ID %s', $release_bulk->series->title, $release_bulk->release->id) ?>
    </div>

    <div class="pull-right">
        <?= $form->open(['enctype' => 'multipart/form-data', 'onsubmit' => 'fuel_set_csrf_token(this);']) ?>
        <?= $form->hidden('csrf_token', $this->getSecurity()->getCsrfToken()); ?>
        <?= $form->file(['name' => 'pages']) ?>
        <?= $form->submit(['name' => 'Submit']) ?>
        <?= $form->close() ?>
    </div>
</div>
<?php

    }

}