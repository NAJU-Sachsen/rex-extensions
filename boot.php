<?php

if (rex::isBackend() && rex::getUser()) {

    // if an image file is larger than 1.5 MB, it may not be uploaded
    rex_extension::register('MEDIA_ADD', function($ep) {
        $file = $ep->getParam('file');
        $file_name = $ep->getParam('filename');
        $size = $file['size'];

        if (!naju_image::supportedFile($file_name)) {
            return 'Dateiformat nicht unterstützt' ;
        }

        if ($size > 1024 * 1024 * 1.5) { // 1024 (byte => KB) * 1024 (KB => MB) * 1.5 (MB => 1.5 MB)
            return 'Die Datei ist zu groß. Maximal erlaubt: 1,5 MB';
        }

        $original_filename = pathinfo($ep->getParam('old_filename'), PATHINFO_FILENAME);
        $existing_media_query = "SELECT originalname FROM rex_media WHERE originalname LIKE concat(:filename, '%')";
        $sql = rex_sql::factory()->setQuery($existing_media_query, ['filename' => $original_filename]);
        $existing_files = $sql->getArray();
        if ($existing_files) {
            $conflicts = array();
            foreach ($existing_files as $conflict) {
                $conflicts[] = $conflict['originalname'];
            }
            return 'Datei mit ähnlichem Namen existiert bereits, bitte neue Datei umbenennen: ' . implode(',', $conflicts);
        }

        return '';
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

    // set image properties that are only useful in the backend
    $img_init_quality = $this->getConfig('img_init_quality');
    if ($img_init_quality) {
        naju_image::$COMPRESSION_QUALITY_INIT = $img_init_quality;
    }

    $img_quality_thresh = $this->getConfig('img_quality_threshold');
    if ($img_quality_thresh) {
        naju_image::$COMPRESSION_QUALITY_THRESH = $img_quality_thresh;
    }

    $img_quality_intervals = $this->getConfig('img_quality_interval');
    if ($img_quality_intervals) {
        naju_image::$COMPRESSION_QUALITY_DEC = $img_quality_intervals;
    }

    $img_min_improvement = $this->getConfig('img_min_improvement');
    if ($img_min_improvement) {
        naju_image::$COMPRESSION_IMPROVEMENT_RATIO = $img_min_improvement;
    }

}

// set the image properties for backend and frontend
$img_breakpoints = $this->getConfig('img_breakpoints');
if ($img_breakpoints) {
    naju_image::$WIDTH_BREAKPOINTS = explode(';', $img_breakpoints);
}
