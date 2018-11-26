/*! visualCaptcha - v0.0.8 - 2016-01-23
* http://visualcaptcha.net
* Copyright (c) 2016 emotionLoop; Licensed MIT 
*
* Modified by TwelveCharzz for GoodOldDownloads. (26/03/2018)
*  - Force no audio support.
*  - Removed refresh button.
*  - Changed "explanation" output.
*/

(function( root, factory ) {
    if ( typeof define === 'function' && define.amd ) {
        define( [], factory );
    } else {
        root.visualCaptcha = factory();
    }
}( this, function() {/**
 * @license almond 0.2.9 Copyright (c) 2011-2014, The Dojo Foundation All Rights Reserved.
 * Available via the MIT or new BSD license.
 * see: http://github.com/jrburke/almond for details
 */
//Going sloppy to avoid 'use strict' string cost, but strict practices should
//be followed.
/*jslint sloppy: true */
/*global setTimeout: false */

var requirejs, require, define;
(function (undef) {
    var main, req, makeMap, handlers,
        defined = {},
        waiting = {},
        config = {},
        defining = {},
        hasOwn = Object.prototype.hasOwnProperty,
        aps = [].slice,
        jsSuffixRegExp = /\.js$/;

    function hasProp(obj, prop) {
        return hasOwn.call(obj, prop);
    }

    /**
     * Given a relative module name, like ./something, normalize it to
     * a real name that can be mapped to a path.
     * @param {String} name the relative name
     * @param {String} baseName a real name that the name arg is relative
     * to.
     * @returns {String} normalized name
     */
    function normalize(name, baseName) {
        var nameParts, nameSegment, mapValue, foundMap, lastIndex,
            foundI, foundStarMap, starI, i, j, part,
            baseParts = baseName && baseName.split("/"),
            map = config.map,
            starMap = (map && map['*']) || {};

        //Adjust any relative paths.
        if (name && name.charAt(0) === ".") {
            //If have a base name, try to normalize against it,
            //otherwise, assume it is a top-level require that will
            //be relative to baseUrl in the end.
            if (baseName) {
                //Convert baseName to array, and lop off the last part,
                //so that . matches that "directory" and not name of the baseName's
                //module. For instance, baseName of "one/two/three", maps to
                //"one/two/three.js", but we want the directory, "one/two" for
                //this normalization.
                baseParts = baseParts.slice(0, baseParts.length - 1);
                name = name.split('/');
                lastIndex = name.length - 1;

                // Node .js allowance:
                if (config.nodeIdCompat && jsSuffixRegExp.test(name[lastIndex])) {
                    name[lastIndex] = name[lastIndex].replace(jsSuffixRegExp, '');
                }

                name = baseParts.concat(name);

                //start trimDots
                for (i = 0; i < name.length; i += 1) {
                    part = name[i];
                    if (part === ".") {
                        name.splice(i, 1);
                        i -= 1;
                    } else if (part === "..") {
                        if (i === 1 && (name[2] === '..' || name[0] === '..')) {
                            //End of the line. Keep at least one non-dot
                            //path segment at the front so it can be mapped
                            //correctly to disk. Otherwise, there is likely
                            //no path mapping for a path starting with '..'.
                            //This can still fail, but catches the most reasonable
                            //uses of ..
                            break;
                        } else if (i > 0) {
                            name.splice(i - 1, 2);
                            i -= 2;
                        }
                    }
                }
                //end trimDots

                name = name.join("/");
            } else if (name.indexOf('./') === 0) {
                // No baseName, so this is ID is resolved relative
                // to baseUrl, pull off the leading dot.
                name = name.substring(2);
            }
        }

        //Apply map config if available.
        if ((baseParts || starMap) && map) {
            nameParts = name.split('/');

            for (i = nameParts.length; i > 0; i -= 1) {
                nameSegment = nameParts.slice(0, i).join("/");

                if (baseParts) {
                    //Find the longest baseName segment match in the config.
                    //So, do joins on the biggest to smallest lengths of baseParts.
                    for (j = baseParts.length; j > 0; j -= 1) {
                        mapValue = map[baseParts.slice(0, j).join('/')];

                        //baseName segment has  config, find if it has one for
                        //this name.
                        if (mapValue) {
                            mapValue = mapValue[nameSegment];
                            if (mapValue) {
                                //Match, update name to the new value.
                                foundMap = mapValue;
                                foundI = i;
                                break;
                            }
                        }
                    }
                }

                if (foundMap) {
                    break;
                }

                //Check for a star map match, but just hold on to it,
                //if there is a shorter segment match later in a matching
                //config, then favor over this star map.
                if (!foundStarMap && starMap && starMap[nameSegment]) {
                    foundStarMap = starMap[nameSegment];
                    starI = i;
                }
            }

            if (!foundMap && foundStarMap) {
                foundMap = foundStarMap;
                foundI = starI;
            }

            if (foundMap) {
                nameParts.splice(0, foundI, foundMap);
                name = nameParts.join('/');
            }
        }

        return name;
    }

    function makeRequire(relName, forceSync) {
        return function () {
            //A version of a require function that passes a moduleName
            //value for items that may need to
            //look up paths relative to the moduleName
            return req.apply(undef, aps.call(arguments, 0).concat([relName, forceSync]));
        };
    }

    function makeNormalize(relName) {
        return function (name) {
            return normalize(name, relName);
        };
    }

    function makeLoad(depName) {
        return function (value) {
            defined[depName] = value;
        };
    }

    function callDep(name) {
        if (hasProp(waiting, name)) {
            var args = waiting[name];
            delete waiting[name];
            defining[name] = true;
            main.apply(undef, args);
        }

        if (!hasProp(defined, name) && !hasProp(defining, name)) {
            throw new Error('No ' + name);
        }
        return defined[name];
    }

    //Turns a plugin!resource to [plugin, resource]
    //with the plugin being undefined if the name
    //did not have a plugin prefix.
    function splitPrefix(name) {
        var prefix,
            index = name ? name.indexOf('!') : -1;
        if (index > -1) {
            prefix = name.substring(0, index);
            name = name.substring(index + 1, name.length);
        }
        return [prefix, name];
    }

    /**
     * Makes a name map, normalizing the name, and using a plugin
     * for normalization if necessary. Grabs a ref to plugin
     * too, as an optimization.
     */
    makeMap = function (name, relName) {
        var plugin,
            parts = splitPrefix(name),
            prefix = parts[0];

        name = parts[1];

        if (prefix) {
            prefix = normalize(prefix, relName);
            plugin = callDep(prefix);
        }

        //Normalize according
        if (prefix) {
            if (plugin && plugin.normalize) {
                name = plugin.normalize(name, makeNormalize(relName));
            } else {
                name = normalize(name, relName);
            }
        } else {
            name = normalize(name, relName);
            parts = splitPrefix(name);
            prefix = parts[0];
            name = parts[1];
            if (prefix) {
                plugin = callDep(prefix);
            }
        }

        //Using ridiculous property names for space reasons
        return {
            f: prefix ? prefix + '!' + name : name, //fullName
            n: name,
            pr: prefix,
            p: plugin
        };
    };

    function makeConfig(name) {
        return function () {
            return (config && config.config && config.config[name]) || {};
        };
    }

    handlers = {
        require: function (name) {
            return makeRequire(name);
        },
        exports: function (name) {
            var e = defined[name];
            if (typeof e !== 'undefined') {
                return e;
            } else {
                return (defined[name] = {});
            }
        },
        module: function (name) {
            return {
                id: name,
                uri: '',
                exports: defined[name],
                config: makeConfig(name)
            };
        }
    };

    main = function (name, deps, callback, relName) {
        var cjsModule, depName, ret, map, i,
            args = [],
            callbackType = typeof callback,
            usingExports;

        //Use name if no relName
        relName = relName || name;

        //Call the callback to define the module, if necessary.
        if (callbackType === 'undefined' || callbackType === 'function') {
            //Pull out the defined dependencies and pass the ordered
            //values to the callback.
            //Default to [require, exports, module] if no deps
            deps = !deps.length && callback.length ? ['require', 'exports', 'module'] : deps;
            for (i = 0; i < deps.length; i += 1) {
                map = makeMap(deps[i], relName);
                depName = map.f;

                //Fast path CommonJS standard dependencies.
                if (depName === "require") {
                    args[i] = handlers.require(name);
                } else if (depName === "exports") {
                    //CommonJS module spec 1.1
                    args[i] = handlers.exports(name);
                    usingExports = true;
                } else if (depName === "module") {
                    //CommonJS module spec 1.1
                    cjsModule = args[i] = handlers.module(name);
                } else if (hasProp(defined, depName) ||
                           hasProp(waiting, depName) ||
                           hasProp(defining, depName)) {
                    args[i] = callDep(depName);
                } else if (map.p) {
                    map.p.load(map.n, makeRequire(relName, true), makeLoad(depName), {});
                    args[i] = defined[depName];
                } else {
                    throw new Error(name + ' missing ' + depName);
                }
            }

            ret = callback ? callback.apply(defined[name], args) : undefined;

            if (name) {
                //If setting exports via "module" is in play,
                //favor that over return value and exports. After that,
                //favor a non-undefined return value over exports use.
                if (cjsModule && cjsModule.exports !== undef &&
                        cjsModule.exports !== defined[name]) {
                    defined[name] = cjsModule.exports;
                } else if (ret !== undef || !usingExports) {
                    //Use the return value from the function.
                    defined[name] = ret;
                }
            }
        } else if (name) {
            //May just be an object definition for the module. Only
            //worry about defining if have a module name.
            defined[name] = callback;
        }
    };

    requirejs = require = req = function (deps, callback, relName, forceSync, alt) {
        if (typeof deps === "string") {
            if (handlers[deps]) {
                //callback in this case is really relName
                return handlers[deps](callback);
            }
            //Just return the module wanted. In this scenario, the
            //deps arg is the module name, and second arg (if passed)
            //is just the relName.
            //Normalize module name, if it contains . or ..
            return callDep(makeMap(deps, callback).f);
        } else if (!deps.splice) {
            //deps is a config object, not an array.
            config = deps;
            if (config.deps) {
                req(config.deps, config.callback);
            }
            if (!callback) {
                return;
            }

            if (callback.splice) {
                //callback is an array, which means it is a dependency list.
                //Adjust args if there are dependencies
                deps = callback;
                callback = relName;
                relName = null;
            } else {
                deps = undef;
            }
        }

        //Support require(['a'])
        callback = callback || function () {};

        //If relName is a function, it is an errback handler,
        //so remove it.
        if (typeof relName === 'function') {
            relName = forceSync;
            forceSync = alt;
        }

        //Simulate async callback;
        if (forceSync) {
            main(undef, deps, callback, relName);
        } else {
            //Using a non-zero value because of concern for what old browsers
            //do, and latest browsers "upgrade" to 4 if lower value is used:
            //http://www.whatwg.org/specs/web-apps/current-work/multipage/timers.html#dom-windowtimers-settimeout:
            //If want a value immediately, use require('id') instead -- something
            //that works in almond on the global level, but not guaranteed and
            //unlikely to work in other AMD implementations.
            setTimeout(function () {
                main(undef, deps, callback, relName);
            }, 4);
        }

        return req;
    };

    /**
     * Just drops the config on the floor, but returns req in case
     * the config return value is used.
     */
    req.config = function (cfg) {
        return req(cfg);
    };

    /**
     * Expose module registry for debugging and tooling
     */
    requirejs._defined = defined;

    define = function (name, deps, callback) {

        //This module may not have dependencies
        if (!deps.splice) {
            //deps is not an array, so probably means
            //an object literal or factory function for
            //the value. Adjust args.
            callback = deps;
            deps = [];
        }

        if (!hasProp(defined, name) && !hasProp(waiting, name)) {
            waiting[name] = [name, deps, callback];
        }
    };

    define.amd = {
        jQuery: true
    };
}());

define("almond", function(){});

/*global define */

define( 'visualcaptcha/core',[],function() {
    'use strict';

    var _addUrlParams,
        _refresh,
        _startUrl,
        _imageUrl,
        _audioUrl,
        _imageValue,
        _isRetina,
        _supportsAudio;

    _addUrlParams = function( config, url, params ) {
        params = params || [];

        if ( config.namespace && config.namespace.length > 0 ) {
            params.push( config.namespaceFieldName + '=' + config.namespace );
        }

        params.push( config.randomParam + '=' + config.randomNonce );

        return url + '?' + params.join( '&' );
    };

    _refresh = function( config ) {
        var core = this,
            startURL;

        // Set loading state
        config.applyRandomNonce();
        config.isLoading = true;

        // URL must be loaded after nonce is applied
        startURL = _startUrl( config );

        config._loading( core );

        if ( config.callbacks.loading ) {
            config.callbacks.loading( core );
        }

        config.request( startURL, function( response ) {
            // We need now to set the image and audio field names
            if ( response.audioFieldName ) {
                config.audioFieldName = response.audioFieldName;
            }

            if ( response.imageFieldName ) {
                config.imageFieldName = response.imageFieldName;
            }

            // Set the correct image name
            if ( response.imageName ) {
                config.imageName = response.imageName;
            }

            // Set the correct image values
            if ( response.values ) {
                config.imageValues = response.values;
            }

            // Set loaded state
            config.isLoading = false;
            config.hasLoaded = true;

            config._loaded( core );

            if ( config.callbacks.loaded ) {
                config.callbacks.loaded( core );
            }
        } );
    };

    _startUrl = function( config ) {
        var url = config.url + config.routes.start + '/' + config.numberOfImages;

        return _addUrlParams( config, url );
    };

    _imageUrl = function( config, i ) {
        var url = '',
            params = [];

        // Is the image index valid?
        if ( i < 0 || i >= config.numberOfImages ) {
            return url;
        }

        // If retina is required, add url param
        if ( this.isRetina() ) {
            params.push( 'retina=1' );
        }

        url = config.url + config.routes.image + '/' + i;

        return _addUrlParams( config, url, params );
    };

    _audioUrl = function( config, ogg ) {
        var url = config.url + config.routes.audio;

        if ( ogg ) {
            url += '/ogg';
        }

        return _addUrlParams( config, url );
    };

    _imageValue = function( config, i ) {
        if ( i >= 0 && i < config.numberOfImages ) {
            return config.imageValues[ i ];
        }

        return '';
    };

    //
    // Check for device/browser capabilities
    //
    _isRetina = function() {
      // Check if the device is retina-like
      return ( window.devicePixelRatio !== undefined && window.devicePixelRatio > 1 );
    };

    // Check if the device supports the HTML5 audio element, for accessibility
    // I'm using an IIFE just because I don't want audioElement to be in the rest of the scope
    _supportsAudio = function() {
/*        var audioElement,
            support = false;

        try {
            audioElement = document.createElement( 'audio' );
            if ( audioElement.canPlayType ) {
                support = true;
            }
        } catch( e ) {}

        return support;*/
        return false;
    };

    return function( config ) {
        var core,
            refresh,
            isLoading,
            hasLoaded,
            numberOfImages,
            imageName,
            imageValue,
            imageUrl,
            audioUrl,
            imageFieldName,
            audioFieldName,
            namespace,
            namespaceFieldName;

        refresh = function() {
            return _refresh.call( this, config );
        };

        isLoading = function() {
            return config.isLoading;
        };

        hasLoaded = function() {
            return config.hasLoaded;
        };

        numberOfImages = function() {
            return config.imageValues.length;
        };

        imageName = function() {
            return config.imageName;
        };

        imageValue = function( index ) {
            return _imageValue.call( this, config, index );
        };

        imageUrl = function( index ) {
            return _imageUrl.call( this, config, index );
        };

        audioUrl = function( ogg ) {
            return _audioUrl.call( this, config, ogg );
        };

        imageFieldName = function() {
            return config.imageFieldName;
        };

        audioFieldName = function() {
            return config.audioFieldName;
        };

        namespace = function() {
            return config.namespace;
        };

        namespaceFieldName = function() {
            return config.namespaceFieldName;
        };

        core = {
            refresh: refresh,
            isLoading: isLoading,
            hasLoaded: hasLoaded,
            numberOfImages: numberOfImages,
            imageName: imageName,
            imageValue: imageValue,
            imageUrl: imageUrl,
            audioUrl: audioUrl,
            imageFieldName: imageFieldName,
            audioFieldName: audioFieldName,
            namespace: namespace,
            namespaceFieldName: namespaceFieldName,
            isRetina: _isRetina,
            supportsAudio: _supportsAudio
        };

        // Load the data if auto refresh is enabled
        if ( config.autoRefresh ) {
            core.refresh();
        }

        return core;
    };
} );
/*global define */

define( 'visualcaptcha/xhr-request',[],function() {
    'use strict';

    var XMLHttpRequest = window.XMLHttpRequest;

    return function( url, callback ) {
        var ajaxRequest = new XMLHttpRequest();

        ajaxRequest.open( 'GET', url, true );
        ajaxRequest.onreadystatechange = function() {
            var response;

            if ( ajaxRequest.readyState !== 4 || ajaxRequest.status !== 200 ) {
                return;
            }

            response = JSON.parse( ajaxRequest.responseText );
            callback( response );
        };

        ajaxRequest.send();
    };
} );
/*global define */

define('visualcaptcha/config',[ 'visualcaptcha/xhr-request' ], function( xhrRequest ) {
    'use strict';

    return function( options ) {
        var urlArray = window.location.href.split( '/' );
        urlArray[urlArray.length-1]='';

        var config = {
            /* REQUEST */
            request: xhrRequest,
            url: urlArray.join( '/' ).slice(0, -1),
            namespace: '',
            namespaceFieldName: 'namespace',
            routes: {
                start: '/start',
                image: '/image',
                audio: '/audio'
            },
            isLoading: false,
            hasLoaded: false,
            /* STATE */
            autoRefresh: true,
            numberOfImages: 6,
            randomNonce: '',
            randomParam: 'r',
            audioFieldName: '',
            imageFieldName: '',
            imageName: '',
            imageValues: [],
            /* CALLBACKS */
            callbacks: {},
            _loading: function() {},
            _loaded: function() {}
        };

        // Update and return the random nonce
        config.applyRandomNonce = function() {
            return ( config.randomNonce = Math.random().toString( 36 ).substring( 2 ) );
        };

        // We don't want to extend config, just allow setting a few of its options
        if ( options.request ) {
            config.request = options.request;
        }

        if ( options.url ) {
            config.url = options.url;
        }

        if ( options.namespace ) {
            config.namespace = options.namespace;
        }

        if ( options.namespaceFieldName ) {
            config.namespaceFieldName = options.namespaceFieldName;
        }

        if ( typeof options.autoRefresh !== 'undefined' ) {
            config.autoRefresh = options.autoRefresh;
        }

        if ( options.numberOfImages ) {
            config.numberOfImages = options.numberOfImages;
        }

        if ( options.routes ) {
            if ( options.routes.start ) {
                config.routes.start = options.routes.start;
            }

            if ( options.routes.image ) {
                config.routes.image = options.routes.image;
            }

            if ( options.routes.audio ) {
                config.routes.audio = options.routes.audio;
            }
        }

        if ( options.randomParam ) {
            config.randomParam = options.randomParam;
        }

        if ( options.callbacks ) {
            if ( options.callbacks.loading ) {
                config.callbacks.loading = options.callbacks.loading;
            }

            if ( options.callbacks.loaded ) {
                config.callbacks.loaded = options.callbacks.loaded;
            }
        }

        if ( options._loading ) {
          config._loading = options._loading;
        }

        if ( options._loaded ) {
          config._loaded = options._loaded;
        }

        return config;
    };
} );
/*global define */

define( 'visualcaptcha',['require','visualcaptcha/core','visualcaptcha/config'],function( require ) {
    'use strict';

    var core = require( 'visualcaptcha/core' ),
        config = require( 'visualcaptcha/config' );

    return function( options ) {
        options = options || {};

        return core( config( options ) );
    };
} );
/*global define */

define( 'visualcaptcha/deep-extend',[],function() {
    'use strict';

    var _deepExtend;

    //
    // Credits: http://andrewdupont.net/2009/08/28/deep-extending-objects-in-javascript/
    //
    _deepExtend = function( dest, src ) {
        dest = dest || {};
        
        for ( var key in src ) {
            if ( src[ key ] &&
                src[ key ].constructor &&
                src[ key ].constructor === Object ) {
                dest[ key ] = dest[ key ] || {};
                _deepExtend( dest[ key ], src[ key ] );
            } else {
                dest[ key ] = src[ key ];
            }
        }

        return dest;
    };

    return _deepExtend;
} );
/*global define */

define( 'visualcaptcha/helpers',[],function() {
    'use strict';

    var _firstOrArray,
        _findByClass,
        _findByTag,
        _hasClass,
        _addClass,
        _removeClass,
        _bindClick;

    _firstOrArray = function( items, first ) {
        return first ? items[ 0 ] : Array.prototype.slice.call( items );
    };

    _findByClass = function( element, className, first ) {
        var elements = element.getElementsByClassName( className );

        return _firstOrArray( elements, first );
    };

    _findByTag = function( element, tagName, first ) {
        var elements = element.getElementsByTagName( tagName );

        return _firstOrArray( elements, first );
    };

    _hasClass = function( element, cls ) {
        var reg = new RegExp( "(\\s|^)" + cls + "(\\s|$)" );

        return element.className && reg.test( element.className );
    };

    _addClass = function( element, cls ) {
        if ( Array.isArray( element ) ) {
            for ( var i = 0; i < element.length; i++ ) {
                _addClass( element[ i ], cls );
            }
        } else {
            if ( !_hasClass( element, cls ) ) {
                if ( element.className.length > 0 ) {
                    element.className += ' ' + cls;
                } else {
                    element.className = cls;
                }
            }
        }
    };

    _removeClass = function( element, cls ) {
        var reg;

        if ( Array.isArray( element ) ) {
            for ( var i = 0; i < element.length; i++ ) {
                _removeClass( element[ i ], cls );
            }
        } else {
            reg = new RegExp( "(\\s|^)" + cls + "(\\s|$)" );

            element.className = element.className
                .replace( reg, " " )
                .replace( /(^\s*)|(\s*$)/g, "" );
        }
    };

    _bindClick = function( element, callback ) {
        if ( Array.isArray( element ) ) {
            for ( var i = 0; i < element.length; i++ ) {
                _bindClick( element[ i ], callback );
            }
        } else {
            if ( element.addEventListener ) {
                element.addEventListener( 'click', callback, false );
            } else {
                element.attachEvent( 'onclick', callback );
            }
        }
    };

    return {
        findByClass: _findByClass,
        findByTag: _findByTag,
        hasClass: _hasClass,
        addClass: _addClass,
        removeClass: _removeClass,
        bindClick: _bindClick
    };
} );
/*global define */

define( 'visualcaptcha/templates',[],function() {
    'use strict';

    var _t,
        _buttonsHTML,
        _accessibilityHTML,
        _imagesHTML,
        _audioInputHTML,
        _imageInputHTML,
        _namespaceInputHTML;

    // Template engine
    _t = function( str, d ) {
        for ( var p in d ) {
            str = str.replace( new RegExp( '{' + p + '}', 'g' ), d[ p ] );
        }

        return str;
    };

    // Generate refresh and accessibility buttons HTML
    _buttonsHTML = function( captcha, language, path ) {
        var btnAccessibility,
            btnRefresh,
            string,
            params;

         btnAccessibility =
            '<div class="visualCaptcha-accessibility-button">' +
                '<a href="#"><img src="{path}accessibility{retinaExtra}.png" title="{accessibilityTitle}" alt="{accessibilityAlt}" /></a>' +
            '</div>';

        btnRefresh =
            '<div class="visualCaptcha-refresh-button">' +
                '<a class="btn blue" href="#" title="{refreshTitle}" alt="{refreshAlt}"><i class="fas fa-sync"></i></a>' +
            '</div>';

        string = '';
/*        string =
            '<div class="visualCaptcha-button-group">' +
                btnRefresh +
                ( captcha.supportsAudio() ? btnAccessibility : '' ) +
            '</div>';*/

        params = {
            path: path || '',
            refreshTitle: language.refreshTitle,
            refreshAlt: language.refreshAlt,
            accessibilityTitle: language.accessibilityTitle,
            accessibilityAlt: language.accessibilityAlt,
            retinaExtra: captcha.isRetina() ? '@2x' : ''
        };

        return _t( string, params );
    };

    // Generate accessibility option and audio element HTML
    _accessibilityHTML = function( captcha, language ) {
        var string,
            params;

        if ( !captcha.supportsAudio() ) {
            return '';
        }

        string =
            '<div class="visualCaptcha-accessibility-wrapper visualCaptcha-hide">' +
                '<div class="accessibility-description">{accessibilityDescription}</div>' +
                '<audio preload="preload">' +
                    '<source src="{audioURL}" type="audio/ogg" />' +
                    '<source src="{audioURL}" type="audio/mpeg" />' +
                '</audio>' +
            '</div>';

        params = {
            accessibilityDescription: language.accessibilityDescription,
            audioURL: captcha.audioUrl(),
            audioFieldName: captcha.audioFieldName()
        };

        return _t( string, params );
    };

    // Generate images HTML
    _imagesHTML = function( captcha, language ) {
        var images = '',
            string,
            params;

        for ( var i = 0, l = captcha.numberOfImages(); i < l; i++ ) {
            string =
                '<div class="img">' +
                    '<a href="#"><img src="{imageUrl}" id="visualCaptcha-img-{i}" data-index="{i}" alt="" title="" /></a>' +
                '</div>';

            params = {
                imageUrl: captcha.imageUrl( i ),
                i: i
            };

            images += _t( string, params );
        }

        string =
            '<p class="visualCaptcha-explanation">{explanation}</p>' +
            '<div class="visualCaptcha-possibilities">{images}</div>';

        params = {
            imageFieldName: captcha.imageFieldName(),
            explanation: language.explanation.replace( /ANSWER/, captcha.imageName() ),
            images: images
        };

        return _t( string, params );
    };

    _audioInputHTML = function( captcha ) {
        var string,
            params;

        string =
            '<input class="form-control audioField" type="text" name="{audioFieldName}" value="" autocomplete="off" />';

        params = {
            audioFieldName: captcha.audioFieldName()
        };

        return _t( string, params );
    };

    _imageInputHTML = function( captcha, imageIndex ) {
        var string,
            params;

        string =
            '<input class="form-control imageField" type="hidden" name="{imageFieldName}" value="{value}" readonly="readonly" />';

        params = {
            imageFieldName: captcha.imageFieldName(),
            value: captcha.imageValue( imageIndex )
        };

        return _t( string, params );
    };

    _namespaceInputHTML = function( captcha ) {
        var string,
            params,
            namespace = captcha.namespace();

        // Ensure namespace is present
        if ( !namespace || namespace.length === 0 ) {
            return '';
        }

        string =
            '<input type="hidden" name="{fieldName}" value="{value}" />';

        params = {
            fieldName: captcha.namespaceFieldName(),
            value: namespace
        };

        return _t( string, params );
    };

    return {
        buttons: _buttonsHTML,
        accessibility: _accessibilityHTML,
        images: _imagesHTML,
        audioInput: _audioInputHTML,
        imageInput: _imageInputHTML,
        namespaceInput: _namespaceInputHTML
    };
} );
/*global define */

define( 'visualcaptcha/language',[],function() {
    'use strict';

    return {
        accessibilityAlt: 'Sound icon',
        accessibilityTitle: 'Accessibility option: listen to a question and answer it!',
        accessibilityDescription: 'Type below the <strong>answer</strong> to what you hear. Numbers or words:',
        explanation: '<strong>ANSWER</strong>',
        refreshAlt: 'Refresh/reload icon',
        refreshTitle: 'Refresh/reload images!'
    };
} );
/*global define */

define( 'visualcaptcha.vanilla',[
    'visualcaptcha',
    'visualcaptcha/deep-extend',
    'visualcaptcha/helpers',
    'visualcaptcha/templates',
    'visualcaptcha/language'
], function( visualCaptcha, deepExtend, helpers, templates, language ) {
    'use strict';

    var _loading,
        _loaded,
        _toggleAccessibility,
        _chooseImage,
        _refreshCaptcha,
        _getCaptchaData;

    // callback on loading
    _loading = function() {};

    // callback on loaded
    _loaded = function( element, captcha ) {
        var config = element.config,
            captchaHTML,
            selected;

        captchaHTML =
            // Add namespace input, if present
            templates.namespaceInput( captcha ) +
            // Add audio element, if supported
            templates.accessibility( captcha, config.language ) +
            // Add image elements
            templates.images( captcha, config.language ) +
            // Add refresh and accessibility buttons
            templates.buttons( captcha, config.language, config.imgPath );

        // Actually add the HTML
        element.innerHTML = captchaHTML;

        // Bind accessibility button
/*        selected = helpers.findByClass( element, 'visualCaptcha-accessibility-button', true );
        helpers.bindClick( selected, _toggleAccessibility.bind( null, element, captcha ) );*/

        // Bind refresh button
        //selected = helpers.findByClass( element, 'visualCaptcha-refresh-button', true );
        //helpers.bindClick( selected, _refreshCaptcha.bind( null, element, captcha ) );

        // Bind images
        selected = helpers.findByClass( element, 'visualCaptcha-possibilities', true );
        helpers.bindClick( helpers.findByClass( selected, 'img' ), _chooseImage.bind( null, element, captcha ) );
    };

    // Toggle accessibility option
    _toggleAccessibility = function( element, captcha ) {
        var accessibilityWrapper = helpers.findByClass( element, 'visualCaptcha-accessibility-wrapper', true ),
            possibilitiesWrapper = helpers.findByClass( element, 'visualCaptcha-possibilities', true ),
            explanation = helpers.findByClass( element, 'visualCaptcha-explanation', true ),
            audio = helpers.findByTag( accessibilityWrapper, 'audio', true ),
            images,
            imageInput,
            audioInput,
            audioInputHTML;

        if ( helpers.hasClass( accessibilityWrapper, 'visualCaptcha-hide' ) ) {
            // Hide images and explanation
            helpers.addClass( possibilitiesWrapper, 'visualCaptcha-hide' );
            helpers.addClass( explanation, 'visualCaptcha-hide' );

            // Reset selected images and input value
            images = helpers.findByClass( possibilitiesWrapper, 'img' );
            helpers.removeClass( images, 'visualCaptcha-selected' );

            imageInput = helpers.findByTag( explanation, 'input', true );
            if ( imageInput !== undefined ) {
                imageInput.value = '';
            }

            // Build the input HTML
            audioInputHTML = templates.audioInput( captcha );

            // Add the input before the audio element
            accessibilityWrapper.innerHTML = accessibilityWrapper.innerHTML.replace( '<audio', audioInputHTML + '<audio' );

            // Show the accessibility wrapper
            helpers.removeClass( accessibilityWrapper, 'visualCaptcha-hide' );

            // Play the audio
            audio.load();
            audio.play();
        } else {
            // Stop audio, delete input element, show images
            audio.pause();

            // Hide the accessibility wrapper
            helpers.addClass( accessibilityWrapper, 'visualCaptcha-hide' );

            // Delete the input element
            audioInput = helpers.findByTag( accessibilityWrapper, 'input', true );
            accessibilityWrapper.removeChild( audioInput );

            // Show images and explanation
            helpers.removeClass( explanation, 'visualCaptcha-hide' );
            helpers.removeClass( possibilitiesWrapper, 'visualCaptcha-hide' );
        }
    };

    // Choose image
    _chooseImage = function( element, captcha, event ) {
        var image = event.currentTarget,
            possibilitiesWrapper = helpers.findByClass( element, 'visualCaptcha-possibilities', true ),
            explanation = helpers.findByClass( element, 'visualCaptcha-explanation', true ),
            imgElement,
            images,
            imageIndex,
            imageInput,
            imageInputHTML;

        // Check if an input element already exists
        imageInput = helpers.findByTag( explanation, 'input', true );

        if ( imageInput ) {
            // Remove it if so
            explanation.removeChild( imageInput );

            // Remove selected class from selected image
            images = helpers.findByClass( possibilitiesWrapper, 'img' );
            helpers.removeClass( images, 'visualCaptcha-selected' );
        }

        // Add selected class to image
        helpers.addClass( image, 'visualCaptcha-selected' );

        // Get the image index
        imgElement = helpers.findByTag( image, 'img', true );
        imageIndex = parseInt( imgElement.getAttribute( 'data-index' ), 10 );

        // Build the input HTML
        imageInputHTML = templates.imageInput( captcha, imageIndex );

        // Append the input
        explanation.innerHTML += imageInputHTML;
    };

    // Refresh the captcha
    _refreshCaptcha = function( element, captcha ) {
        captcha.refresh();
    };

    _getCaptchaData = function( element ) {
        var image = helpers.findByClass( element, 'imageField', true ) || {},
            audio = helpers.findByClass( element, 'audioField', true ) || {},
            valid = !! ( image.value || audio.value );

        return valid ? {
            valid: valid,
            name:  image.value ? image.name  : audio.name,
            value: image.value ? image.value : audio.value
        } : {
            valid: valid
        };
    };

    return function( element, options ) {
        var config,
            captcha,
            captchaConfig;

        config = deepExtend( {
            imgPath: '/',
            language: language,
            captcha: {}
        }, options );

        element = ( typeof element === "string" ) ? document.getElementById( element ) : element;
        element.config = config;

        // Add visualCaptcha class to element
        helpers.addClass( element, 'visualCaptcha' );

        // Store captcha config
        captchaConfig = deepExtend( config.captcha, {
            _loading: _loading.bind( null, element ),
            _loaded: _loaded.bind( null, element )
        } );

        // Load namespace from data-namespace attribute
        if ( typeof element.getAttribute( 'data-namespace' ) !== 'undefined' ) {
            captchaConfig.namespace = element.getAttribute( 'data-namespace' );
        }

        // Initialize visualCaptcha
        captcha = visualCaptcha( captchaConfig );

        captcha.getCaptchaData = _getCaptchaData.bind( null, element );

        if ( typeof config.init === "function" ) {
            config.init.call( null, captcha );
        }

        return captcha;

    };
} );
    return require( 'visualcaptcha.vanilla' );
} ));