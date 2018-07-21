<?php

namespace Tests;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return todo
     */
    public static function createApplication()
    {
        if(!defined('INSTALLDIR')) {
            define('INSTALLDIR', __DIR__ . '/../../../');
        }
        if(!defined('GNUSOCIAL')) {
            define('GNUSOCIAL', true);
        }
        if(!defined('STATUSNET')) {
            define('STATUSNET', true);  // compatibility
        }

        require INSTALLDIR . '/lib/common.php';

        return true;

    }
}
