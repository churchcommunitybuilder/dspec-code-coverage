<?php

namespace CCB\DspecCodeCoverage;

use DSpec\Console\DSpecApplication;
use DSpec\Container;
use DSpec\Event\SuiteEndEvent;
use DSpec\Event\SuiteStartEvent;
use DSpec\Events;
use DSpec\ServiceProviderInterface;
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
			$whitelistDirs = $container->offsetExists('coverage.whitelistDirs') ? $container['coverage.whitelistDirs'] : array();
			$blacklistDirs = $container->offsetExists('coverage.blacklistDirs') ? $container['coverage.blacklistDirs'] : array();

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

			$defaultOutputDir = '/tmp/code-coverage-report';
			if ($container->offsetExists('coverage.outputDir')) {
				$outputDir = $container['coverage.outputDir'];
				if (strpos($outputDir, '/') !== 0) {
					$outputDir = getcwd().'/'.$outputDir;
				}
				$parentDir = dirname($outputDir);

				if (!is_writable($parentDir)) {
					throw new \Exception("Directory {$parentDir} is not writable");
				}
			}

			if (!isset($outputDir) || empty($outputDir)) {
				$outputDir = $defaultOutputDir;
			}

			$container['coverage.outputDir'] = $outputDir;

			$coverage = new \PHP_CodeCoverage(null, $filter);
			$coverage->start($event->getExampleGroup()->getTitle());
		});

		$dispatcher->addListener(Events::SUITE_END, function() use (&$coverage, $container) {
			$coverage->stop();

			$outputDir = $container['coverage.outputDir'];

			$reportWriterType = $container->offsetExists('coverage.reportWriter') ? $container['coverage.reportWriter'] : 'html';

			switch ($reportWriterType) {
				case 'html':
				default:
					$writer = new \PHP_CodeCoverage_Report_HTML();
					break;
			}
			$writer->process($coverage, $outputDir);
		});
	}

	public function boot(DSpecApplication $app, Container $container)
	{
	}
}