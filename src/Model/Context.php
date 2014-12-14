<?php

namespace Foolz\Foolslide\Model;

use Foolz\Foolframe\Model\Auth;
use Foolz\Foolframe\Model\ContextInterface;
use Foolz\Foolframe\Model\Legacy\Config;
use Foolz\Plugin\Event;
use Foolz\Plugin\Hook;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

class Context implements ContextInterface
{
    /**
     * @var \Foolz\Foolframe\Model\Context
     */
    public $context;

    public function __construct(\Foolz\Foolframe\Model\Context $context)
    {
        $this->context = $context;

        /** @var \Foolz\Foolframe\Model\Config $config */
        $config = $this->context->getService('config');
        $config->addPackage('foolz/foolslide', ASSETSPATH);

        class_alias('Foolz\\Foolslide\\Model\\Ban', 'Ban');
        class_alias('Foolz\\Foolslide\\Model\\Board', 'Board');
        class_alias('Foolz\\Foolslide\\Model\\Comment', 'Comment');
        class_alias('Foolz\\Foolslide\\Model\\CommentInsert', 'CommentInsert');
        class_alias('Foolz\\Foolslide\\Model\\Media', 'Media');
        class_alias('Foolz\\Foolslide\\Model\\Radix', 'Radix');
        class_alias('Foolz\\Foolslide\\Model\\Report', 'Report');
        class_alias('Foolz\\Foolslide\\Model\\Search', 'Search');

        require_once __DIR__.'/../../assets/packages/stringparser-bbcode/library/stringparser_bbcode.class.php';

        $context->getContainer()
            ->register('foolslide.radix_collection', 'Foolz\Foolslide\Model\RadixCollection')
            ->addArgument($context);

        $context->getContainer()
            ->register('foolslide.comment_factory', 'Foolz\Foolslide\Model\CommentFactory')
            ->addArgument($context);

        $context->getContainer()
            ->register('foolslide.media_factory', 'Foolz\Foolslide\Model\MediaFactory')
            ->addArgument($context);

        $context->getContainer()
            ->register('foolslide.ban_factory', 'Foolz\Foolslide\Model\BanFactory')
            ->addArgument($context);

        $context->getContainer()
            ->register('foolslide.report_collection', 'Foolz\Foolslide\Model\ReportCollection')
            ->addArgument($context);
    }

    public function handleWeb(Request $request)
    {
        $preferences = $this->context->getService('preferences');
        $config = $this->context->getService('config');
        $uri = $this->context->getService('uri');

        $theme_instance = \Foolz\Theme\Loader::forge('foolslide');
        $theme_instance->addDir($config->get('foolz/foolslide', 'package', 'directories.themes'));
        $theme_instance->addDir(VAPPPATH.'foolz/foolslide/themes/');
        $theme_instance->setBaseUrl($uri->base().'foolslide/');
        $theme_instance->setPublicDir(DOCROOT.'foolslide/');

        // set an ->enabled on the themes we want to use
        /** @var Auth $auth */
        $auth = $this->context->getService('auth');
        if ($auth->hasAccess('maccess.admin')) {
            Event::forge('Foolz\Foolframe\Model\System::environment.result')
                ->setCall(function($result) use ($config) {
                    $environment = $result->getParam('environment');

                    foreach ($config->get('foolz/foolslide', 'environment') as $section => $data) {
                        foreach ($data as $k => $i) {
                            array_push($environment[$section]['data'], $i);
                        }
                    }

                    $result->setParam('environment', $environment)->set($environment);
                })->setPriority(0);

            foreach ($theme_instance->getAll() as $theme) {
                $theme->enabled = true;
            }
        } else {
            if ($themes_enabled = $preferences->get('foolslide.theme.active_themes')) {
                $themes_enabled = unserialize($themes_enabled);
            } else {
                $themes_enabled = ['foolz/foolslide-theme-foolslide' => 1];
            }

            foreach ($themes_enabled as $key => $item) {
                if (!$item && !$auth->hasAccess('maccess.admin')) {
                    continue;
                }

                try {
                    $theme = $theme_instance->get($key);
                    $theme->enabled = true;
                } catch (\OutOfBoundsException $e) {}
            }
        }

        try {
            $theme_name = $request->query->get('theme', $request->cookies->get($this->context->getService('config')->get('foolz/foolframe', 'config', 'config.cookie_prefix').'theme')) ? : $preferences->get('foolslide.theme.default');
            $theme_name_exploded = explode('/', $theme_name);

            // must get rid of the style
            if (count($theme_name_exploded) >= 2) {
                $theme_name = $theme_name_exploded[0].'/'.$theme_name_exploded[1];
            }

            $theme = $theme_instance->get($theme_name);

            if (!isset($theme->enabled) || !$theme->enabled) {
                throw new \OutOfBoundsException;
            }
        } catch (\OutOfBoundsException $e) {
            $theme = $theme_instance->get('foolz/foolslide-theme-foolslide');
        }

        $theme->bootstrap();
    }

