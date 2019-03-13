# Composer Dev-Symlink
This plugin aims to assist when working on local composer packages without using a "path" repository, because that will break the composer.lock file. The goal was to simulate the basic behaviour of "npm link" to create symlinks and have the local packages in deployment in a single directory.

By default the plugin will look for packages to link in the directory: `/var/www/html/vendor-dev/`. However, if that does not fit your needs you can always change the directory by setting it in your project's composer.json:
```json
{
	"extra": {
		"composer-dev-symlink": "./my-dev/*"
	}
}
```

## To keep in mind
* This plugin is installed globally in our php dev images 
* If it is not available in your ecosystem it by using `composer (global) require labor/composer-dev-symlink`
* There are plenty of other plugins that aim to do the same stuff, but did not work as globally installed plugin or broke the composer.lock file.
  * https://github.com/franzliedke/studio/issues/89
  * https://github.com/DHager/composer-haydn
  * https://github.com/Letudiant/composer-shared-package-plugin/pull/21
