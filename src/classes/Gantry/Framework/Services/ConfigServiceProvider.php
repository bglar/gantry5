<?php
/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2016 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Framework\Services;

use Gantry\Component\Config\CompiledBlueprints;
use Gantry\Component\Config\CompiledConfig;
use Gantry\Component\Config\ConfigFileFinder;
use Gantry\Framework\Atoms;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class ConfigServiceProvider implements ServiceProviderInterface
{
    public function register(Container $gantry)
    {
        $gantry['blueprints'] = function($c) {
            GANTRY_DEBUGGER && \Gantry\Debugger::startTimer('blueprints', 'Loading blueprints');

            $blueprints = static::blueprints($c);

            GANTRY_DEBUGGER && \Gantry\Debugger::stopTimer('blueprints');

            return $blueprints;
        };

        $gantry['config'] = function($c) {
            // Make sure configuration has been set.
            if (!isset($c['configuration'])) {
                throw new \LogicException('Gantry: Please set current configuration before using $gantry["config"]', 500);
            }

            GANTRY_DEBUGGER && \Gantry\Debugger::startTimer('config', 'Loading configuration');

            // Get the current configuration and lock the value from modification.
            $configuration = $c->lock('configuration');

            $config = static::load($c, $configuration);

            GANTRY_DEBUGGER && \Gantry\Debugger::setConfig($config)->stopTimer('config');

            return $config;
        };
    }

    public static function blueprints(Container $container)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $container['locator'];

        $cache = $locator->findResource('gantry-cache://theme/compiled/blueprints', true, true);

        $files = [];
        $paths = $locator->findResources('gantry-particles://');
        $files += (new ConfigFileFinder)->setBase('particles')->locateFiles($paths);
        $paths = $locator->findResources('gantry-blueprints://');
        $files += (new ConfigFileFinder)->locateFiles($paths);

        $config = new CompiledBlueprints($cache, $files, GANTRY5_ROOT);

        return $config->load();
    }

    public static function load(Container $container, $name = 'default')
    {
        /** @var UniformResourceLocator $locator */
        $locator = $container['locator'];

        // Merge current configuration with the default.
        $uris = array_unique(["gantry-config://{$name}", 'gantry-config://default']);

        $paths = [];
        foreach ($uris as $uri) {
            $paths = array_merge($paths, $locator->findResources($uri));
        }

        // Locate all configuration files to be compiled.
        $files = (new ConfigFileFinder)->locateFiles($paths);

        $cache = $locator->findResource('gantry-cache://theme/compiled/config', true, true);

        if (!$cache) {
            throw new \RuntimeException('Who just removed Gantry 5 cache folder? Try reloading the page if it fixes the issue');
        }

        $compiled = new CompiledConfig($cache, $files, GANTRY5_ROOT);
        $compiled->setBlueprints(function() use ($container) {
            return $container['blueprints'];
        });

        $config = $compiled->load(true);

        // Set atom inheritance.
        $atoms = new Atoms((array) $config->get('page.head.atoms'));
        $config->set('page.head.atoms', $atoms->init()->toArray());

        return $config;
    }
}
