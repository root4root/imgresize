<?php
namespace Root4root\ImgResize;

use Root4root\ImgResize\Exception\BadImageFileException;
use Root4root\ImgResize\Exception\NotEnoughImagesException;
use Root4root\ImgResize\Exception\BadResourceException;

class ImgResize
{
    /**
     * Immutable source image
     * 
     * @var gd resource 
     */
    protected $gdResource;
    
    /**
     *
     * @var gd constant 
     */
    protected $imageType = null;
    
    /**
     *
     * @var string
     */
    protected $filename;
    
    /**
     *
     * @var array For type recognition by extension
     */
    private $typeHint = [
        'gif' => IMAGETYPE_GIF,
        'png' => IMAGETYPE_PNG,
        'jpg' => IMAGETYPE_JPEG
    ];
    
    /**
     *
     * @var array gd resources 
     */
    protected $imagesCollection = [];
    
    /**
     * 
     * @param string $imageFilePath
     */
    public function __construct($imageFilePath = false)
    {
        if ($imageFilePath !== false) {
            $this->load($imageFilePath);
        }
    }
    
    public function getWidth()
    {
        return imagesx($this->gdResource);
    }
    
    public function getHeight()
    {
        return imagesy($this->gdResource);
    }
    
    /**
     * 
     * @return gd resource link
     */
    public function getResource()
    {
        return $this->gdResource;
    }
    
    /**
     * 
     * @param resource $gdResource
     * @throws BadResourceException
     */
    public function setResource($gdResource)
    {
        if (! is_resource($gdResource) || get_resource_type($gdResource) != 'gd') {
            throw new BadResourceException('Should be gd resuorce, forgot to load image?');
        }
        
        if (is_resource($this->gdResource)) {
            imagedestroy($this->gdResource);
        }
        
        $this->gdResource = $gdResource;
    }
    
    public function getType()
    {
        return $this->imageType;
    }
    
    public function setType($type)
    {
        $this->imageType = $type;
    }
    
   
    /**
     * 
     * @param string $imageFilePath
     * @return $this
     * @throws BadImageFileException
     */
    public function load($imageFilePath) 
    {
        if (! file_exists($imageFilePath)) {
            throw new BadImageFileException('No such file: ' . $imageFilePath);
        }
        
        $imageInfo = getimagesize($imageFilePath);
        $this->imageType = $imageInfo[2];
        
        $this->filename = $imageFilePath;
        
        if ($this->imageType == IMAGETYPE_JPEG) {
            $this->gdResource = imagecreatefromjpeg($imageFilePath);
            if ($this->getHeight() < $this->getWidth()) {
                $this->exifRotate($imageFilePath);
            }
        } elseif ($this->imageType == IMAGETYPE_GIF) {
            $this->gdResource = imagecreatefromgif($imageFilePath);
        } elseif($this->imageType == IMAGETYPE_PNG) {
            $this->gdResource = imagecreatefrompng($imageFilePath);
        } else {
            throw new BadImageFileException('Unable to load image: unknown format ' . $this->imageType);
        }
        
        return $this;
        
    }
    
    /**
     * Used with joinHorizontal() and joinVertical()
     * 
     * @param ImgResize $imageResource
     * @return $this
     */
    public function addImage(ImgResize $imageResource)
    {
        if (is_resource($this->gdResource) && empty($this->imagesCollection)) {
            $this->imagesCollection[] = $this;
        }
        
        $this->imagesCollection[] = $imageResource;
        
        return $this;
    }
   
