<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd617e33bc55bf057375568b5b97a0698
{
    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Mysyncsevdeskinvoices\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Mysyncsevdeskinvoices\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd617e33bc55bf057375568b5b97a0698::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd617e33bc55bf057375568b5b97a0698::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitd617e33bc55bf057375568b5b97a0698::$classMap;

        }, null, ClassLoader::class);
    }
}
