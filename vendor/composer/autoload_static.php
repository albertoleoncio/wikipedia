<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitc3b9036af0d355be07e9c1219a58874b
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'Abraham\\TwitterOAuth\\' => 21,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Abraham\\TwitterOAuth\\' => 
        array (
            0 => __DIR__ . '/..' . '/abraham/twitteroauth/src',
        ),
    );

    public static $prefixesPsr0 = array (
        'R' => 
        array (
            'Requests' => 
            array (
                0 => __DIR__ . '/..' . '/rmccue/requests/library',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitc3b9036af0d355be07e9c1219a58874b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitc3b9036af0d355be07e9c1219a58874b::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitc3b9036af0d355be07e9c1219a58874b::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
