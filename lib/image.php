<?php

class naju_image
{
    public const ALLOWED_TYPES = 'jpg,jpeg,png';
    public const UNSUPPORTED_EXTENSIONS = ['svg', 'tif', 'tiff'];

    public const WEBP_FILENAME_PATTERN = '%s_%sw___webp.webp';
    public const WEBP_XXL_FILENAME_PATTERN = '%s___webp.webp';

    public static $WIDTH_BREAKPOINTS = [1000, 800, 400];
    public static $COMPRESSION_QUALITY_INIT = 100;
    public static $COMPRESSION_QUALITY_THRESH = 45;
    public static $COMPRESSION_QUALITY_DEC = 5;
    public static $COMPRESSION_IMPROVEMENT_RATIO = 0.8;

    public const ASPECT_RATIO_16_9 = 16 / 9;
    public const ASPECT_RATIO_4_3 = 4 / 3;
    public const ASPECT_RATIO_3_2 = 3 / 2;
    public const ASPECT_RATIO_1_1 = 1;

    public const ASPECT_RATIOS = array(self::ASPECT_RATIO_16_9, self::ASPECT_RATIO_4_3, self:: ASPECT_RATIO_3_2, self::ASPECT_RATIO_1_1);

    public static function supportedFile($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return rex_media::isImageType($extension) && !in_array($extension, self::UNSUPPORTED_EXTENSIONS);
    }

    public static function aspectRatioName($img)
    {
        $eps = 0.2;
        if (naju_float::eq(self::ASPECT_RATIO_16_9, $img->aspectRatio(), $eps)) {
            return '16:9';
        } elseif (naju_float::eq(self::ASPECT_RATIO_4_3, $img->aspectRatio(), $eps)) {
            return '4:3';
        } elseif (naju_float::eq(self::ASPECT_RATIO_3_2, $img->aspectRatio(), $eps)) {
            return '3:2';
        } elseif (naju_float::eq(self::ASPECT_RATIO_1_1, $img->aspectRatio(), $eps)) {
            return '1:1';
        } else {
            return null;
        }
    }

