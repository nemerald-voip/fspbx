<?php

namespace App\Models;

class Sounds
{
    public static $soundsPath = '/usr/share/freeswitch/sounds';

    public static function list($language = 'en', $dialect = 'us', $voice = 'callie') {
        //set default values
        if (!isset($language)) { $language = 'en'; }
        if (!isset($dialect)) { $dialect = 'us'; }
        if (!isset($voice)) { $voice = 'callie'; }

        //set the variables
        $dir = self::$soundsPath.'/'.$language.'/'.$dialect.'/'.$voice;
        $rate = '8000';
        $files = self::glob($dir.'/*/'.$rate, true);

        //loop through the languages
        foreach($files as $file) {
            $file = substr($file, strlen($dir)+1);
            $file = str_replace("/".$rate, "", $file);
            $array[] = $file;
        }

        //return the list of sounds
        return $array;
    }

    /**
     * Glob search for a list of files
     * @var string $dir			this is the directory to scan
     * @var boolean $recursive	get the sub directories
     */
    public static function glob($dir, $recursive) {
        if ($dir != '' || $dir != '/') {
            $tree = glob(rtrim($dir, '/') . '/*');
            if ($recursive) {
                if (is_array($tree)) {
                    foreach($tree as $file) {
                        if (is_dir($file)) {
                            if ($recursive == true) {
                                $files[] = self::glob($file, $recursive);
                            }
                        } elseif (is_file($file)) {
                            $files[] = $file;
                        }
                    }
                }
                else {
                    $files[] = $tree;
                }
            }
            else {
                $files[] = $tree;
            }
            return $files;
        }
    }
}
