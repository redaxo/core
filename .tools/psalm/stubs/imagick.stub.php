<?php

/**
 * Workaround for Psalm crash on macOS with ext-imagick + PHP 8.5.
 *
 * Psalm fails with "Could not get class storage for imagickpixel" when
 * expanding parameter types of methods like Imagick::setImageBackgroundColor().
 * Stubbing the classes used in this codebase gives Psalm the class storage
 * it needs without depending on the (broken) reflection path.
 *
 * See: https://github.com/vimeo/psalm/issues/11794
 */

class ImagickPixel {}
