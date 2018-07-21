<?php

namespace Tests;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return todo
     */
    public function createApplication()
    {
        define('GNUSOCIAL', true);
        define('STATUSNET', true);  // compatibility

        return require_once __DIR__. '/../../lib/common.php';

    }
}