    public function loadRoutes(RouteCollection $route_collection)
    {
        Hook::forge('Foolz\Foolslide\Model\Context.loadRoutes.before')
            ->setObject($this)
            ->setParam('route_collection', $route_collection)
            ->execute();

        $route_collection->add('foolslide.root', new Route(
            '/',
            ['_controller' => '\Foolz\Foolslide\Controller\Chan::index']
        ));

        $route_collection->add('404', new Route(
            '',
            ['_controller' => '\Foolz\Foolslide\Controller\Chan::404']
        ));

        $route = \Foolz\Plugin\Hook::forge('Foolz\Foolslide\Model\Content::routes.collection')
            ->setParams([
                'default_suffix' => 'page',
                'suffix' => 'page',
                'controller' => '\Foolz\Foolslide\Controller\Chan::*'
            ])
            ->execute();


        /** @var Radix[] $radix_all */
        $radix_all = $this->context->getService('foolslide.radix_collection')->getAll();

        foreach ($radix_all as $radix) {
            $route_collection->add(
                'foolslide.chan.radix.'.$radix->shortname, new Route(
                '/'.$radix->shortname.'/{_suffix}',
                [
                    '_default_suffix' => $route->getParam('default_suffix'),
                    '_suffix' => $route->getParam('suffix'),
                    '_controller' => $route->getParam('controller'),
                    'radix_shortname' => $radix->shortname
                ],
                [
                    '_suffix' => '.*'
                ]
            ));
        }

        $route_collection->add(
            'foolslide.chan.api', new Route(
            '/_/api/chan/{_suffix}',
            [
                '_suffix' => '',
                '_controller' => '\Foolz\Foolslide\Controller\Api\Chan::*',
            ],
            [
                '_suffix' => '.*'
            ]
        ));

        $route_collection->add(
            'foolslide.chan._', new Route(
            '/_/{_suffix}',
            [
                '_suffix' => '',
                '_controller' => '\Foolz\Foolslide\Controller\Chan::*',
            ],
            [
                '_suffix' => '.*'
            ]
        ));

        foreach(['boards', 'moderation'] as $location) {
            $route_collection->add(
                'foolslide.admin.'.$location, new Route(
                    '/admin/'.$location.'/{_suffix}',
                    [
                        '_suffix' => '',
                        '_controller' => '\Foolz\Foolslide\Controller\Admin\\'.ucfirst($location).'::*',
                    ],
                    [
                        '_suffix' => '.*',
                    ]
                )
            );
        }

        Hook::forge('Foolz\Foolslide\Model\Context.loadRoutes.after')
            ->setObject($this)
            ->setParam('route_collection', $route_collection)
            ->execute();
    }

    public function handleConsole()
    {
        // no actions
    }
}
