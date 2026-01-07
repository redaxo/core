# Media Manager

## Funktionen:

Das AddOn erlaubt das Anpassen von Grafiken und Handling von Dateien anhand von Medientypen. Die Medientypen werden in der Verwaltung des AddOns erstellt und konfiguriert. Jeder Medientyp kann beliebig viele Effekte enthalten, die auf das aktuelle Medium angewendet werden. Zum Einbinden eines Mediums muss dazu der Medientyp in der URL notiert werden.


## Erzeugung der URL:

### Mittels PHP-Methode

```php
use Redaxo\Core\MediaManager\MediaManager;

$url = MediaManager::getUrl($type,$file); 
```
> Der Pfad zum Medium muss nicht angegeben werden.

### Direkter Auruf per URL 

```
index.php?rex_media_type=MediaTypeName&amp;rex_media_file=MediaFileName
```

> MediaTypeName = Der MediaManager-Typ, MediaFileName = Dateiname des Mediums. Der Pfad zum Medium muss nicht angegeben werden.  

## Hinweise

***Effekt "In Bild konvertieren"***

Dieser Effekt benötigt ImageMagick und für PDF Ghostscript als Commandline-Binary, ausführbar per exec().
