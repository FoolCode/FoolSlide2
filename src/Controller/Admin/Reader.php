<?php

namespace Foolz\Foolslide\Controller\Admin;

use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Foolframe\Model\Validation\ActiveConstraint\Trim;
use Foolz\Foolframe\Model\Validation\Validator;
use Foolz\Foolslide\Model\RadixCollection;
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

    public function before()
    {
        parent::before();

        $this->series_factory = $this->context->getService('foolslide.series_factory');

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
        $this->builder->createPartial('body', 'reader/manage')
            ->getParamManager()->setParam('series_bulk', $this->series_factory->getPaged($page));

        return new Response($this->builder->build());
    }

    public function action_add_series()
    {
        $this->param_manager->setParam('method_title', _i('Add series'));
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

        $this->param_manager->setParam('method_title', _i('Edit series'));
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
            $this->notices->setFlash('success', sprintf(_i('The series %s has been deleted.'), $series_bulk->series->title));
            return $this->redirect('admin/reader/manage');
        }

        $data['alert_level'] = 'warning';
        $data['message'] = _i('Do you really want to remove the series and all its data?');

        $this->param_manager->setParam('method_title', _i('Removing series:').' '.$series_bulk->series->title);
        $this->builder->createPartial('body', 'confirm')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }




    public function action_board($shortname = null)
    {
        $data['form'] = $this->radix_coll->structure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token was not found. Please try again.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());

            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $this->radix_coll->save($result['success']);

                if (is_null($shortname)) {
                    $this->notices->setFlash('success', _i('New board created!'));
                    return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
                } elseif ($shortname != $result['success']['shortname']) {
                    // case in which letter was changed
                    $this->notices->setFlash('success', _i('Board information updated.'));
                    return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
                } else {
                    $this->notices->set('success', _i('Board information updated.'));
                }
            }
        }

        $board = $this->radix_coll->getByShortname($shortname);
        if ($board === false) {
            throw new NotFoundHttpException;
        }

        $data['object'] = (object) $board->getAllValues();

        $this->param_manager->setParam('method_title', [_i('Manage'), _i('Edit'), $shortname]);
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_add()
    {
        $data['form'] = $this->radix_coll->structure();

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $result = Validator::formValidate($data['form'], $this->getPost());
            if (isset($result['error'])) {
                $this->notices->set('warning', $result['error']);
            } else {
                // it's actually fully checked, we just have to throw it in DB
                $this->radix_coll->save($result['success']);
                $this->notices->setFlash('success', _i('New board created!'));
                return $this->redirect('admin/boards/board/'.$result['success']['shortname']);
            }
        }

        // the actual POST is in the board() function
        $data['form']['open']['action'] = $this->uri->create('admin/boards/add_new');

        // panel for creating a new board
        $this->param_manager->setParam('method_title', [_i('Manage'), _i('Add')]);
        $this->builder->createPartial('body', 'form_creator')
            ->getParamManager()->setParams($data);

        return new Response($this->builder->build());
    }

    function action_delete($id = 0)
    {
        $board = $this->radix_coll->getById($id);
        if ($board == false) {
            throw new NotFoundHttpException;
        }

        if ($this->getPost() && !$this->checkCsrfToken()) {
            $this->notices->set('warning', _i('The security token wasn\'t found. Try resubmitting.'));
        } elseif ($this->getPost()) {
            $board->remove($id);
            $this->notices->setFlash('success', sprintf(_i('The board %s has been deleted.'), $board->shortname));
            return $this->redirect('admin/boards/manage');
        }

        $data['alert_level'] = 'warning';
        $data['message'] = _i('Do you really want to remove the board and all its data?').
            '<br/>'.
            _i('Notice: due to its size, you will have to remove the image directory manually. The directory will have the "_removed" suffix. You can remove all the leftover "_removed" directories with the following command:').
            ' <code>php index.php cli boards remove_leftover_dirs</code>';

        $this->param_manager->setParam('method_title', _i('Removing board:').' '.$board->shortname);
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
