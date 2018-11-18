<?php

namespace Vis\Builder\Helpers\Traits;

use Vis\Builder\Setting;
use Illuminate\Support\Facades\Config;

trait ImagesTrait
{
    /*
    *  get main picture this page
    * @param  string|integer $width
    * @param  string|integer $height
    * @return string tag img
    */
    public function getImg($width = '', $height = '', $options = [])
    {
        $img_res = $this->getImgPath($width, $height, $options);

        return  '<img src = "'.$img_res.'" title = "'.e($this->title).'" alt = "'.e($this->title).'">';
    }

    // end getImg

    public function getImgLang($width = '', $height = '', $options = [])
    {
        $img_res = $this->getImgPath($width, $height, $options, true);

        return  '<img src = "'.$img_res.'" title = "'.e($this->t('title')).'" alt = "'.e($this->t('title')).'">';
    }

    // end getImg

    public function getImgPath($width = '', $height = '', $options = [], $lang = false)
    {
        $picture = $this->picture;

        if (isset($options['image_title']) && $options['image_title']) {
            $name = $options['image_title'];
            $picture = $this->$name;
        }

        if ($lang) {
            $picture = $this->t('picture');
        }

        if (! $picture) {
            $picture = Setting::get('no-foto');
        }

        $size = [];
        if ($width) {
            $size['w'] = $width;
        }

        if ($height) {
            $size['h'] = $height;
        }

        $params = array_merge($size, $options);

        return  glide($picture, $params);
    }

    /*
     * get additional pictures this page
     * @param string $nameField field in bd
     * @param array $paramImg param width,height,fit
     * @return array list small images
     */
    public function getOtherImg($nameField = 'additional_pictures', $paramImg = '')
    {
        if (! $this->$nameField) {
            return;
        }

        $images = json_decode($this->$nameField);

        $imagesRes = [];
        foreach ($images as $imgOne) {
            if ($paramImg) {
                $imagesRes[] = glide($imgOne, $paramImg);
            } else {
                $imagesRes[] = '/'.$imgOne;
            }
        }

        return $imagesRes;
    }

    public function getOtherImgWatermark($nameField = 'additional_pictures', $paramImg = '')
    {
        if (! $this->$nameField) {
            return;
        }

        $images = json_decode($this->$nameField);

        $imagesRes = [];
        foreach ($images as $imgOne) {
            if ($paramImg) {
                if (config('builder.watermark.active') && $imgOne) {
                    $imagesRes[] = '/img/watermark/'.ltrim($imgOne, '/');
                } else {
                    $imagesRes[] = glide($imgOne, $paramImg);
                }
            } else {
                $imagesRes[] = '/'.$imgOne;
            }
        }

        return $imagesRes;
    }

    /**
     * get array additional pictures with original img in key.
     *
     * @param string $nameField
     * @param string $paramImg
     */
    public function getOtherImgWithOriginal($nameField = 'additional_pictures', $paramImg = '')
    {
        if (! $this->$nameField) {
            return;
        }

        $images = json_decode($this->$nameField);

        $imagesRes = [];
        foreach ($images as $imgOne) {
            if ($paramImg) {
                $imagesRes['/'.$imgOne] = glide($imgOne, $paramImg);
            } else {
                $imagesRes[] = '/'.$imgOne;
            }
        }

        return $imagesRes;
    }

    public function getWatermark($width = '', $height = '', $options = [])
    {
        if (config('builder.watermark.active') && $this->picture) {
            return '/img/watermark/'.ltrim($this->picture, '/');
        } else {
            return $this->getImgPath($width, $height, $options);
        }
    }
}
