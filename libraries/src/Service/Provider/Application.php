<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Service
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Service\Provider;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Console\SessionGcCommand;
use Joomla\Console\Application as BaseConsoleApplication;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Session\Session;
use Joomla\Console\Loader\ContainerLoader;
use Joomla\Console\Loader\LoaderInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Session\Storage\RuntimeStorage;
use Psr\Log\LoggerInterface;

/**
 * Application service provider
 *
 * @since  4.0
 */
class Application implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   4.0
	 */
	public function register(Container $container)
	{
		$container->alias(AdministratorApplication::class, 'JApplicationAdministrator')
			->share(
				'JApplicationAdministrator',
				function (Container $container)
				{
					$app = new AdministratorApplication(null, $container->get('config'), null, $container);

					// The session service provider needs Factory::$application, set it if still null
					if (Factory::$application === null)
					{
						Factory::$application = $app;
					}

					$app->setDispatcher($container->get('Joomla\Event\DispatcherInterface'));
					$app->setLogger($container->get(LoggerInterface::class));
					$app->setSession($container->get('Joomla\Session\SessionInterface'));

					return $app;
				},
				true
			);

		$container->alias(SiteApplication::class, 'JApplicationSite')
			->share(
				'JApplicationSite',
				function (Container $container)
				{
					$app = new SiteApplication(null, $container->get('config'), null, $container);

					// The session service provider needs Factory::$application, set it if still null
					if (Factory::$application === null)
					{
						Factory::$application = $app;
					}

					$app->setDispatcher($container->get('Joomla\Event\DispatcherInterface'));
					$app->setLogger($container->get(LoggerInterface::class));
					$app->setSession($container->get('Joomla\Session\SessionInterface'));

					return $app;
				},
				true
			);

		$container->alias(ConsoleApplication::class, BaseConsoleApplication::class)
			->share(
				BaseConsoleApplication::class,
				function (Container $container)
				{
					$app = new ConsoleApplication(null, $container->get('config'));

					$dispatcher = $container->get('Joomla\Event\DispatcherInterface');

					$session = new Session(new RuntimeStorage);
					$session->setDispatcher($dispatcher);

					$app->setCommandLoader($container->get(LoaderInterface::class));
					$app->setContainer($container);
					$app->setDispatcher($dispatcher);
					$app->setLogger($container->get(LoggerInterface::class));
					$app->setSession($session);

					// Dynamically register core commands
					$srcDir = JPATH_LIBRARIES . '/src/';
					$paths = glob($srcDir . '/Console/*.php');

					foreach ($paths as $key => $path) {
						$namespaces[] = str_replace(
							['.php', DIRECTORY_SEPARATOR],
							['', '\\'],
							explode($srcDir, $path)[1]
						);
					}
					foreach ($namespaces as $key => $command) {
							$className = '\Joomla\CMS' . $command;
							$app->addCommand($container->buildSharedObject($className));
					}

					return $app;
				},
				true
			);

		$container->alias(ContainerLoader::class, LoaderInterface::class)
			->share(
				LoaderInterface::class,
				function (Container $container)
				{
					$mapping = [
						'session:gc' => SessionGcCommand::class,
					];
					return new ContainerLoader($container, $mapping);
				},
				true
			);
	}
}
