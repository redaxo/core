be_style-Addon
=============

This addon provides the assets for the REDAXO backend.

##  Compiling styles

If changes are made, the styles must be recompiled. For this purpose **be_style** provides several options.

### 1. via REDAXO console
Using the console of REDAXO the styles can be recompiled. For this option you use the compilation process provided by **be_style** via `be_style:compile` or `styles:compile`.

### 2. via package.yml
In the `package.yml` of the AddOn the value of `compile` can be set from `0` to `1`.
At the next request as a logged in backend user the styles will be recompiled.

##### Note
The value should be set back to `0` afterwards, otherwise the styles will be recompiled on every page load.


# Extension Points

### BE_STYLE_SCSS_FILES
This extension point can be used to add additional SCSS files to the compilation process of REDAXO.
This is useful if variables or CSS properties should be overwritten.

##### Example
```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('BE_STYLE_SCSS_FILES', function(ExtensionPoint $ep) {
   $files = $ep->getSubject();
   array_unshift($files, '/pfad/zu/meiner/scss-datei');
   return $files;
});
```

### BE_STYLE_SCSS_COMPILE
Own CSS files can be created via this extension point. When using own styles, for example in your AddOn or in the frontend, completely separate CSS files can be created via this EP.

##### Example
```php
use Redaxo\Core\ExtensionPoint\Extension;
use Redaxo\Core\ExtensionPoint\ExtensionPoint;

Extension::register('BE_STYLE_SCSS_FILES', function(ExtensionPoint $ep) {
   $files = $ep->getSubject();
   $files[] = [
       'scss_files' => 'pfad/zu/scss/dateien',   # Source SCSS files as string or array
       'css_file' => 'pfad/zur/ziel/css/datei',  # Path to the destination where the CSS file should be stored.

       'copy_dest' => 'pfad/zur/kopie',          # Optional: If the file should be stored in a second location, e.g. the assets folder, this can be specified here 
   ];
   return $files;
});
```
