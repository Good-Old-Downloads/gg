// https://github.com/ericktatsui/scroll-top-pure-js
// Licensed under the MIT License
var scrollTo = function (opt) {
    var self,
        cache = {};

    var scrollTo = function () {
        self = this;

        this.start = self.getCurrentPosition();
        this.change = self.getNewPosition() - this.start;
        this.currentTime = 0;
        this.increment = 20;
        this.duration = (typeof (opt.duration) === 'undefined') ? 500 : opt.duration;

        if (!this.fail) {
            this.animate();
        }
    };

    scrollTo.prototype.animate = function () {
        // increment the time
        self.currentTime += self.increment;
        // find the value with the quadratic in-out easing function
        var val = self.easing()(self.currentTime, self.start, self.change, self.duration);
        // move the document.body, document.documentElement and window all at once for maximum 
        document.body.scrollTop = val;
        document.documentElement.scrollTop = val;
        window.scrollTop = val;
        // do the animation unless its over
        if (self.currentTime < self.duration) {
            self.requestAnimFrame()(self.animate);
        } else {
            if (opt.callback && typeof (opt.callback) === 'function') {
                // the animation is done so lets callback
                opt.callback();
            }
        }
    };

    scrollTo.prototype.callFail = function () {
        self.fail = true;
        console.error('Argument is not valid.');
    };

    scrollTo.prototype.getNewPosition = function () {
        var position = 0,
            element;

        if (typeof (opt.targetName) == 'string') {
            element = document.querySelector(opt.targetName);

            if (element) {
                position = (element.getBoundingClientRect()).top + window.scrollY;
            } else {
                self.callFail();
            }
        } else if (typeof (opt.position) == 'number') {
            position = opt.position;
        } else if (typeof (opt.target) == 'object' && 'getBoundingClientRect' in opt.target) {
            position = (opt.target.getBoundingClientRect()).top + window.scrollY;
        } else {
            self.callFail();
        }

        return position;
    };

    scrollTo.prototype.easing = function () {
        var easeType;

        if(!cache.easing){
        switch (opt.easing) {
            case 'easeInCubic':
                easeType = self.easeInCubic;
                break;
            case 'inOutQuintic':
                easeType = self.inOutQuintic;
                break;
            default:
                easeType = self.easeInOutQuad;
                break;
        }

        cache.easing = easeType;
    }else{
        easeType = cache.easing;
    }

        return easeType;
    };

    scrollTo.prototype.easeInOutQuad = function (t, b, c, d) {
        t /= d / 2;
        if (t < 1) {
            return c / 2 * t * t + b
        }
        t--;
        return -c / 2 * (t * (t - 2) - 1) + b;
    };

    scrollTo.prototype.easeInCubic = function (t, b, c, d) {
        var tc = (t /= d) * t * t;
        return b + c * (tc);
    };

    scrollTo.prototype.inOutQuintic = function (t, b, c, d) {
        var ts = (t /= d) * t,
        tc = ts * t;
        return b + c * (6 * tc * ts + -15 * ts * ts + 10 * tc);
    };

    // polyfill
    scrollTo.prototype.requestAnimFrame = function () {
        return window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || function (callback) { window.setTimeout(callback, 1000 / 60); };
    };

    scrollTo.prototype.getCurrentPosition = function () {
        return document.documentElement.scrollTop || document.body.parentNode.scrollTop || document.body.scrollTop;
    };

    return new scrollTo();
};