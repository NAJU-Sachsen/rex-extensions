<?php

if (rex::isBackend() && rex::getUser()) {

    // TODO: refuse image upload if it is too large

    // create optimized versions of uploaded images
    rex_extension::register('MEDIA_ADDED', function ($ep) {
        $file = $ep->getSubject()['filename'];
        if (naju_image::supportedFile($file)) {
            naju_image::createOptimizedVersions($file);
        }
    });

    // also update optimzed versions when base image is updated
    rex_extension::register('MEDIA_UPDATED', function ($ep) {
        $file = $ep->getSubject()['filename'];
        if (naju_image::supportedFile($file)) {
            naju_image::updateOptimizedVersions($file);
        }
    });

    // delete optimized versions if base image is deleted
    rex_extension::register('MEDIA_DELETED', function ($ep) {
        $file = $ep->getSubject()['filename'];
        if (naju_image::supportedFile($file)) {
            naju_image::deleteOptimizedVersions($file);
        }
    });

}