    public static function createOptimizedVersions($img_name, $reuse = true)
    {
        $logger = rex_logger::factory();

        $rex_img = rex_media::get($img_name);
        $filename = pathinfo($img_name, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
        $path = rex_path::media($img_name);
        switch ($extension) {
            case 'jpg':
                // fall through
            case 'jpeg':
                $image = imagecreatefromjpeg($path);
                break;
            case 'png':
                $image = imagecreatefrompng($path);
                break;
            case 'gif':
                $image = imagecreatefromgif($path);
                break;
            case 'bmp':
                $image = imagecreatefrombmp($path);
                break;
            default:
                $logger->log(E_WARNING, "Unsupported image type for image '$img_name'");
                return false;
        }

        if (!$image) {
            $logger->log(E_WARNING, "Could not load image '$img_name'");
            return false;
        }

        $failed_quality = 2 * self::$COMPRESSION_QUALITY_INIT;
        $created_quality = $failed_quality;

        $needs_xxl = $rex_img->getWidth() > max(self::$WIDTH_BREAKPOINTS);
        $needs_xxs = $rex_img->getWidth() < min(self::$WIDTH_BREAKPOINTS);
        if (($needs_xxl || $needs_xxs) && $extension !== 'webp') {
            $xxl_webp_name = sprintf(self::WEBP_XXL_FILENAME_PATTERN, $filename);
            $res = self::createWebp($img_name, $image, $rex_img, $xxl_webp_name, $reuse);
            if ($res) {
                $created_quality = $res;
            }
        }

        if ($needs_xxs && $res) {
            imagedestroy($image);
            return 'xxs;' . $res;
        } elseif ($needs_xxs) {
            imagedestroy($image);
            return false;
        }

        foreach (self::$WIDTH_BREAKPOINTS as $width) {

            // if the uploaded image is narrower than the breakpoint don't create optimized version
            if ($rex_img->getWidth() < $width) {
                continue;
            }

            $scaled = imagescale($image, $width);
            if (!$scaled) {
                $logger->log(E_WARNING, "Could not rescale image '$img_name' to width $width");
                continue;
            }

            $webp_name = sprintf(self::WEBP_FILENAME_PATTERN, $filename, $width);
            $res = self::createWebp($img_name, $scaled, $rex_img, $webp_name, $reuse);

            if ($res) {
                $created_quality = min($res, $created_quality);
            }
        }

        imagedestroy($image);
        return $created_quality == $failed_quality ? false : $created_quality;
    }

    public static function deleteOptimizedVersions($img_name)
    {
        $filename = pathinfo($img_name, PATHINFO_FILENAME);

        $xxl_webp_path = rex_path::media(sprintf(self::WEBP_XXL_FILENAME_PATTERN, $filename));
        @unlink($xxl_webp_path);
        foreach (self::$WIDTH_BREAKPOINTS as $width) {
            $webp_path = rex_path::media(sprintf(self::WEBP_FILENAME_PATTERN, $filename, $width));
            @unlink($webp_path);
        }
    }

    public static function updateOptimizedVersions($img_name)
    {
        self::deleteOptimizedVersions($img_name);
        $res = self::createOptimizedVersions($img_name, false);
        return $res;
    }

    public static function inflateOptimizedVersionsToMediapool($partitioning = null)
    {
        $sql = rex_sql::factory();
        $total = 0;
        $success = array();
        $fail = array();
        $skipped = array();

        if ($partitioning) {
            $media_count = $sql->setQuery('SELECT COUNT(*) AS count FROM ' . rex::getTable('media'))->getValue('count');
            $offset = $partitioning['offset'] ?? 0;
            $limit = $partitioning['limit'] ?? $media_count;
            $media_files = $sql->getArray('SELECT filename FROM ' . rex::getTable('media') . " ORDER BY filename LIMIT $limit OFFSET $offset");
        } else {
            $sql->setTable(rex::getTable('media'));
            $sql->select('filename');
            $media_files = $sql->getArray();
        }

        foreach ($media_files as $media) {
            $filename = $media['filename'];
            $total += 1;
            if (self::supportedFile($filename)) {
                $res = self::createOptimizedVersions($filename);
                if (str_contains($res, 'reuse') || $res === 'skipped') {
                    $skipped[] = $filename . ' (' . $res . ')';
                } elseif ($res) {
                    $success[] = $filename . ' (' . $res . ')';
                } else {
                    $fail[] = $filename;
                }
            } else {
                $skipped[] = $filename;
            }
        }
        return ['total' => $total, 'success' => $success, 'failure' => $fail, 'skipped' => $skipped];
    }

    public static function clearOptimizedVersionsFromMediapool()
    {
        set_time_limit(60 * 60);
        ignore_user_abort(true);
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('media'));
        $sql->select('filename');
        $media_files = $sql->getArray();

        foreach ($media_files as $media) {
            self::deleteOptimizedVersions($media['filename']);
        }
    }

