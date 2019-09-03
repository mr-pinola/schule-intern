<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit796dd8e19cd8a98fd5a23894d9eef698
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
            'PhpZip\\' => 7,
        ),
        'I' => 
        array (
            'Ifsnop\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'PhpZip\\' => 
        array (
            0 => __DIR__ . '/..' . '/nelexa/zip/src/PhpZip',
        ),
        'Ifsnop\\' => 
        array (
            0 => __DIR__ . '/..' . '/ifsnop/mysqldump-php/src/Ifsnop',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit796dd8e19cd8a98fd5a23894d9eef698::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit796dd8e19cd8a98fd5a23894d9eef698::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
