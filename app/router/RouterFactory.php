<?php

namespace App;

use Nette;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;


class RouterFactory
{
	use Nette\StaticClass;

	/**
	 * @return Nette\Application\IRouter
	 */
	public static function createRouter()
	{
		$router = new RouteList;

		$router[] = new Route('/sitemap.xml', "Homepage:sitemap");

		// defaultna routa - vzdy na konci
		$router[] = new Route('[<locale=cs cs|en>/]<presenter>/<action>[/<id>]', "Jobs:default");
		return $router;
	}
}