    private static function createWebp($img_name, $image, $rex_img, $webp_name, $reuse)
    {
        $logger = rex_logger::factory();
        $webp_path = rex_path::media($webp_name);

        if ($reuse && file_exists($webp_path)) {
            return 'reuse';
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $quality = self::$COMPRESSION_QUALITY_INIT;
        $webp_created = imagewebp($image, $webp_path, $quality);
        if (!$webp_created) {
            $logger->log(E_WARNING, "Could not create WebP image '$webp_name' from image '$img_name'");
            return false;
        }

        // as long as the converted image is larger than the original one, reduce the quality and try again
        while ((filesize($webp_path) > ($rex_img->getSize() * self::$COMPRESSION_IMPROVEMENT_RATIO))
            && ($quality > self::$COMPRESSION_QUALITY_THRESH)) {
            $quality -= self::$COMPRESSION_QUALITY_DEC;

            @unlink($webp_path);
            $webp_created = imagewebp($image, $webp_path, $quality);

            // if an error occurred, don't try to create better converted images
            if (!$webp_created) {
                $logger->log(E_WARNING, "Could not create WebP image '$webp_name' from image '$img_name' with quality $quality");
                return false;
            }
        }

        // although compression was set to its maximum, the WebP image still is not better than the original one
        // give up
        if (filesize($webp_path) > $rex_img->getSize()) {
            @unlink($webp_path);
            return false;
        } else {
            return $quality;
        }
    }

    private static function attrsToStr($attrs)
    {
        $str = '';
        foreach ($attrs as $attr => $val) {
            $str .= $attr . '="' . rex_escape($val) . '" ';
        }
        return $str;
    }

    private $name;
    private $path;
    private $absoluteUrl;
    private $aspect_ratio;
    private $rex_media;

    public function __construct($name)
    {
        if (!$name) {
            throw new InvalidArgumentException('Empty image name');
        }

        $this->rex_media = rex_media::get($name);

        $this->path = rex_path::media($name);

        $this->name = $name;

        $server = substr(rex::getServer('https'), 0, -1);
        $this->absoluteUrl = $server . rex_url::media($name);

        if (!$this->rex_media) {
            throw new InvalidArgumentException('Image is not accessible: "' . $this->path . '"');
        }
    }

    public function name()
    {
        return $this->name;
    }

    public function url()
    {
        return rex_url::media($this->name);
    }

    public function path()
    {
        return $this->path;
    }

    public function absoluteUrl()
    {
        return $this->absoluteUrl;
    }

    public function media()
    {
        return $this->rex_media;
    }

    public function width()
    {
        return $this->rex_media->getWidth();
    }

    public function height()
    {
        return $this->rex_media->getHeight();
    }

    public function aspectRatio()
    {
        // compute the image's aspect ratio on demand
        if ($this->aspect_ratio) {
            return $this->aspect_ratio;
        }

        $exact_ratio = $this->rex_media->getWidth() / $this->rex_media->getHeight();
        $ratio_found = false;
        foreach (self::ASPECT_RATIOS as $ratio) {
            if (naju_float::eq($exact_ratio, $ratio, 0.2)) {
                $this->aspect_ratio = $ratio;
                $ratio_found = true;
                break;
            }
        }
        if (!$ratio_found) {
            $this->aspect_ratio = $exact_ratio;
        }

        return $this->aspect_ratio;
    }

    public function exactAspectRatio()
    {
        return $this->rex_media->getWidth() / $this->rex_media->getHeight();
    }

    public function altText()
    {
        $med_alt = $this->rex_media->getValue('med_alt');
        if ($med_alt) {
            return $med_alt;
        }
        $title = $this->rex_media->getTitle();
        if ($title) {
            return $title;
        }
        return $this->name();
    }

    public function optimizedName($prefer_small = false, $breakpoint = null)
    {
        $filename = pathinfo($this->name, PATHINFO_FILENAME);

        // if a breakpoint was explicitely specified, first try to fetch the corresponding media
        if ($breakpoint) {
            $break_file = sprintf(self::WEBP_FILENAME_PATTERN, $filename, $breakpoint);
            if (file_exists(rex_path::media($break_file))) {
                return $break_file;
            }
        }

        // if the media was not found, or no breakpoint was specified, try to detect
        // the appropriate source manually
        $is_xxl = $this->rex_media->getWidth() > max(self::$WIDTH_BREAKPOINTS);
        $is_xxs = $this->rex_media->getWidth() < min(self::$WIDTH_BREAKPOINTS);
        if ((!$prefer_small && $is_xxl) || $is_xxs) {
            $xxl_webp_name = sprintf(self::WEBP_XXL_FILENAME_PATTERN, $filename);
            if (file_exists(rex_path::media($xxl_webp_name))) {
                return $xxl_webp_name;
            }
        }

        if ($prefer_small) {
            $breakpoints = self::$WIDTH_BREAKPOINTS;
            sort($breakpoints);
        } else {
            $breakpoints = self::$WIDTH_BREAKPOINTS;
            rsort($breakpoints);
        }

        foreach ($breakpoints as $width) {
            $webp_name = sprintf(self::WEBP_FILENAME_PATTERN, $filename, $width);
            if (file_exists(rex_path::media($webp_name))) {
                return $webp_name;
            }
        }

        return '';
    }

    public function optimizedUrl($prefer_small = false, $breakpoint = null)
    {
        $opt_name = $this->optimizedName($prefer_small, $breakpoint);
        if ($opt_name) {
            return rex_url::media($opt_name);
        }
        return '';
    }

    public function author()
    {
        $med_author = $this->rex_media->getValue('med_author');
        if ($med_author) {
            return $med_author;
        }
        rex_logger::logError(E_USER_WARNING, 'Image ' . $this->name . ' has no author set', 'naju_image', 397);
        return 'NAJU Sachsen';
    }

    public function generateImgTag($classes = array(), $id = '', $attrs = [])
    {
        $attr_class = $classes ? ('class="' . rex_escape(implode(' ', $classes)) . '"') : '';
        $attr_id = $id ? ('id="' . rex_escape($id) . '"') : '';
        $additional_attrs = self::attrsToStr($attrs);
        return '<img src="' . $this->url() . '" alt="' . rex_escape($this->altText()) . '" ' . $attr_id . ' ' . $attr_class . ' ' . $additional_attrs . '>';
    }

    public function generatePictureTag($classes = array(), $id = '', $attrs = [], $source_attrs = [], $with_author = true)
    {
        $filename = pathinfo($this->path, PATHINFO_FILENAME);
        $webp_sources = array($this->url());

        // first up, check if XXL or XXS versions need to be present in the `srcset`
        $is_xxl = $this->rex_media->getWidth() > max(self::$WIDTH_BREAKPOINTS);
        $is_xxs = $this->rex_media->getWidth() < min(self::$WIDTH_BREAKPOINTS);
        if ($is_xxl || $is_xxs) {
            $xxl_webp_name = sprintf(self::WEBP_XXL_FILENAME_PATTERN, $filename);
            $xxl_path = rex_path::media($xxl_webp_name);
            if (file_exists($xxl_path)) {
                $xxl_info = getimagesize($xxl_path);
                if ($xxl_info) {
                    $webp_sources[] = rex_url::media($xxl_webp_name) . ' ' . $xxl_info[0] . 'w';
                }
            }
        }

        // secondly, collect all the avilable breakpoints
        foreach (self::$WIDTH_BREAKPOINTS as $width) {
            $webp_name = sprintf(self::WEBP_FILENAME_PATTERN, $filename, $width);
            if (file_exists(rex_path::media($webp_name))) {
                $webp_sources[] = rex_url::media($webp_name) . ' ' . $width . 'w';
            }
        }

        // thirdly, determine the `size` attribute to use in the <source>
        if (!array_key_exists('sizes', $source_attrs)) {
            if (array_key_exists('width', $attrs)) {
                $source_attrs['sizes'] = rex_escape($attrs['width']) . 'px';
            } elseif (array_key_exists('height', $attrs)) {
                $size_ratio = $attrs['height'] / $this->height();
                $width = ceil($this->width() * $size_ratio);
                $source_attrs['sizes'] = $width . 'px';
            } else {
                $source_attrs['sizes'] = '75vw';
            }
        }
        $source_attrs = self::attrsToStr($source_attrs);

        $tag = '';
        if ($with_author) {
            $tag .= '<figure class="clearfix">';
        }

        // finally, create the tag
        $tag .= '<picture>';
        $tag .= '   <source type="image/webp" srcset="' . implode(', ', $webp_sources) . '"' . $source_attrs . '>';
        $tag .=     $this->generateImgTag($classes, $id, $attrs);
        $tag .= '</picture>';

        if ($with_author) {
            $tag .= '<figcaption class="float-right mr-2">Foto: ' . rex_escape($this->author()) . '</figcaption>';
            $tag .= '</figure>';
        }

        return $tag;
    }

    public function __toString()
    {
        return $this->name;
    }

}
