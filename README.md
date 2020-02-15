# Composer Dev-Symlink
This plugin aims to assist when working on local composer packages without using a "path" repository, because that will break the composer.lock file. The goal was to simulate the basic behaviour of "npm link" to create symlinks and have the local packages in deployment in a single directory.

## Installation
Simply install this plugin by using composer:
```
composer require labor-digital/composer-dev-symlink
```

If you want to install the plugin globally instead, feel free to do so by using:
```
composer require -g labor-digital/composer-dev-symlink
```

## Configuration
By default the plugin will look for packages to link in the directory: `/var/www/html/vendor-dev/`. However, if that does not fit your needs you can always change the directory by setting it in your project's composer.json:
```json
{
	"extra": {
		"composer-dev-symlink": "./my-dev/*"
	}
}
```

## To keep in mind
* This plugin is installed globally in our [php dev images](https://github.com/labor-digital/docker-base-images) 
* There are plenty of other plugins that aim to do the same stuff, but did not work as globally installed plugin or broke the composer.lock file.
  * https://github.com/franzliedke/studio/issues/89
  * https://github.com/DHager/composer-haydn
  * https://github.com/Letudiant/composer-shared-package-plugin/pull/21

## Postcardware
You're free to use this package, but if you use it regularly in your development environment we highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using.

Our address is: LABOR.digital - Fischtorplatz 21 - 55116 Mainz, Germany

We publish all received postcards on our [company website](https://labor.digital).