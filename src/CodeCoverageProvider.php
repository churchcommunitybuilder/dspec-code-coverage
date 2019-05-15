<?php

namespace CCB\DspecCodeCoverage;

use DSpec\Console\DSpecApplication;
use DSpec\Container;
use DSpec\Event\SuiteStartEvent;
use DSpec\Events;
use DSpec\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Html\Facade;

class CodeCoverageProvider implements ServiceProviderInterface
{
	public function register(Container $container)
	{
		/** @var EventDispatcherInterface $dispatcher */
		$dispatcher = $container['dispatcher'];

		/** @var CodeCoverage $coverage */
		$coverage = null;

		$dispatcher->addListener(Events::SUITE_START, function(SuiteStartEvent $event) use (&$coverage, $container) {
			$whitelistDirs = $container->offsetExists('coverage.whitelistDirs') ? $container['coverage.whitelistDirs'] : array();

			$filter = null;
			if (is_array($whitelistDirs) && count($whitelistDirs) > 0) {
				$filter = new Filter();
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

			$coverage = new CodeCoverage(null, $filter);
			$coverage->start($event->getExampleGroup()->getTitle());
		});

		$dispatcher->addListener(Events::SUITE_END, function() use (&$coverage, $container) {
			$coverage->stop();

			$outputDir = $container['coverage.outputDir'];

			$reportWriterType = $container->offsetExists('coverage.reportWriter') ? $container['coverage.reportWriter'] : 'html';

			if ($reportWriterType === 'html') {
				$writer = new Facade();
			}

			$writer->process($coverage, $outputDir);
		});
	}

	public function boot(DSpecApplication $app, Container $container)
	{
	}
}