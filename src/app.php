<?php

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\Finder\Finder;
use MotionDetectionViewer\ImageThumbnailService;

$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new TwigServiceProvider(), array(
  'twig.path'    => array(__DIR__.'/../templates'),
  'twig.options' => array('cache' => __DIR__.'/../var/cache/twig'),
));
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
  return $twig;
}));
$app['thumbnail_service'] = function($app) {
  return new ImageThumbnailService($app['config']['thumbnailWidth'], $app['config']['thumbnailHeight'], new Finder, new Finder, new Finder);
};

$app['config'] = array(
  'numberOfDaysToKeepWhenCleaning' => 1,
  'imagesPath'        => __DIR__ . '/../web/images/',
  'imagesArchivePath' => __DIR__ . '/../web/images/archive/',
  'thumbnailsPath'    => __DIR__ . '/../web/thumbnails/',
  'thumbnailWidth'    => 200,
  'thumbnailHeight'   => 200,
  'imageTimestampFunction'    => function(\SplFileInfo $file) {
      $matches = array();
      $filename = $file->getBasename('.jpg');
      $cleanedFilename = substr(strstr(((strpos($filename, ',') === false) ? ',' : '') . $filename, ','), 1);
      preg_match('#^A(.{2})(.{2})(.{2})(.{2})(.{2})(.{2})#', $cleanedFilename, $matches);
      return sprintf('20%s-%s-%s %s:%s:%s', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
  },
  // 'imageTimestampFunction'    => function(\SplFileInfo $file) { return date('Y-m-d H:i:s', $file->getMTime()); },
  'lazyImageLoading'  => true,
);

return $app;
