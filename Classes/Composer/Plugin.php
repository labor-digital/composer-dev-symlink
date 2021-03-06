<?php
/**
 * Copyright 2019 LABOR.digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2019.03.13 at 14:00
 */

namespace LaborDigial\ComposerDevSymlink\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use RuntimeException;
use Throwable;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * Added to the original directory to mark it as a backup to revert
     */
    const BACKUP_SUFFIX = '.dev-symlink-bkp';

    /**
     * True if the revertSymlinks() ran at least once...
     *
     * @var bool
     */
    protected $reverted = false;

    /**
     * True if this is an action on the root package (global installation); we ignore that stuff...
     *
     * @var bool
     */
    protected $isRoot = false;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'postAutoload',
            ScriptEvents::PRE_AUTOLOAD_DUMP  => 'revertSymlinks',
            ScriptEvents::PRE_INSTALL_CMD    => 'revertSymlinks',
            ScriptEvents::PRE_UPDATE_CMD     => 'revertSymlinks',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->isRoot = $composer->getPackage()->getName() === '__root__';
    }

    /**
     * Reverts the created backups or removes them if required
     *
     * @param   \Composer\Script\Event  $event
     */
    public function revertSymlinks(Event $event): void
    {
        // Ignore the global installation
        if ($this->isRoot) {
            return;
        }

        // Skip if we already ran
        if ($this->reverted) {
            return;
        }
        $this->reverted = true;

        // Find all locally installed packages and revert backups
        $composer = $event->getComposer();
        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            // Flush our install path
            $installPath       = $composer->getInstallationManager()->getInstallPath($package);
            $installBackupPath = $installPath . static::BACKUP_SUFFIX;
            if (! is_dir($installBackupPath)) {
                continue;
            }

            // Remove the symlink
            if (is_link($installPath)) {
                unlink($installPath);
            }

            // Remove the file or directory
            if (file_exists($installPath)) {
                $this->rmdir($installBackupPath);
                $event->getIO()->write('Removed backup of package: "' . $package->getName() . '"...');
            } else {
                rename($installBackupPath, $installPath);
                $event->getIO()->write(
                    'The package: "' . $package->getName() . '" was restored to it\'s original state...');
            }
        }
    }

    /**
     * Runs after composer dumped the autoload files which means there was probably stuff we need to know about...
     *
     * @param   \Composer\Script\Event  $event
     */
    public function postAutoload(Event $event): void
    {
        // Ignore the global installation
        if ($this->isRoot) {
            return;
        }

        // Get references
        $composer    = $event->getComposer();
        $io          = $event->getIO();
        $repoManager = $composer->getRepositoryManager();

        // Prepare path to load the overrides from
        $devPath = rtrim(getcwd(), '/\\') . '/vendor-dev/*';
        $extra   = $composer->getPackage()->getExtra();
        if (! empty($extra['composer-dev-symlink']) && is_string($extra['composer-dev-symlink'])) {
            $devPath = rtrim($extra['composer-dev-symlink'], ' *\\/') . '/*';
        }

        // Introduce ourselves
        $io->write('Checking for dev-only package overrides in: "' . $devPath . '"...');

        // Ignore if the directory does not exist or does not have contents
        try {
            $contents = glob($devPath);
            if (! $contents || empty($contents)) {
                return;
            }
        } catch (Throwable $e) {
            $io->write('Exception while searching for packages: ' . $e->getMessage());

            return;
        }

        // Create our own repository to read the packages in the vendor-dev directory
        $repo = $repoManager->createRepository('path', [
            'url' => $devPath,
        ]);

        // Find a list of all available override packages
        $overridePackages = [];
        foreach ($repo->getPackages() as $package) {
            $overridePackages[$package->getName()] = realpath($package->getDistUrl());
        }

        // Skip if there are no override packages
        if (empty($overridePackages)) {
            return;
        }

        // Find a list of all locally installed packages we might want to override
        $installedPackages = [];
        foreach ($repoManager->getLocalRepository()->getPackages() as $package) {
            $installedPackages[$package->getName()] = $composer->getInstallationManager()->getInstallPath($package);
        }

        // Create a list of all targets
        $targetPackages = array_intersect_key($installedPackages, $overridePackages);
        if (empty($targetPackages)) {
            return;
        }

        // Create symlinks if the directories aren't symlinks already...
        foreach ($targetPackages as $key => $pathToOverride) {
            // Ignore if link exists
            if (is_link($pathToOverride)) {
                $io->write('The package: "' . $key . '" is already a symlink, skip...');
                continue;
            }

            // Skip if there is an unknown package required
            if (! isset($overridePackages[$key])) {
                throw new RuntimeException(
                    'Found an unknown target package which is not known in the override packages! "' . $key . '"');
            }

            // Move original package out of the way and create a symlink to it's source
            rename($pathToOverride, $pathToOverride . static::BACKUP_SUFFIX);
            symlink($overridePackages[$key], $pathToOverride);
            $io->write('The package: "' . $key . '" was sym-linked to the dev-source at: "' .
                       $overridePackages[$key] . '"');
        }
    }

    /**
     * Recursively remove a directory
     *
     * @param $dir
     */
    protected function rmdir($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (is_dir($dir . '/' . $object)) {
                        $this->rmdir($dir . '/' . $object);
                    } else {
                        unlink($dir . '/' . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io): void { }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io): void { }
}
