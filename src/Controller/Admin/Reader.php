<?php

namespace Foolz\Foolslide\Controller\Admin;

use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Validation\ActiveConstraint\Trim;
use Foolz\Foolframe\Model\Validation\Validator;
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

    public function before()
    {
        parent::before();

        $this->series_factory = $this->context->getService('foolslide.series_factory');
        $this->release_factory = $this->context->getService('foolslide.release_factory');

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

    function action_search()
    {
        $this->_views['method_title'] = _i('Search');

        $form = [];

        $form['open'] = [
            'type' => 'open'
        ];

        $form['foolslide.sphinx.global'] = [
            'type' => 'checkbox',
            'label' => 'Global SphinxSearch',
            'placeholder' => 'Foolslide',
            'preferences' => true,
            'help' => _i('Activate Sphinx globally (enables crossboard search)')
        ];

        $form['foolslide.sphinx.listen'] = [
            'type' => 'input',
            'label' => 'Listen (Sphinx)',
            'preferences' => true,
            'help' => _i('Set the address and port to your Sphinx instance.'),
            'class' => 'span2',
            'validation' => [new Trim(), new Assert\Length(['max' => 48])],
            'validation_func' => function($input, $form) {
                if (strpos($input['foolslide.sphinx.listen'], ':') === false) {
                    return [
                        'error_code' => 'MISSING_COLON',
                        'error' => _i('The Sphinx listening address and port aren\'t formatted correctly.')
                    ];
                }

                $sphinx_ip_port = explode(':', $input['foolslide.sphinx.listen']);

                if (count($sphinx_ip_port) != 2) {
                    return [
                        'error_code' => 'WRONG_COLON_NUMBER',
                        'error' => _i('The Sphinx listening address and port aren\'t formatted correctly.')
                    ];
                }

                if (intval($sphinx_ip_port[1]) <= 0) {
                    return [
                        'error_code' => 'PORT_NOT_A_NUMBER',
                        'error' => _i('The port specified isn\'t a valid number.')
                    ];
                }
                /*
                \Foolz\Sphinxql\Sphinxql::addConnection('default', $sphinx_ip_port[0], $sphinx_ip_port[1]);

                try {
                    \Foolz\Sphinxql\Sphinxql::connect(true);
                } catch (\Foolz\Sphinxql\SphinxqlConnectionException $e) {
                    return [
                        'warning_code' => 'CONNECTION_NOT_ESTABLISHED',
                        'warning' => _i('The Sphinx server couldn\'t be contacted at the specified address and port.')
                    ];
                }
                */
                return ['success' => true];
            }
        ];

        $form['foolslide.sphinx.listen_mysql'] = [
            'type' => 'input',
            'label' => 'Listen (MySQL)',
            'preferences' => true,
            'validation' => [new Trim(), new Assert\Length(['max' => 48])],
            'help' => _i('Set the address and port to your MySQL instance.'),
            'class' => 'span2'
        ];

        $form['foolslide.sphinx.connection_flags'] = [
            'type' => 'input',
            'label' => 'Connection Flags (MySQL)',
            'placeholder' => 0,
            'preferences' => true,
            'validation' => [new Trim()],
            'help' => _i('Set the MySQL client connection flags to enable compression, SSL, or secure connection.'),
            'class' => 'span2'
        ];

        $form['foolslide.sphinx.dir'] = [
            'type' => 'input',
            'label' => 'Working Directory',
            'preferences' => true,
            'help' => _i('Set the working directory to your Sphinx working directory.'),
            'class' => 'span3',
            'validation' => [new Trim()],
            'validation_func' => function($input, $form) {
                if (!file_exists($input['foolslide.sphinx.dir'])) {
                    return [
                        'warning_code' => 'SPHINX_WORKING_DIR_NOT_FOUND',
                        'warning' => _i('Couldn\'t find the Sphinx working directory.')
                    ];
                }

                return ['success' => true];
            }
        ];

        $form['foolslide.sphinx.min_word_len'] = [
            'type' => 'input',
            'label' => 'Minimum Word Length',
            'preferences' => true,
            'help' => _i('Set the minimum word length indexed by Sphinx.'),
            'class' => 'span1',
            'validation' => [new Trim()]
        ];

        $form['foolslide.sphinx.mem_limit'] = [
            'type' => 'input',
            'label' => 'Memory Limit',
            'preferences' => true,
            'help' => _i('Set the memory limit for the Sphinx indexer.'),
            'class' => 'span1'
        ];

        $form['foolslide.sphinx.max_children'] = [
            'type' => 'input',
            'label' => 'Max Children',
            'placeholder' => 0,
            'validation' => [new Trim()],
            'preferences' => true,
            'help' => _i('Set the maximum number of children to fork for searchd.'),
            'class' => 'span1'
        ];

        $form['foolslide.sphinx.max_matches'] = [
            'type' => 'input',
            'label' => 'Max Matches',
            'placeholder' => 5000,
            'validation' => [new Trim()],
            'preferences' => true,
            'help' => _i('Set the maximum amount of matches the search daemon keeps in RAM for each index and results returned to the client.'),
            'class' => 'span1'
        ];

        $form['foolslide.sphinx.distributed'] = [
            'type' => 'input',
            'label' => 'Number of Distributed Indexes',
            'placeholder' => 0,
            'validation' => [new Trim()],
            'preferences' => true,
            'help' => _i('Set the total number of distributed indexes to be created with indexer and used for searchd.'),
            'class' => 'span1'
        ];

        $form['foolslide.sphinx.custom_message'] = [
            'type' => 'textarea',
            'label' => 'Custom Error Message',
            'preferences' => true,
            'help' => _i('Set a custom error message.'),
            'class' => 'span6'
        ];

        $form['separator'] = [
            'type' => 'separator'
        ];

        $form['submit'] = [
            'type' => 'submit',
            'value' => _i('Save'),
            'class' => 'btn btn-primary'
        ];

        $form['close'] = [
            'type' => 'close'
        ];

        $this->preferences->submit_auto($this->getRequest(), $form, $this->getPost());

        // create the form
        $data['form'] = $form;

        $this->param_manager->setParam('method_title', _i('Preferences'));
        $partial = $this->builder->createPartial('body', 'form_creator');
        $partial->getParamManager()->setParams($data);
        $built = $partial->build();
        $partial->setBuilt($built.'<a href="'.$this->uri->create('admin/boards/sphinx_config').'" class="btn">'._i('Generate Config').'</a>');

        return new Response($this->builder->build());
    }

    public function action_sphinx_config()
    {
        $data = [];

        $mysql = $this->preferences->get('foolslide.sphinx.listen_mysql', null);
        $data['mysql'] = [
            'host' => $mysql === null ? '127.0.0.1' : explode(':', $mysql)[0],
            'port' => $mysql === null ? '3306' : explode(':', $mysql)[1],
            'flag' => $this->preferences->get('foolslide.sphinx.connection_flags', '0')
        ];

        $sphinx = $this->preferences->get('foolslide.sphinx.listen', null);
        $data['sphinx'] = [
            'port' => $sphinx === null ? '9306' : explode(':', $sphinx)[1],
            'working_directory' => $this->preferences->get('foolslide.sphinx.dir', '/usr/local/sphinx'),
            'mem_limit' => $this->preferences->get('foolslide.sphinx.mem_limit', '1024M'),
            'min_word_len' => $this->preferences->get('foolslide.sphinx.min_word_len', 1),
            'max_children' => $this->preferences->get('foolslide.sphinx.max_children', 0),
            'max_matches' => $this->preferences->get('foolslide.sphinx.max_matches', 5000),
            'distributed' => (int) $this->preferences->get('foolslide.sphinx.distributed', 0)
        ];

        $data['boards'] = $this->radix_coll->getAll();
        $data['example'] = current($data['boards']);

        $this->param_manager->setParam('method_title', [_i('Search'), 'Sphinx', _i('Configuration File'), _i('Generate')]);
        $this->builder->createPartial('body', ($data['sphinx']['distributed'] > 1) ? 'boards/sphinx_dist_config' : 'boards/sphinx_config')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }
}
