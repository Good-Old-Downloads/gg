<?php
/*
    GOGGames
    Copyright (C) 2018  GoodOldDownloads

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class AppExtension extends \Twig_Extension {
    public function getFunctions(){
        return array(
            new \Twig_SimpleFunction('loadCSS', array($this, 'loadCSS'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('loadJS', array($this, 'loadJS'), array('is_safe' => array('html'))),
        );
    }

    public function getFilters(){
        return array(
            new \Twig_SimpleFilter('long2ip', array($this, 'long2ip')),
            new \Twig_SimpleFilter('convertBytes', array($this, 'convertBytes')),
        );
    }

    public function long2ip($int){
        return long2ip($int);
    }

    public function convertBytes($bytes, $decimals = 2){
        // Jeffrey Sambells
        // http://jeffreysambells.com/2012/10/25/human-readable-filesize-php
        $size = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) .' '. @$size[$factor];
    }

    public function loadCSS($styles, $integrity = null){
        $ret = array();
        if (is_array($styles)) {
            foreach ($styles as $key => $value) {
                if ($integrity !== null) {
                    if (!empty($integrity[$key])) {
                        $integStr = ' integrity="'.$integrity[$key].'"';
                    } else {
                        $integStr = '';
                    }
                }
                if (file_exists(__DIR__.'/web/'.$value)) {
                    $ret[] = '<link rel="stylesheet" href="'.$value.'?'.filemtime(__DIR__.'/web/'.$value).'"'.$integStr.'>';
                }
            }
        }
        return join($ret, "\n");
    }
    public function loadJS($scripts, $integrity = null){
        $ret = array();
        if (is_array($scripts)) {
            foreach ($scripts as $key => $value) {
                if ($integrity !== null) {
                    if (!empty($integrity[$key])) {
                        $integStr = ' integrity="'.$integrity[$key].'"';
                    } else {
                        $integStr = '';
                    }
                }
                if (file_exists(__DIR__.'/web/'.$value)) {
                    $ret[] = '<script src="'.$value.'?'.filemtime(__DIR__.'/web/'.$value).'"'.$integStr.'></script>';
                }
            }
        }
        return join($ret, "\n");
    }
}