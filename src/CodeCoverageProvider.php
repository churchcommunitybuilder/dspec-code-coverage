<?php

namespace CCB\DspecCodeCoverage;

use DSpec\Console\DSpecApplication;
use DSpec\Container;
use DSpec\Event\SuiteEndEvent;
use DSpec\Event\SuiteStartEvent;
use DSpec\Events;
use DSpec\ServiceProviderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CodeCoverageProvider implements ServiceProviderInterface
{
	public function register(Container $container)
	{
		if (!class_exists('\PHP_CodeCoverage')) {
			throw new \Exception('Please run composer require --dev phpunit/php-code-coverage:~2.2');
		}

		/** @var EventDispatcherInterface $dispatcher */
		$dispatcher = $container['dispatcher'];

		/** @var \PHP_CodeCoverage $coverage */
		$coverage = null;

		$dispatcher->addListener(Events::SUITE_START, function(SuiteStartEvent $event) use (&$coverage, $container) {
			$whitelistDirs = $container->offsetExists('coverage_whitelistDirs') ? $container['coverage_whitelistDirs'] : array();
			$blacklistDirs = $container->offsetExists('coverage_blacklistDirs') ? $container['coverage_blacklistDirs'] : array();

			$filter = null;
			if ((is_array($whitelistDirs) && count($whitelistDirs) > 0) ||
				(is_array($blacklistDirs) && count($blacklistDirs) > 0)) {
				$filter = new \PHP_CodeCoverage_Filter();
				foreach ($blacklistDirs as $dir) {
					$filter->addDirectoryToBlacklist($dir);
				}
				foreach ($whitelistDirs as $dir) {
					$filter->addDirectoryToWhitelist($dir);
				}
			}

			$coverage = new \PHP_CodeCoverage(null, $filter);
			$coverage->start($event->getExampleGroup()->getTitle());
		});

		$dispatcher->addListener(Events::SUITE_END, function(SuiteEndEvent $event) use (&$coverage) {
			$coverage->stop();

			$writer = new \PHP_CodeCoverage_Report_HTML();
			$writer->process($coverage, '/tmp/code-coverage-report');
		});
	}

	public function boot(DSpecApplication $app, Container $container)
	{
	}
}