<?php

namespace Foolz\Foolslide\Controller\Admin;

use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Validation\ActiveConstraint\Trim;
use Foolz\Foolframe\Model\Validation\Validator;
use Foolz\Foolslide\Model\PageFactory;
use Foolz\Foolslide\Model\PageUploadException;
use Foolz\Foolslide\Model\RadixCollection;
use Foolz\Foolslide\Model\ReleaseFactory;
use Foolz\Foolslide\Model\SeriesFactory;
use Foolz\Foolslide\Model\SeriesNotFoundException;
use Foolz\Theme\Loader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints as Assert;


class Reader extends \Foolz\Foolframe\Controller\Admin
{
    /**
     * @var SeriesFactory
     */
    protected $series_factory;

    /**
     * @var ReleaseFactory
     */
    protected $release_factory;

    /**
     * @var PageFactory
     */
    protected $page_factory;

    public function before()
    {
        parent::before();

        $this->series_factory = $this->context->getService('foolslide.series_factory');
        $this->release_factory = $this->context->getService('foolslide.release_factory');
        $this->page_factory = $this->context->getService('foolslide.page_factory');

        $this->param_manager->setParam('controller_title', _i('Reader'));
    }

    public function security()
    {
        return $this->getAuth()->hasAccess('maccess.admin');
    }

    /**
     * Selects the theme. Can be overridden so other controllers can use their own admin components
     *
     * @param Loader $theme_instance
     */
    public function setupTheme(Loader $theme_instance)
    {
        // we need to load more themes
        $theme_instance->addDir(ASSETSPATH.'public/themes-admin');
        $this->theme = $theme_instance->get('foolz/foolslide-theme-admin');
    }

    public function action_manage($page = 1)
    {
        if (!ctype_digit((string) $page)) {
            throw new NotFoundHttpException;
        }

        $this->param_manager->setParam('method_title', _i('Manage'));
        $this->builder->createPartial('body', 'reader/manage_series')
            ->getParamManager()->setParam('series_bulk', $this->series_factory->getPaged($page));

        return new Response($this->builder->build());
    }

