# ImgResize
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT) 

Yet another library based on gd to resize images with method chaining.


### Examle
```php
require_once ('vendor/autoload.php');

use Root4root\ImgResize\ImgResize;

$image = new ImgResize('pathToImage.jpg');

$image->resampleToHeight(500)
    ->sharpen()
    ->save('pathToSave.gif');

$watermark = new ImgResize('pathToWatermark.png');

$image->addImage(new ImgResize('anotherImage.png'))
    ->joinVertical()
    ->watermark($watermark)
    ->save('pathToSave.jpg');

$image->watermark($watermark)->save('pathToSave2.jpg');

```
### Notes
Every time you modify image, ImgResize returns new instanse of self as a result. So you could assign instance to a variable at any moment, which will stay immutable. Be careful with saving instances due to the memory limit, though.

```php
$imageWithWatermark = (new ImgResize('pathToImage.jpg'))->watermark(new ImgResize('pathToWater.png'));
$imageWithWatermark->resampleToRectangle(500,500)->save('path.jpg'); //Fits image to rectangle by height or width - which is bigger.  
$imageWithWatermark->resampleToWidth(1000)->sharpen()->save('path.png'); //Still one resample - best quality

```
