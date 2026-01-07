# Media Manager

## Functions:

This AddOn allows the customization of graphics and handling of files based on media types. These media types are created and configured in the configuration of the AddOn. Each media type can contain any number of effects that are applied to the current medium. To embed a medium, the medium type must be noted in the URL.

## Generation of the URL:

### By PHP method

```php
use Redaxo\Core\MediaManager\MediaManager;

$url = MediaManager::getUrl($type,$file); 
```
> The path to the medium does not have to be specified.  


### Direct call via URL 

```
index.php?rex_media_type=MediaTypeName&amp;rex_media_file=MediaFileName
```

> MediaTypeName = The MediaManager type, MediaFileName = File name of the medium. The path to the medium does not have to be specified.  

## Annotations

***Effect "Convert to image"***

This effect requires ImageMagick and for PDF Ghostscript as command line binary, executable via exec().
