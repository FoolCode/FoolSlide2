<?php

namespace Foolz\Foolslide\Controller;

use Foolz\Foolframe\Controller\Common;
use Foolz\Foolframe\Model\Config;
use Foolz\Foolframe\Model\Preferences;
use Foolz\Foolframe\Model\Uri;
use Foolz\Foolframe\Model\Util;
use Foolz\Foolframe\Model\Validation\ActiveConstraint\Trim;
use Foolz\Foolframe\Model\Validation\Validator;
use Foolz\Foolframe\Model\Cookie;
use Foolz\Foolslide\Model\Ban;
use Foolz\Foolslide\Model\BanFactory;
use Foolz\Foolslide\Model\Board;
use Foolz\Foolslide\Model\Comment;
use Foolz\Foolslide\Model\CommentBulk;
use Foolz\Foolslide\Model\CommentFactory;
use Foolz\Foolslide\Model\CommentInsert;
use Foolz\Foolslide\Model\Media;
use Foolz\Foolslide\Model\MediaFactory;
use Foolz\Foolslide\Model\Radix;
use Foolz\Foolslide\Model\RadixCollection;
use Foolz\Foolslide\Model\Report;
use Foolz\Foolslide\Model\Search;
use Foolz\Inet\Inet;
use Foolz\Profiler\Profiler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Validator\Constraints as Assert;


class Reader extends Common
{
    /**
     * The Theme object
     *
     * @var  \Foolz\Theme\Theme
     */
    protected $theme = null;

    /**
     * A builder object with some defaults appended
     *
     * @var  \Foolz\Theme\Builder
     */
    protected $builder = null;

    /**
     * The ParamManager object of the builder
     *
     * @var  \Foolz\Theme\ParamManager
     */
    protected $param_manager = null;

    /**
     *  The Request object
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request = null;

    /**
     * The Response object
     *
     * @var Response|StreamedResponse
     */
    protected $response = null;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Preferences
     */
    protected $preferences;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var Profiler
     */
    protected $profiler;

    public function before()
    {
        $this->config = $this->getContext()->getService('config');
        $this->preferences = $this->getContext()->getService('preferences');
        $this->uri = $this->getContext()->getService('uri');
        $this->profiler = $this->getContext()->getService('profiler');

        // this has already been forged in the foolslide bootstrap
        $theme_instance = \Foolz\Theme\Loader::forge('foolslide');

        try {
            $theme_name = $this->getQuery('theme', $this->getCookie('theme')) ? : $this->preferences->get('foolslide.theme.default');

            $theme_name_exploded = explode('/', $theme_name);
            if (count($theme_name_exploded) >=2) {
                $theme_name = $theme_name_exploded[0].'/'.$theme_name_exploded[1];
            }

            $theme = $theme_instance->get($theme_name);
            if (!isset($theme->enabled) || !$theme->enabled) {
                throw new \OutOfBoundsException;
            }
            $this->theme = $theme;
        } catch (\OutOfBoundsException $e) {
            $theme_name = 'foolz/foolslide-theme-foolslide';
            $this->theme = $theme_instance->get('foolz/foolslide-theme-foolslide');
        }

        // TODO this is currently bootstrapped in the foolslide bootstrap because we need it running before the router.
        //$this->theme->bootstrap();
        $this->builder = $this->theme->createBuilder();
        $this->param_manager = $this->builder->getParamManager();
        $this->builder->createLayout('chan');

        if (count($theme_name_exploded) == 3) {
            try {
                $this->builder->setStyle($theme_name_exploded[2]);
            } catch (\OutOfBoundsException $e) {
                // just let it go with default on getStyle()
            }
        }

        // KEEP THIS IN SYNC WITH THE ONE IN THE POSTS ADMIN PANEL
        $to_bind = [
            'context' => $this->getContext(),
            'request' => $this->getRequest(),
            'order' => false,
            'modifiers' => [],
            'backend_vars' => [
                'site_url'  => $this->uri->base(),
                'default_url'  => $this->uri->base(),
                'archive_url'  => $this->uri->base(),
                'system_url'  => $this->uri->base(),
                'api_url'   => $this->uri->base(),
                'cookie_domain' => $this->config->get('foolz/foolframe', 'config', 'config.cookie_domain'),
                'cookie_prefix' => $this->config->get('foolz/foolframe', 'config', 'config.cookie_prefix'),
                'selected_theme' => $theme_name,
                'csrf_token_key' => 'csrf_token',
                'images' => [],
                'gettext' => [
                    'submit_state' => _i('Submitting'),
                    'thread_is_real_time' => _i('This thread is being displayed in real time.'),
                    'update_now' => _i('Update now'),
                    'ghost_mode' => _i('This thread has entered ghost mode. Your reply will be marked as a ghost post and will only affect the ghost index.')
                ]
            ]
        ];

        $this->param_manager->setParams($to_bind);

       // $this->builder->createPartial('tools_modal', 'tools_modal');
       // $this->builder->createPartial('tools_search', 'tools_search');
       // $this->builder->createPartial('tools_advanced_search', 'advanced_search');
    }

