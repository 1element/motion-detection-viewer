<?php

namespace MotionDetectionViewer;

/**
 * Image thumbnail service.
 *
 * Collection of thumbnail related tasks:
 * Create thumbnails, cleanup old thumbnails, move thumbnails.
 */
class ImageThumbnailService
{
  protected $width;
  protected $height;
  protected $finder;
  protected $sideFinder;
  protected $subfolderFinder;
  protected $fileNames = array();
  protected $sideFilenames = array();
  protected $images = array();

    /**
     * Constructor.
     *
     * @param int $width   thumbnail width
     * @param int $height  thumbnail height
     * @param $finder
     * @param $sideFinder
     * @param $subfolderFinder
     */
  public function __construct($width, $height, $finder, $sideFinder, $subfolderFinder)
  {
    $this->width = $width;
    $this->height = $height;
    $this->finder = $finder;
    $this->sideFinder = $sideFinder;
    $this->subfolderFinder = $subfolderFinder;
  }

  /**
   * Return thumbnail width.
   *
   * @return int
   */
  protected function getWidth()
  {
    return $this->width;
  }
  
  /**
   * Return thumbnail height.
   *
   * @return int
   */
  protected function getHeight()
  {
    return $this->height;
  }

  /**
   * Return Finder instance.
   *
   * @return Symfony\Component\Finder\Finder
   */
  protected function getFinder()
  {
    return $this->finder;
  }

  /**
   * Return Finder instance.
   *
   * @return Symfony\Component\Finder\Finder
   */
  protected function getSideFinder()
  {
      return $this->sideFinder;
  }

  /**
   * Return Finder instance.
   *
   * @return Symfony\Component\Finder\Finder
   */
  protected function getSubfolderFinder()
  {
      return $this->subfolderFinder;
  }

    /**
     * Retrieve images for given path.
     *
     * @param string $imagesPath  images directory
     * @param $imageTimestampFunction
     * @param string $folder
     */
  public function retrieve($imagesPath, $imageTimestampFunction, $folder = '')
  {

    $sortByName = function(\SplFileInfo $a, \SplFileInfo $b)
    {
      $filenameA = $a->getBasename();
      $filenameB = $b->getBasename();
      $cleanedFilenameA = substr(strstr(((strpos($filenameA, ',') === false) ? ',' : '') . $filenameA, ','), 1);
      $cleanedFilenameB = substr(strstr(((strpos($filenameB, ',') === false) ? ',' : '') . $filenameB, ','), 1);
      return strnatcmp($cleanedFilenameA, $cleanedFilenameB) * -1;
    };

    $finder = $this->getFinder();
    $finder->files()
      ->in($imagesPath) 
      ->path($folder)
      ->name('/\.jpg/')
      ->size('>0')
      ->sort($sortByName);

    $fileNames = array();
    $images = array();
    foreach($finder as $file)
    {
      $images[] = array(
        'thumbnailWidth'  => $this->getWidth(),
        'thumbnailHeight' => $this->getHeight(),
        'url'             => $file->getRelativePathname(),
        'internalUrl'     => str_replace('/', ',', $file->getRelativePathname()),
        'timestamp'       => $imageTimestampFunction($file),
      );
      $fileNames[] = $file->getRelativePathname();
    }

    $this->fileNames = $fileNames;
    $this->images = $images;

    $sideFinder = $this->getSideFinder();
    $sideFinder->files()
      ->in($imagesPath)
      ->path($folder)
      ->notName('/\.jpg/')
      ->sortByName();
    $sideFilenames = array();
    foreach($sideFinder as $sideFile) {
      $sideFilenames[] = $sideFile->getRelativePathname();
    }
    $this->sideFilenames = $sideFilenames;
  }

  public function getSubfolders($imagesPath) {

    $sortByLabel = function ($a, $b) { return strnatcasecmp($a['label'], $b['label']); };

    $finder = $this->getSubfolderFinder();
    $finder->directories()
        ->in($imagesPath)
        ->depth(0);

    $subfolders = array();
    foreach($finder as $folder) {

      $subfolderLabel = $folder->getBasename();
      $labelFile = $folder->getRealPath() . DIRECTORY_SEPARATOR . '.label';
      if(file_exists($labelFile)) {
        $subfolderLabel = file_get_contents($labelFile);
      }

      $subfolders[] = array(
        'path' => $folder->getRelativePathname(),
        'label' => $subfolderLabel
      );
    }

    usort($subfolders, $sortByLabel);

    return $subfolders;

  }

