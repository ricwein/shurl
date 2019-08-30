<?php
/**
 * @author Richard Weinhold
 */
namespace ricwein\shurl\Core;

use Klein\App;
use Klein\Klein;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use ricwein\shurl\Config\Config;

/**
 * shurl application,
 * using the shurl core to provide
 */
class Router
{

    /**
     * @var Config
     */
    protected $config;

    /**
     * init new shurl Core
     * @param Config|null $config
     */
    public function __construct(Config $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $this->config = Config::getInstance();
        }
    }

    /**
     * parse reqeusted shorten
     * ed URL and redirect to original, if available
     * @throws \Throwable
     * @return void
     */
    public function dispatch()
    {
        $klein = new Klein();

        // prepare routing
        $klein->respond(function (Request $request, Response $response, ServiceProvider $service, App $app, Klein $klein) {

            // lazy load shurl core and templater
            $app->register('core', function () {
                return new Core($this->config);
            });

            $app->register('templater', function () use ($app, $request, $response) {
                return new Templater($app->core, $request, $response);
            });

            // error handling for each request
            $klein->onError(function ($klein, $message, $type, $throwable) use ($request, $response, $app) {
                $app->core->logException($throwable);
                $app->templater->error($throwable);
            });
        });

        // show welcome page as default
        $klein->respond('/', function (Request $request, Response $response, ServiceProvider $service, App $app) {
            $app->templater->welcome($app->core->getUrlCount());
        });

        // match and preview slugs
        $klein->respond('GET', '/preview/[:slug](/)?', function (Request $request, Response $response, ServiceProvider $service, App $app) {
            $url = $app->core->getUrl($request->slug);
            $app->core->track($url, $request);

            $app->templater->view('preview', [
                'redirect' => $url,
            ]);
        });

        // match and preview slugs
        $klein->respond('GET', '/api/[:slug](/)?', function (Request $request, Response $response, ServiceProvider $service, App $app) {
            $url = $app->core->getUrl($request->slug);
            $app->core->track($url, $request);

            $response->json([[
                'slug'     => $url->slug,
                'original' => $url->original,
            ]]);
            exit(0);
        });

        // match and redirect slugs
        $klein->respond('GET', '/[:slug](/)?', function (Request $request, Response $response, ServiceProvider $service, App $app) {
            $url = $app->core->getUrl($request->slug);
            $app->core->track($url, $request);

            if ($url->mode() === 'html') {
                $app->templater->view('redirect', ['redirect' => $url]);
            } else {
                $app->core->redirect($url, $response);
            }
        });

        // match scss assets, and parse them
        $klein->respond('GET', '/assets/css/[:stylesheet].css', function (Request $request, Response $response, ServiceProvider $service, App $app) {
            $app->templater->asset($request->stylesheet);
        });

        $klein->dispatch();
    }
}
