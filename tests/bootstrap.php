<?php

require dirname(__DIR__).'/vendor/autoload.php';
<<<<<<< ours
=======

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
>>>>>>> theirs