    public function router($method, $parameters)
    {
        $request = $this->getRequest();
        $this->request = $request;
        $this->response = new Response();
        $this->builder->getProps()->addTitle($this->preferences->get('foolframe.gen.website_title', $this->preferences->get('foolslide.gen.website_title')));

        if (method_exists($this, 'action_'.$method)) {
            return [$this, 'action_'.$method, $parameters];
        }

        return [$this, 'action_404', []];
    }

    public function setLastModified($timestamp = 0, $max_age = 0)
    {
        $time = new \DateTime('@'.$timestamp);
        $etag = md5($this->builder->getTheme()->getDir().$this->builder->getStyle().'|'.$timestamp.'|'.$max_age);

        $this->response->headers->addCacheControlDirective('must-revalidate', true);
        $this->response->setLastModified($time);
        $this->response->setEtag($etag);
        $this->response->setMaxAge($max_age);
    }

    public function action_index()
    {
        $this->builder->createPartial('body', 'index');
        return $this->response->setContent('test');//$this->builder->build());
    }

    public function action_404($error = null)
    {
        return $this->error($error === null ? _i('Page not found. You can use the search if you were looking for something!') : $error, 404);
    }

    protected function error($error = null, $code = 200)
    {
        $this->builder->createPartial('body', 'error')
            ->getParamManager()
            ->setParams(['error' => $error === null ? _i('We encountered an unexpected error.') : $error]);

        $this->response->setStatusCode($code);
        if ($this->response instanceof StreamedResponse) {
            $this->response->setCallback(function() {
                $this->builder->stream();
            });
        } else {
            $this->response->setContent($this->builder->build());
        }

        return $this->response;
    }

    protected function message($level = 'success', $message = null, $code = 200)
    {
        $this->builder->createPartial('body', 'message')
            ->getParamManager()
            ->setParams([
                'level' => $level,
                'message' => $message
            ]);

        return $this->response->setContent($this->builder->build())->setStatusCode($code);
    }

    public function action_theme($vendor = 'foolz', $theme = 'foolslide-theme-default', $style = '')
    {
        $this->builder->getProps()->addTitle(_i('Changing Theme Settings'));

        $theme = $vendor.'/'.$theme.'/'.$style;

        $this->response->headers->setCookie(new Cookie($this->getContext(), 'theme', $theme, 31536000));

        if ($this->getRequest()->headers->get('referer')) {
            $url = $this->getRequest()->headers->get('referer');
        } else {
            $url = $this->uri->base();
        }

        $this->builder->createLayout('redirect')
            ->getParamManager()
            ->setParam('url', $url);
        $this->builder->getProps()->addTitle(_i('Redirecting'));

        return $this->response->setContent($this->builder->build());
    }

    public function action_language($language = 'en_EN')
    {
        $this->response->headers->setCookie(new Cookie($this->getContext(), 'language', $language, 31536000));

        if ($this->getRequest()->headers->get('referer')) {
            $url = $this->getRequest()->headers->get('referer');
        } else {
            $url = $this->uri->base();
        }

        $this->builder->createLayout('redirect')
            ->getParamManager()
            ->setParam('url', $url);
        $this->builder->getProps()->addTitle(_i('Changing Language'));

        return $this->response->setContent($this->builder->build());
    }

    public function action_opensearch()
    {
        $this->builder->createLayout('open_search');

        return $this->response->setContent($this->builder->build());
    }
}