  /**
   * Return images array data.
   *
   * @return array
   */
  public function getImages()
  {
    return $this->images;
  }

  /**
   * Get number of images.
   *
   * @return int
   */
  public function getImagesCount()
  {
    return count($this->images);
  }

  /**
   * Return string of image file names.
   *
   * @return string
   */
  public function getFileNames()
  {
    $files = implode(',', $this->fileNames);

    return $files;
  }

  public function getSideFileNames()
  {
    $files = implode(',', $this->sideFilenames);

    return $files;
  }

  /**
   * Return timestamp of latest image.
   *
   * @return string
   */
  public function getLatestTimestamp()
  {
    $latestTimestamp = false;
    $images = $this->getImages();

    if (array_key_exists('0', $images))
    {
      $latestTimestamp = $images[0]['timestamp'];
    }

    return $latestTimestamp;
  }

    /**
     * Create thumbnail and return image path.
     *
     * @param string $file              image file name
     * @param string $imagesPath        image source directory
     * @param string $imagesArchivePath image archive directory
     * @param string $thumbnailsPath    thumbnail destination directory
     *
     * @throws \Exception
     * @return string image path
     */
  public function getThumbnail($file, $imagesPath, $imagesArchivePath, $thumbnailsPath)
  {
    $thumbnailsPath = realpath($thumbnailsPath);
    $fullImagePath = realpath($imagesPath . preg_replace('#,#', '/', $file, 1));

    // maybe $file is already archived but we have no thumbnail yet? try archive directory.
    if ($fullImagePath == false)
    {
      $fullImagePath = realpath($imagesArchivePath . $file);
    }

    if ($fullImagePath == false)
    {
      throw new \Exception("File not found." . $file);
    }

    // security check
    if (strpos($fullImagePath, realpath($imagesPath)) !== 0)
    {
      throw new \Exception("Access denied");
    }

    $image = new \Imagick($fullImagePath); // @FIXME: use DI for Imagick
    // $image->rotateImage(new ImagickPixel(), 270); // rotate image
    $image->thumbnailImage($this->getWidth(), $this->getHeight(), true);

    $outputImage = $thumbnailsPath . DIRECTORY_SEPARATOR . $file;
    $image->writeImage($outputImage);

    return $outputImage;
  }

  /**
   * Move images (to archive directory).
   *
   * @param array  $filenames       array of image file names
   * @param string $sourceDir       source directory
   * @param string $destinationDir  destination directory
   */
  public function moveToArchive($filenames, $sourceDir, $destinationDir)
  {
    $realSourcePath = realpath($sourceDir);

    foreach ($filenames as $filename)
    {
      $fullSourceImagePath = realpath($sourceDir . $filename);
      if (strpos($fullSourceImagePath, $realSourcePath) !== false)
      {
        $fullDestinationImagePath = $destinationDir . preg_replace('#/#', ',', $filename, 1);
        rename($fullSourceImagePath, $fullDestinationImagePath);
      }
    }
  }

    /**
     * Delete archived images that are older than 3 days.
     *
     * @param string $imagesPath      images directory
     * @param string $thumbnailsPath  thumbnails directory
     *
     * @param $numberOfDaysToKeepWhenCleaning
     * @return int number of removed images
     */
  public function cleanup($imagesPath, $thumbnailsPath, $numberOfDaysToKeepWhenCleaning)
  {
    $finder = $this->getFinder();
    $finder->files()
      ->in($imagesPath)
      ->depth(0)
      ->date('until -' . $numberOfDaysToKeepWhenCleaning . ' days');

    $count = count($finder);
      
    foreach($finder as $file)
    {
      $fullThumbnailPath = realpath($thumbnailsPath . $file->getRelativePathname());
      unlink($file->getRealpath());
      if ($fullThumbnailPath !== false)
      {
        unlink($fullThumbnailPath);
      }
    }

    return $count;
  }

}