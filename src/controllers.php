<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// home
$app->get('/', function (Request $request) use ($app) {

  $thumbnailService = $app['thumbnail_service'];
  $thumbnailService->retrieve($app['config']['imagesPath']);

  $data = array(
    'images'              => $thumbnailService->getImages(),
    'count'               => $thumbnailService->getImagesCount(),
    'latestTimestamp'     => $thumbnailService->getLatestTimestamp(),
    'files'               => $thumbnailService->getFileNames(),
    'hasLazyImageLoading' => $app['config']['lazyImageLoading'],
  );

  return $app['twig']->render('index.html', $data);
})
->bind('homepage');

// thumbnail
$app->get('/thumbnails/{file}', function ($file, Request $request) use ($app) {

  $imagesPath = $app['config']['imagesPath'];
  $imagesArchivePath = $app['config']['imagesArchivePath'];
  $thumbnailsPath = $app['config']['thumbnailsPath'];

  $thumbnailService = $app['thumbnail_service'];
  $image = $thumbnailService->getThumbnail($file, $imagesPath, $imagesArchivePath, $thumbnailsPath);

  return $app->sendFile($image, 200, ['Content-Type' => 'image/jpeg']);
})
->bind('thumbnail');

// archive images
$app->post('/archive/', function (Request $request) use ($app) {

  $sourceDir = $app['config']['imagesPath'];
  $destinationDir = $app['config']['imagesArchivePath'];

  $files = $request->get('files');
  $filenames = explode(',', $files);

  $thumbnailService = $app['thumbnail_service'];
  $thumbnailService->moveToArchive($filenames, $sourceDir, $destinationDir);

  return $app->redirect($app['url_generator']->generate('homepage')); 
})
->bind('archive-move');

// archive display
$app->get('/archive/', function (Request $request) use ($app) {

  $thumbnailService = $app['thumbnail_service'];
  $thumbnailService->retrieve($app['config']['imagesArchivePath']);

  $data = array(
    'images'              => $thumbnailService->getImages(),
    'count'               => $thumbnailService->getImagesCount(),
    'hasLazyImageLoading' => $app['config']['lazyImageLoading'],
  );

  return $app['twig']->render('archive.html', $data);
})
->bind('archive-display');

// cleanup archived images (cronjob)
$app->get('/cleanup/', function (Request $request) use ($app) {

  $archiveImagesPath = $app['config']['imagesArchivePath'];
  $thumbnailsPath = $app['config']['thumbnailsPath'];

  $thumbnailService = $app['thumbnail_service'];
  $filesCount = $thumbnailService->cleanup($archiveImagesPath, $thumbnailsPath);

  return new Response(sprintf('Finished. %s images deleted.', $filesCount));
})
->bind('cleanup');

// error handling
$app->error(function (\Exception $e, $code) use ($app) {
  if ($app['debug']) {
    return;
  }

  // 404.html, or 40x.html, or 4xx.html, or error.html
  $templates = array(
    'errors/'.$code.'.html',
    'errors/'.substr($code, 0, 2).'x.html',
    'errors/'.substr($code, 0, 1).'xx.html',
    'errors/default.html',
  );

  return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
