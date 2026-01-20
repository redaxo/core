REDAXO-AddOn: project
=====================

The project addOn is used as a basis for custom project specific extensions. It is an initially empty addOn, which can be equipped with PHP classes, stylesheets, JavaScript, media and other data required in your project. REDAXO loads the addOn like all others and integrates it into its processes. However, it is different from other addOns in that its files are never overwritten or deleted during a system update.

Installation
------------

The addOn is delivered with the REDAXO core and has to be installed via the addOn administration only.

Folders
-------------

During the installation only the directory `lib/` is created. All PHP classes stored in this directory are available system-wide thanks to the autoloader and do not need to be included additionally.

**Example**

In the folder `lib` create the file `my_helpers.php`.

The code of the file may look like this:

```php
class my_helpers {
  public static function links_to_blank ($text) {
    return str_replace('<a href="http','<a target="_blank" href="http',$text);
  }
}
```

Now you can replace the links in every module where you want http(s) links to open in a new browser window:

```php
echo my_helpers::links_to_blank($text);
```

Additional folders (like `pages/`, `fragments/`, etc.) can be created directly within the project addOn. In the documentation you will find the corresponding notes: https://redaxo.org/doku/5.x/addon-struktur (German)

Files
-------

### boot.php

The file 'boot.php' is executed during the initialization of REDAXO, i means before the execution of templates and modules.

So an additional path for yform templates can be specified here:

```php
rex_yform::addTemplatePath($this->getPath('yform-templates'));
```

Now templates for the output of the yform fields are also searched in the path `src/addons/project/yform-templates/[theme-name]`, where [theme-name] must be replaced by the name of the theme (default is bootstrap).

With this code

```php
if (rex::isBackend()) {
    rex_view::addJsFile($this->getAssetsUrl('scripts/be_scripts.js'));    
}
```

the file `/assets/addons/project/scripts/be_scripts.js` is loaded in the backend of REDAXO.

The use of extension points in the boot.php is also useful:

```php
if (!rex::isBackend()) {
    // eine Session im Frontend wird gestartet
    rex_login::startSession();    
    rex_extension::register('PACKAGES_INCLUDED', function() {
        // der Code hier wird erst ausgeführt, wenn alle AddOns geladen sind
        // ....
    });
}
```