    public function action_add_series()
    {
        $data['form'] = $this->series_factory->getStructure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $id = $this->series_factory->save($result['success']);

                return $this->redirect('admin/reader/edit_series/'.$id);
            }
        }

        $this->param_manager->setParam('method_title', [_i('Add new series')]);
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    public function action_edit_series($id = 0)
    {
        if (!$id || !ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        try {
            $series_bulk = $this->series_factory->getById($id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        $data['object'] = $series_bulk->series;

        $data['form'] = $this->series_factory->getStructure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $id = $this->series_factory->save($result['success']);

                return $this->redirect('admin/reader/edit_series/'.$id);
            }
        }

        $this->param_manager->setParam('method_title', _i('Edit series'));
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_delete_series($id = 0)
    {
        if (!$id || !ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        try {
            $series_bulk = $this->series_factory->getById($id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $this->series_factory->delete($id);
            $this->notices->setFlash('success', sprintf(_i('The series with ID %s has been deleted.'), $series_bulk->series->id));
            return $this->redirect('admin/reader/manage');
        }

        $data['alert_level'] = 'warning';
        $data['message'] = _i('Do you really want to remove the series with ID %s and all its data?', $series_bulk->series->id);

        $this->param_manager->setParam('method_title', _i('Removing series with ID %s', $series_bulk->series->id));
        $this->builder->createPartial('body', 'confirm')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    public function action_manage_releases($series_id = 0)
    {
        if (!$series_id || !ctype_digit((string) $series_id)) {
            throw new NotFoundHttpException;
        }

        try {
            $series_bulk = $this->series_factory->getById($series_id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        $this->release_factory->fillSeriesBulk($series_bulk);

        $this->param_manager->setParam('method_title', _i('Releases for series %s', $series_bulk->series->title));
        $this->builder->createPartial('body', 'reader/manage_releases')
            ->getParamManager()->setParam('series_bulk', $series_bulk);

        return new Response($this->builder->build());
    }

    function action_add_release($series_id = 0)
    {
        if (!$series_id || !ctype_digit((string) $series_id)) {
            throw new NotFoundHttpException;
        }

        try {
            $series_bulk = $this->series_factory->getById($series_id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        $data['object'] = ['series_id' => $series_id];

        $data['form'] = $this->release_factory->getStructure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost() + ['series_id' => $series_id]);

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $id = $this->release_factory->save($result['success']);

                return $this->redirect('admin/reader/edit_release/'.$id);
            }
        }

        $this->param_manager->setParam('method_title', _i('Add new release to %s', $series_bulk->series->title));
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    public function action_edit_release($id = 0)
    {
        if (!$id || !ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        try {
            $release_bulk = $this->release_factory->getById($id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        $data['object'] = $release_bulk->release;

        $data['form'] = $this->release_factory->getStructure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $id = $this->release_factory->save($result['success']);

                return $this->redirect('admin/reader/edit_release/'.$id);
            }
        }

        $this->param_manager->setParam('method_title', _i('Edit series'));
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_delete_release($id = 0)
    {
        if (!$id || !ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        try {
            $release_bulk = $this->release_factory->getById($id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $this->release_factory->delete($release_bulk->release->id);
            $this->notices->setFlash('success', sprintf(_i('The release with ID %s has been deleted.', $release_bulk->release->id)));
            return $this->redirect('admin/reader/manage_releases/'.$release_bulk->series->id);
        }

        $data['alert_level'] = 'warning';
        $data['message'] = _i('Do you really want to remove the release with ID %s and all its data?', $release_bulk->release->id);

        $this->param_manager->setParam('method_title', _i('Removing release with ID %s', $release_bulk->release->id));
        $this->builder->createPartial('body', 'confirm')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    public function action_manage_pages($id = 0)
    {
        if (!$id || !ctype_digit((string) $id)) {
            throw new NotFoundHttpException;
        }

        try {
            $release_bulk = $this->release_factory->getById($id);
        } catch (SeriesNotFoundException $e) {
            throw new NotFoundHttpException;
        }

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            var_dump($this->getRequest()->files->count());

            try {
                $this->page_factory->addFromFileArray($release_bulk, $this->getRequest()->files->all());
            } catch (PageUploadException $e) {
                $this->notices->set('warning', $e->getMessage());
            }

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                $this->notices->set('success', _i('The pages were uploaded successfully'));
                return $this->redirect('admin/reader/manage_pages/'.$id);
            }
        }

        $this->page_factory->fillReleaseBulk($release_bulk);

        $this->param_manager->setParam('method_title', [_i('Manage pages')]);
        $this->builder->createPartial('body', 'reader/manage_pages')
            ->getParamManager()->setParam('release_bulk', $release_bulk);

        return new Response($this->builder->build());
    }

    function action_preferences()
    {
        /** @var DoctrineConnection $dc */
        $dc = $this->getContext()->getService('doctrine');

        $form = [];

        $form['open'] = [
            'type' => 'open'
        ];

        $form['foolslide.boards.directory'] = [
            'type' => 'input',
            'label' => _i('Boards directory'),
            'preferences' => true,
            'help' => _i('Overrides the default path to the boards directory (Example: /var/www/foolslide/boards)')
        ];

        $form['foolslide.boards.url'] = [
            'type' => 'input',
            'label' => _i('Boards URL'),
            'preferences' => true,
            'help' => _i('Overrides the default url to the boards folder (Example: http://foolslide.site.com/there/boards)')
        ];

        if ($dc->getConnection()->getDriver()->getName() != 'pdo_pgsql') {
            $form['foolslide.boards.db'] = [
                'type' => 'input',
                'label' => _i('Boards database'),
                'preferences' => true,
                'help' => _i('Overrides the default database. You should point it to your Asagi database if you have a separate one.')
            ];

            $form['foolslide.boards.prefix'] = [
                'type' => 'input',
                'label' => _i('Boards prefix'),
                'preferences' => true,
                'help' => _i('Overrides the default prefix (which would be "'.$dc->p('').'board_"). Asagi doesn\'t use a prefix by default.')
            ];

            // it REALLY must never have been set
            if ($this->preferences->get('foolslide.boards.prefix', null, true) === null) {
                $form['foolslide.boards.prefix']['value'] = $dc->p('').'board_';
            }
        }

        $form['foolslide.boards.media_balancers'] = [
            'type' => 'textarea',
            'label' => _i('Media load balancers'),
            'preferences' => true,
            'help' => _i('Facultative. One per line the URLs where your images are reachable.'),
            'class' => 'span6'
        ];

        $form['foolslide.boards.media_balancers_https'] = [
            'type' => 'textarea',
            'label' => _i('HTTPS media load balancers'),
            'preferences' => true,
            'help' => _i('Facultative. One per line the URLs where your images are reachable. This is used when the site is loaded via HTTPS protocol, and if empty it will fall back to HTTP media load balancers.'),
            'class' => 'span6'
        ];

        $form['foolslide.boards.media_download_url'] = [
            'type' => 'input',
            'label' => _i('Boards Media Download URL'),
            'preferences' => true,
        ];

        $form['separator-2'] = [
            'type' => 'separator'
        ];

        $form['submit'] = [
            'type' => 'submit',
            'value' => _i('Submit'),
            'class' => 'btn btn-primary'
        ];

        $form['close'] = [
            'type' => 'close'
        ];

        $this->preferences->submit_auto($this->getRequest(), $form, $this->getPost());

        $data['form'] = $form;

        // create a form
        $this->param_manager->setParam('method_title', _i('Preferences'));
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

}
