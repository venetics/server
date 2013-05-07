<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC;

class Autoloader {
	private $useGlobalClassPath = true;

	private $prefixPaths = array();

	private $classPaths = array();

	/**
	 * Add a custom prefix to the autoloader
	 *
	 * @param string $prefix
	 * @param string $path
	 */
	public function registerPrefix($prefix, $path) {
		$this->prefixPaths[$prefix] = $path;
	}

	/**
	 * Add a custom classpath to the autoloader
	 *
	 * @param string $class
	 * @param string $path
	 */
	public function registerClass($class, $path) {
		$this->classPaths[$class] = $path;
	}

	/**
	 * disable the usage of the global classpath \OC::$CLASSPATH
	 */
	public function disableGlobalClassPath() {
		$this->useGlobalClassPath = false;
	}

	/**
	 * enable the usage of the global classpath \OC::$CLASSPATH
	 */
	public function enableGlobalClassPath() {
		$this->useGlobalClassPath = true;
	}

	/**
	 * get the possible paths for a class
	 *
	 * @param string $class
	 * @return array|bool an array of possible paths or false if the class is not part of ownCloud
	 */
	public function findClass($class) {
		$class = trim($class, '\\');

		$paths = array();
		if (array_key_exists($class, $this->classPaths)) {
			$paths[] = $this->classPaths[$class];
		} else if ($this->useGlobalClassPath and array_key_exists($class, \OC::$CLASSPATH)) {
			$paths[] = \OC::$CLASSPATH[$class];
			/**
			 * @TODO: Remove this when necessary
			 * Remove "apps/" from inclusion path for smooth migration to mutli app dir
			 */
			if (strpos(\OC::$CLASSPATH[$class], 'apps/') === 0) {
				\OC_Log::write('core', 'include path for class "' . $class . '" starts with "apps/"', \OC_Log::DEBUG);
				$paths[] = str_replace('apps/', '', \OC::$CLASSPATH[$class]);
			}
		} elseif (strpos($class, 'OC_') === 0) {
			// first check for legacy classes if underscores are used
			$paths[] = 'legacy/' . strtolower(str_replace('_', '/', substr($class, 3)) . '.php');
			$paths[] = strtolower(str_replace('_', '/', substr($class, 3)) . '.php');
		} elseif (strpos($class, 'OC\\') === 0) {
			$paths[] = strtolower(str_replace('\\', '/', substr($class, 3)) . '.php');
		} elseif (strpos($class, 'OCP\\') === 0) {
			$paths[] = 'public/' . strtolower(str_replace('\\', '/', substr($class, 3)) . '.php');
		} elseif (strpos($class, 'OCA\\') === 0) {
			foreach (\OC::$APPSROOTS as $appDir) {
				list(, $app,) = explode('\\', $class);
				if (stream_resolve_include_path($appDir['path'] . '/' . strtolower($app))) {
					$paths[] = $appDir['path'] . '/' . strtolower(str_replace('\\', '/', substr($class, 4)) . '.php');
					// If not found in the root of the app directory, insert '/lib' after app id and try again.
					$paths[] = $appDir['path'] . '/lib/' . strtolower(str_replace('\\', '/', substr($class, 4)) . '.php');
				}
			}
		} elseif (strpos($class, 'Test_') === 0) {
			$paths[] = 'tests/lib/' . strtolower(str_replace('_', '/', substr($class, 5)) . '.php');
		} elseif (strpos($class, 'Test\\') === 0) {
			$paths[] = 'tests/lib/' . strtolower(str_replace('\\', '/', substr($class, 5)) . '.php');
		} else {
			foreach ($this->prefixPaths as $prefix => $dir) {
				if (0 === strpos($class, $prefix)) {
					$path = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
					$path = str_replace('_', DIRECTORY_SEPARATOR, $path);
					$paths[] = $dir . '/' . $path;
				}
			}
		}
		return $paths;
	}

	/**
	 * Load the specified class
	 *
	 * @param string $class
	 * @return bool
	 */
	public function load($class) {
		$paths = $this->findClass($class);

		if (is_array($paths)) {
			foreach ($paths as $path) {
				if ($fullPath = stream_resolve_include_path($path)) {
					require_once $fullPath;
				}
			}
		}
		return false;
	}
}
