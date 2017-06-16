<?php

namespace ricwein\shurl\Core;

use Klein\Request;
use Klein\Response;
use ricwein\shurl\Exception\DatabaseUnreachable;
use ricwein\shurl\Exception\NotFound;
use ricwein\shurl\Template\Engine\File;
use ricwein\shurl\Template\Processor;
use ricwein\shurl\Template\Template;

/**
 * shurl frontend renderer
 */
class Templater {

	/**
	 * @var Template
	 */
	protected $template;

	/**
	 * @var Core
	 */
	protected $core;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var Response
	 */
	protected $response;

	/**
	 * @param Core $core
	 * @param Request $request
	 * @param Response $response
	 */
	public function __construct(Core $core, Request $request, Response $response) {
		$this->core     = $core;
		$this->request  = $request;
		$this->response = $response;
	}

	/**
	 * @param string $templateFile
	 * @param array|object $bindings
	 * @param callable|null $filter
	 * @return void
	 * @throws \UnexpectedValueException
	 */
	public function view(string $templateFile, $bindings = [], callable $filter = null) {
		$template = new Template($this->core->config, $this->core->cache);

		$content = $template->make($templateFile, array_replace_recursive($this->fetchVariables(), [
			'assets'   => $this->core->config->assets['variables'],
			'template' => [
				'name' => ucfirst(strtolower(str_replace(['_', '.'], ' ', pathinfo(str_replace($this->core->config->views['extension'], '', $templateFile), PATHINFO_FILENAME)))),
			],
		], (array) $bindings));

		$this->response->body($content);
	}

	/**
	 * fetch and build some default Variables
	 * for template-binding
	 * @return array
	 */
	protected function fetchVariables(): array{
		$protocol = ($this->request->isSecure() ? 'https://' : 'http://');
		$host     = $this->request->server()->get('SERVER_NAME');

		return array_replace_recursive([
			'wait'   => (int) $this->core->config->redirect['wait'],
			'config' => $this->core->config->get(),
			'url'    => [
				'protocol' => $protocol,
				'host'     => $host,
				'base'     => $protocol . $host,
				'root'     => $this->core->config->rootURL,
			],
		], [
			'config' => [
				'name' => ucfirst(strtolower($this->core->config->name)),
			],
		]);
	}

	/**
	 * @param \Throwable $throwable
	 * @return void
	 */
	public function error(\Throwable $throwable) {

		// disguise database-exception to prevent credential-leaking messages
		if ($throwable instanceof \PDOException) {
			$throwable = new DatabaseUnreachable('The Database Server is currently not reachable, please try again later', 503, $throwable);
		}

		// set http response code from exception
		$this->response->status()->setCode($throwable->getCode() > 0 ? (int) $throwable->getCode() : 500);

		$this->view('error', ['exception' => [
			'type'    => (new \ReflectionClass($throwable))->getShortName(),
			'code'    => $throwable->getCode(),
			'message' => $throwable->getMessage(),
		]]);
	}

	/**
	 * @param string $assetName
	 * @return void
	 */
	public function asset(string $assetName) {
		if (false === $assetPath = realpath(__DIR__ . '/../../' . trim($this->core->config->assets['path'], '/'))) {
			throw new NotFound('assets path not found', 404);
		}
		$asset  = new File($assetPath, $this->core->config);
		$parser = new Processor\Assets($asset, $this->core->config);
		$styles = $parser->parse($assetName . '.scss', $this->fetchVariables());

		$this->response->body($styles);
		$this->response->header('Content-Type', 'text/css; charset=utf-8');
		$this->response->header('Cache-Control', 'max-age=' . $this->core->config->assets['expires']);
		$this->response->send();
	}

	/**
	 * @param int $count
	 * @return void
	 */
	public function welcome(int $count) {
		$this->view('welcome', [
			'count' => $count,
		]);
	}

}
