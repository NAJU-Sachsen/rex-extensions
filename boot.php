<?php

if (rex::isBackend() && rex::getUser()) {

    // if an image file is larger than 1.5 MB, it may not be uploaded
    rex_extension::register('MEDIA_ADD', function($ep) {
        $file = $ep->getParam('file');
        $file_name = $ep->getParam('filename');
        $size = $file['size'];

        if (naju_image::supportedFile($file_name) && $size > 1024 * 1024 * 1.5) { // 1024 (byte => KB) * 1024 (KB => MB) * 1.5 (MB => 1.5 MB)
            return "Die Datei ist zu groß. Maximal erlaubt: 1,5 MB";
        } else {
            return '';
        }
    });

    // create optimized versions of uploaded images
    rex_extension::register('MEDIA_ADDED', function ($ep) {
        $file = $ep->getParam('filename');
        if (naju_image::supportedFile($file)) {
            naju_image::createOptimizedVersions($file);
        }
    });

    // also update optimzed versions when base image is updated
    rex_extension::register('MEDIA_UPDATED', function ($ep) {
        $file = $ep->getParam('filename');
        if (naju_image::supportedFile($file)) {
            naju_image::updateOptimizedVersions($file);
        }
    });

    // delete optimized versions if base image is deleted
    rex_extension::register('MEDIA_DELETED', function ($ep) {
        $file = $ep->getParam('filename');
        if (naju_image::supportedFile($file)) {
            naju_image::deleteOptimizedVersions($file);
        }
    });

}