    public function save($filePath, $jpgCompression = 95) 
    {
        $imageType = $this->hintTypeByExtension($filePath);
        
         switch ($imageType) {
            case IMAGETYPE_JPEG:
                if ($this->imageType == IMAGETYPE_PNG) {
                   $this->gdResource = $this->prepareBackground($this->gdResource);
                }
                imagejpeg($this->gdResource, $filePath, $jpgCompression);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->gdResource, $filePath);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->gdResource, $filePath);
                break;
        }
        
        return $this;
    }
    
    /**
     * 
     * @param string $type jpg|gif|png
     * @return string Binary representation of image
     */
    public function getImageString($type = 'jpg')
    {
        $imageType = $this->hintTypeByExtension('qwerty.' . $type);
        
        ob_start();
         switch ($imageType) {
            case IMAGETYPE_JPEG:
                if ($this->imageType == IMAGETYPE_PNG) {
                   $this->gdResource = $this->prepareBackground($this->gdResource);
                }
                imagejpeg($this->gdResource);
                break;
            case IMAGETYPE_GIF:
                imagegif($this->gdResource);
                break;
            case IMAGETYPE_PNG:
                imagepng($this->gdResource);
                break;
        }
        $stringdata = ob_get_contents();
        ob_end_clean();
        
        return $stringdata;
    }
    
    //******************** perform modifications *******************************
    
    public function scale($scale) 
    {
        $width = $this->getWidth() * $scale / 100;
        $height = $this->getHeight() * $scale / 100;
        
        return $this->resize($width, $height);
    }
 
    public function resize($width, $height)  //Actual
    {
        $this->checkResource();
        
        $newImage = $this->createCanvas($width, $height);
        imagecopyresized($newImage, $this->gdResource, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
                
        return $this->createInstance($newImage);
    }      
   
    public function resample($width, $height) //Actual
    {
        $this->checkResource();
        
        $newImage = $this->createCanvas($width, $height);
        imagecopyresampled($newImage, $this->gdResource, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
        
        return $this->createInstance($newImage);
    }
    
    /**
     * Resize image without resampling (lower quality, higher speed)
     * See also resampleToWidth()
     * 
     * @param int $height
     * @return ImgResize new object
     */
    public function resizeToHeight($height) 
    {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        
        return $this->resize($width, $height);
    }
 
    /**
     * Resize image without resampling (lower quality, higher speed)
     * See also resampleToWidth()
     * 
     * @param int $width
     * @return ImgResize new object
     */
    public function resizeToWidth($width) 
    {
        $ratio = $width / $this->getWidth();
        $height = $this->getHeight() * $ratio;

        return $this->resize($width, $height);
    }
   
    /**
     * Resample image (better quality, lower speed)
     * 
     * @param int $height
     * @return ImgResize new object
     */
    public function resampleToHeight($height) 
    {
        $ratio = $height / $this->getHeight();
        $width = $this->getWidth() * $ratio;
        
        return $this->resample($width, $height);
    }
 
    /**
     * Resample image (better quality, lower speed)
     * 
     * @param int $width
     * @return ImgResize new object
     */
    public function resampleToWidth($width) 
    {
        $ratio = $width / $this->getWidth();
        $height = $this->getHeight() * $ratio;

        return $this->resample($width, $height);
    }
    

    /**
     * Resize image to fit rectangle (resize to height or width)
     * 
     * @param   int       $width      width of rectangle
     * @param   int       $height     height of rectangle
     * @param   boolean   $ifSmaller  upsize if source image smaller
     * 
     * @return  $this|ImgResize Depends is modification occur.
     */
    public function resampleToRectangle($width, $height, $ifSmaller = false)
    {
        $sourceWidth = $this->getWidth();
        $sourceHeight = $this->getHeight();
        $sourceratio = $sourceWidth / $sourceHeight;
        $destinationRatio = $width / $height;
        
        if ($sourceWidth > $width || $sourceHeight > $height || $ifSmaller === true) {
            if ($sourceratio > $destinationRatio) {
                return $this->resampleToWidth($width);
            } else {
                return $this->resampleToHeight($height);
            }
        }

        return $this;
    }
    
    
    /**
     * 
     * @param float|int $strenght Strength of sharpen effect, 
     * @return object Imgresize New object
     */
    public function sharpen($strenght = 18.0) //Actual
    {
       $this->checkResource();
       
       $str = (float)$strenght;
       
        /*Less value in the middle means more sharpen*/
        $sharpenMatrix = [
            [0.0,  -1.0, 0.0],
            [-1.0, $str, -1.0],
            [0.0,  -1.0, 0.0]
        ];
        
        $resource = $this->createResourceCopy();
       
        $divisor = array_sum(array_map('array_sum', $sharpenMatrix));
        imageconvolution($resource, $sharpenMatrix, $divisor, 0);
        
        return $this->createInstance($resource);
    }
   
    
    /**
     * Watermark image with transparent PNG.
     * 
     * @param ImgResize $watermark With png watermark loaded
     * @return ImgResize New instance
     */
    public function watermark(ImgResize $watermark) //Actual
    {
        $this->checkResource();
        
        $waterWidth = $watermark->getWidth();
        $waterHeight = $watermark->getHeight();
        
        $dstX = ($this->getWidth() - $waterWidth) / 2;
        $dstY = ($this->getHeight() - $waterHeight) / 2;
        
        $cut = imagecreatetruecolor($waterWidth, $waterHeight);
        
        imagecopy($cut, $this->gdResource, 0, 0, $dstX, $dstY, $waterWidth, $waterHeight);
        imagecopy($cut, $watermark->getResource(), 0, 0, 0, 0, $waterWidth, $waterHeight);
        
        $resource = $this->createResourceCopy();
        
        imagecopymerge($resource, $cut, $dstX, $dstY, 0, 0, $waterWidth, $waterHeight, 100);

        return $this->createInstance($resource);
    }
    
       
    /**
     * 
     * @return ImgResize New instance
     * @throws NotEnoughImagesException
     */
    public function joinHorizontal() //Actual
    {
        if (count($this->imagesCollection) < 2) {
            throw new NotEnoughImagesException('joinHorizontal(): at least 2 images');
        }
        
        //Find smallest picture height
        $minHeight = $this->imagesCollection[0]->getHeight();
        
        for ($i = 1; $i < count($this->imagesCollection); $i++) {
            
            $currentHeight = $this->imagesCollection[$i]->getHeight();
            
            if ($minHeight > $currentHeight) {
                $minHeight = $currentHeight;
            }
        }
        
        $widthArray = [];
        $overallWidth = 0;
        $newObjects = [];
        
        //All pictures to one height
        foreach ($this->imagesCollection as $key => $obj) {
            if ($obj->getHeight() > $minHeight) {
                $newImage = $obj->resampleToHeight($minHeight)->sharpen();
                $widthArray[$key] = $newImage->getWidth();
                $newObjects[] = $newImage;
            } else {
                $widthArray[$key] = $obj->getWidth();
                $newObjects[] = $obj;
            }
            $overallWidth += $widthArray[$key];
        }

        $resultImg = $this->createCanvas($overallWidth, $minHeight);
        
        $destX = 0;
        
        foreach ($newObjects as $key => $obj) {
            imagecopy($resultImg, $obj->getResource(), $destX, 0, 0, 0, $widthArray[$key], $minHeight);
            $destX += $widthArray[$key];
        }
        
        return $this->createInstance($resultImg);
    }
    
    
    /**
     * 
     * @return ImgResize new object
     * @throws NotEnoughImagesException
     */
    public function joinVertical() //Actual
    {
        if (count($this->imagesCollection) < 2) {
            throw new NotEnoughImagesException('joinVertical(): at least 2 images');
        }
        
        //Find smallest picture width
        $minWidth = $this->imagesCollection[0]->getWidth();

        
        for ($i = 1; $i < count($this->imagesCollection); $i++) {
            
            $currentWidth = $this->imagesCollection[$i]->getWidth();
            
            if ($minWidth > $currentWidth) {
                $minWidth = $currentWidth;
            }
        }

        $heightArray = [];
        $overallHeight = 0;
        $newObjects = [];
        
        //All pictures to one width
        foreach ($this->imagesCollection as $key => $obj) {
            if ($obj->getWidth() > $minWidth) {
                $newImage = $obj->resampleToWidth($minWidth)->sharpen();
                $heightArray[$key] = $newImage->getHeight();
                $newObjects[] = $newImage;
            } else {
                $heightArray[$key] = $obj->getHeight();
                $newObjects[] = $obj;
            }
            
            $overallHeight += $heightArray[$key];
        }
        
        $resultImg = $this->createCanvas($minWidth, $overallHeight);
        
        $destY = 0;
        
        foreach ($newObjects as $key => $obj) {
            imagecopy($resultImg, $obj->getResource(), 0, $destY, 0, 0, $minWidth, $heightArray[$key]);
            $destY += $heightArray[$key];
        }
        
        return $this->createInstance($resultImg);
    }
    
    //****************************** heplers ***********************************
    
    private function exifRotate($path)
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        $exif = exif_read_data($path);
        
        if (empty($exif['Orientation'])) {
            return false;
        }
        
        switch ($exif['Orientation']) {
            case 3:
                $this->gdResource = imagerotate($this->gdResource, 180, 0);
                break;
            case 6:
                $this->gdResource = imagerotate($this->gdResource, -90, 0);
                break;
            case 8:
                $this->gdResource = imagerotate($this->gdResource, 90, 0);
                break;
        }
        
        return true;
    }
    
    private function createCanvas($width, $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        
        if ($this->imageType == IMAGETYPE_PNG) {
            $bg = imagecolorallocate($canvas, 0, 0, 0);
            imagecolortransparent($canvas, $bg);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        } 
        
        if ($this->imageType == IMAGETYPE_GIF) {
            $bg = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $bg);
            imagecolortransparent($canvas, $bg);
        }
        
        return $canvas;
    }
    
    private function prepareBackground($resource)
    {
        $width = imagesx($resource);
        $height = imagesy($resource);
        
        $temp = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($temp, 255, 255, 255);
        imagefill($temp, 0, 0, $bg);
        
        imagecopy($temp, $resource, 0, 0, 0, 0, $width, $height);
        
        return $temp;
    }
    
    private function createResourceCopy()
    {
        $width = $this->getWidth();
        $height = $this->getHeight();
        
        $canvas = $this->createCanvas($width, $height);
        
        imagecopy($canvas, $this->gdResource, 0, 0, 0, 0, $width, $height);
        
        return $canvas;
    }
    
    private function createInstance($gdResource)
    {
        $instance = new self();
        $instance->setResource($gdResource);
        $instance->setType($this->imageType);
        
        return $instance;
    }
    
    private function hintTypeByExtension($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (array_key_exists($extension, $this->typeHint)) {
            return $this->typeHint[$extension];
        }
        
        return $this->typeHint['jpg'];
    }
    
    
    private function checkResource()
    {
        if (! is_resource($this->gdResource)) {
            throw new NotEnoughImagesException('Load image file first!');
        }
    }


    public function __destruct()
    {
        if (is_resource($this->gdResource)) {
            imagedestroy($this->gdResource);
        }
    }
}
