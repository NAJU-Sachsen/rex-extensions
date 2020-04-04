<?php

class naju_image
{
    public const ASPECT_RATIO_16_9 = 16 / 9;
    public const ASPECT_RATIO_4_3 = 4 / 3;
    public const ASPECT_RATIO_3_2 = 3 / 2;
    public const ASPECT_RATIO_1_1 = 1;

    public const ASPECT_RATIOS = array(self::ASPECT_RATIO_16_9, self::ASPECT_RATIO_4_3, self:: ASPECT_RATIO_3_2, self::ASPECT_RATIO_1_1);

    public static function imageMayBeUploaded($extension_point)
    {
        dump($extension_point);
        return false;
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

    private $name;
    private $path;
    private $absoluteUrl;
    private $width;
    private $height;
    private $aspect_ratio;

    public function __construct($name)
    {
        if (!$name) {
            throw new InvalidArgumentException('Empty image name');
        }

        $this->path = rex_path::media($name);

        $this->name = $name;

        $server = substr(rex::getServer('https'), 0, -1);
        $this->absoluteUrl = $server . rex_url::media($name);

        $image_size = getimagesize($this->path);

        if (!$image_size) {
            throw new InvalidArgumentException('Image is not accessible: "' . $this->path . '"');
        }

        $this->width = $image_size[0];
        $this->height = $image_size[1];

        $exact_ratio = $this->width / $this->height;
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
    }

    public function name()
    {
        return $this->name;
    }

    public function path()
    {
        return $this->path;
    }

    public function absoluteUrl()
    {
        return $this->absoluteUrl;
    }

    public function width()
    {
        return $this->width;
    }

    public function height()
    {
        return $this->height;
    }

    public function aspectRatio()
    {
        return $this->aspect_ratio;
    }

    public function exactAspectRatio()
    {
        return $this->width / $this->height;
    }

    public function __toString()
    {
        return $this->name;
    }

}
