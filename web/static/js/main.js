document.body.classList.remove('no-js');

var http = function() {
    this.toParam = function(obj){
        var query = [];
        for (var key in obj) {
            query.push(encodeURIComponent(key) + '=' + encodeURIComponent(obj[key]));
        }
        return query.join('&');
    };
    this.fromParam = function(str){
        return JSON.parse('{"' + decodeURI(str).replace(/"/g, '\\"').replace(/&/g, '","').replace(/=/g,'":"') + '"}');
    };
    this.get = function(settings, call) {
        var param = this.toParam(settings.data);
        var xmlHttp = new XMLHttpRequest();
        xmlHttp.onreadystatechange = function() { 
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200){
                call(xmlHttp.responseText);
            }
        };

        xmlHttp.open('GET', settings.url+'?'+param, true);
        if (typeof(settings.headers) !== 'undefined') {
            for (var key in settings.headers) {
                // check if the property/key is defined in the object itself, not in parent
                if (settings.headers.hasOwnProperty(key)) {
                    xmlHttp.setRequestHeader(key, settings.headers[key]);
                }
            }
        }
        xmlHttp.send(null);
        return xmlHttp;
    };
    this.post = function(settings, call) {
        if (typeof(call) === 'undefined') {
            call = function(){};
        }
        var formdata = true;
        try {
           settings.data.entries(); // test if formdata
        } catch (e) {
            formdata = false;
        }
        var xmlHttp = new XMLHttpRequest();
        xmlHttp.onreadystatechange = function() { 
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200){
                call(xmlHttp.responseText);
            }
        };
        xmlHttp.open('POST', settings.url, true);
        if (typeof(settings.headers) !== 'undefined') {
            for (var key in settings.headers) {
                // check if the property/key is defined in the object itself, not in parent
                if (settings.headers.hasOwnProperty(key)) {
                    xmlHttp.setRequestHeader(key, settings.headers[key]);
                }
            }
        }
        if(formdata){
            xmlHttp.send(settings.data);
        } else {
            var param = this.toParam(settings.data);
            xmlHttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xmlHttp.send(param);
        }
        return xmlHttp;
    };
};

// Use moment.js from the DOM
var timeyWimey = function(container) {
    if (typeof(container) === 'undefined') {
        this.container = document;
    } else {
        this.container = container;
    }
    this.init = function(){
        // do it for everything on the page
        var momentEls = this.container.querySelectorAll('[data-moment]');
        for (var i = 0; i < momentEls.length; i++) {
            var el = momentEls[i];
            this.format(el.dataset.moment, el);
        }
    };
    this.format = function(type, el){
        var format = el.dataset.format;
        var titleFormat = 'LLLL';
        var time = el.dataset.time;
        switch (type) {
            // add more case-switches here for more types (define types in the DOM as a "data-moment" attribute)
            case 'epoch':
                if (format === 'fromnow') {
                    this.replace(moment.unix(parseInt(time)).format(titleFormat), moment.unix(parseInt(time)).fromNow(), el);
                }
                break;
        }
    };
    this.replace = function(title, text, el) {
        el.setAttribute('title', title);
        el.textContent = text;
    };
};

// https://davidwalsh.name/javascript-debounce-function
function debounce(func, wait, immediate) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

var http = new http();
var time = new timeyWimey();
time.init();

function toggleScroll(){
    if (document.body.classList[0] === 'no-scroll') {
        document.body.classList.remove('no-scroll');
    } else {
        document.body.classList.add('no-scroll');
    }
}

function setModalHeight(el){
    var openedModal;
    if (typeof(el) === 'string') {
        openedModal = document.querySelector(el).nextElementSibling.getElementsByClassName('modal__inner')[0];
    } else {
        openedModal = document.getElementById(el.getAttribute('for')).nextElementSibling.getElementsByClassName('modal__inner')[0];
    }
    var height = 0;
    for (var i = 0; i < openedModal.childNodes.length; i++) {
        var node = openedModal.childNodes[i];
        var elHeight = node.offsetHeight;
        if (!isNaN(elHeight)) {
            var style = getComputedStyle(node);
            extra = parseInt(style.marginTop) + parseInt(style.marginBottom);
            height = height+elHeight+extra;
        }
    }
    height = height+10; // Just a lil bit more

    openedModal.style.maxHeight = height+'px';
}

// Check every "change" event and toggle scrolling if it's a modal
document.addEventListener('change', function(evt) {
    if (evt.target && evt.target.classList.contains('modal-state')) {
        toggleScroll();
    }
});

document.addEventListener('click', function(evt) {
    // Calculate height for modals with 'modal-calc-height'
    if (evt.target && evt.target.classList.contains('modal-calc-height') && evt.target.tagName === 'LABEL') {
        setModalHeight(evt.target);
    }
});

// Turn nav buttons into modal launchers
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.id === "faq") {
        evt.preventDefault();
        toggleScroll();
        // Open modal
        setModalHeight('#modal-faq');
        document.getElementById('modal-faq').checked = true;
    }
    if (evt.target && (evt.target.id === "donate" || evt.target.id === "donate-bar")) {
        evt.preventDefault();
        toggleScroll();
        // Open modal
        setModalHeight('#modal-donate');
        document.getElementById('modal-donate').checked = true;
    }
/*    if (evt.target && evt.target.id === "queue") {
        evt.preventDefault();
        toggleScroll();
        // Open modal
        setModalHeight('#modal-queue');
        document.getElementById('modal-queue').checked = true;
        // Load queue with ajax and auto refresh every 20 seconds if open
    }*/
});

var gameDetailsTemplate = document.getElementById('tpl-details');
var injectCSS = document.getElementById('css-inject');
if (gameDetailsTemplate) {
    Mustache.parse(gameDetailsTemplate.innerHTML);
}
var gameDetails;
var gameDetailsBg = document.getElementById('game-details-bg');
if (gameDetailsBg) {
    var gameDetailsBgImage = gameDetailsBg.getElementsByClassName('bg')[0];
}

// Gotta store those dank memes somewhere
var dank;
var memes;

var detailsOpen = null;

var currentURL = window.location.href; // URL to switch back to when closing details box
function toggleDetails(el){
    if (typeof(el) === 'undefined') {
        el = detailsOpen;
    }
    var lighted = document.getElementsByClassName('light');
    for (var i = lighted.length - 1; i >= 0; i--) {
        lighted[i].classList.remove('light');
    }
    var id =  parseInt(el.dataset.id);
    if (detailsOpen === el) {
        gameDetails.parentNode.removeChild(gameDetails);
        gameDetailsBg.style.display = 'none';
        detailsOpen = null;
        history.replaceState(null, null, currentURL);
        document.body.classList.remove('dark');
        return;
    }
    document.body.classList.add('dark');
    el.classList.add('light');

    // Kill old elements
    var elGameDetails = document.getElementById('game-details');
    var replaceInstead = false;
    if(elGameDetails !== null){
        if (detailsOpen.offsetTop !== topOffset) {
            // only destroy if currently open details is not at the same position as new
            elGameDetails.parentNode.removeChild(elGameDetails);
        } else {
            replaceInstead = true;
        }
    }

    var topOffset = el.offsetTop;
    var amount = Math.floor((topOffset + el.clientHeight)); // Top of body + height of item

    // Loop backwards and stop at first "true" to insert details box
    var rendered = Mustache.render(gameDetailsTemplate.innerHTML, {loading: true});
    var blocks = el.parentNode.children;
    for (var i = blocks.length - 1; i >= 0; i--) {
        var block = blocks[i];
        if (topOffset === block.offsetTop) {
            if (replaceInstead) {
                // only destroy if currently open details is not at the same position as new
                elGameDetails.outerHTML = rendered;
                gameDetails = document.getElementById('game-details');
            } else {
                var tmp = document.createElement('div');
                tmp.innerHTML = rendered;
                gameDetails = tmp.children[0];
                block.parentNode.insertBefore(gameDetails, block.nextSibling);
            }
            break;
        }
    }

    // scroll
    scrollTo({
        easing: 'easeInOutQuad',
        duration: 500,
        position: topOffset - document.getElementById('navbar').clientHeight-10
    });

    gameDetailsBgImage.classList.remove('fadein');
    // Insert "fake" background
    gameDetailsBg.style.display = 'block';
    gameDetailsBg.style.transform = 'translateY('+amount+'px)';



    http.get({
        url: '/api/public/game',
        data: {id: id}
    }, function(res){
        var game = JSON.parse(res);
        dank = game.dank;
        memes = game.memes;
        if (game.uploading == 1) {
            game.uploading = true;
        } else {
            game.uploading = false;
        }
        game.uriencode = function () {
            return function (text, render) {
                return encodeURIComponent(render(text).toLowerCase());
            };
        };
        game.showdrivetut = function(){
            // this is bad and you should feel bad
            return function(text, render) {
                if(render(text) === 'gdrive' || render(text) === 'gdrive_folder'){
                    return ' <a class="btn drive-bypass-btn" href="/google-drive-bypass-tutorial" target="_blank" title="BYPASS GOOGLE DRIVE QUOTA TUTORIAL">BYPASS GOOGLE DRIVE QUOTA TUTORIAL</a>';
                }
            };
        };
        game.formatSize = function(){
            return function(text, render) {
                if (render(text) == '') {
                    return '';
                } else {
                    var bytes = parseInt(render(text));
                    var readable = filesize(bytes, 2, 'jedec'); // Match PHP
                    return readable;
                }
            };
        };
        var rendered = Mustache.render(gameDetailsTemplate.innerHTML, game);
        gameDetails.outerHTML = rendered;
        gameDetails = document.getElementById('game-details');
        history.replaceState(id, game.title, '/game/'+game.slug);

        injectCSS.innerHTML = '';
        imagesLoaded(gameDetailsBgImage, { background: true }, function(instance) {
            injectCSS.innerHTML = '#game-details > .container:before {background-image: url("https://images.gog-statics.com/'+game.bg_id+'.jpg");}';
            gameDetailsBgImage.style.backgroundImage = 'url(https://images.gog-statics.com/'+game.bg_id+'.jpg)';
            gameDetailsBgImage.classList.add('fadein');
            gameDetails.classList.add('fadein');
        });

        gameDetails.querySelector('.close').addEventListener('click', function(evt) {
            toggleDetails();
        });

        // Load extra GOG info
        http.get({
            url: 'https://api.gog.com/products/'+id,
            data: {
                expand: 'changelog',
                x: Date.now()
            }
        }, function(res){
            var goginfo = JSON.parse(res);
            var changelog = goginfo.changelog;
            if (changelog) {
                gameDetails.getElementsByClassName('toggle-changelog')[0].classList.add('fadeIn');
                gameDetails.getElementsByClassName('changelog')[0].innerHTML = changelog;
            }
        });
    });
    detailsOpen = el; // set new current open details
}

var gameBlocks = document.getElementsByClassName('game-blocks');
for (var i = 0; i < gameBlocks.length; i++) {
    gameBlocks[i].addEventListener('click', function(evt) {
        var el = evt.target.closest('.block');
        if (evt.target && evt.target.closest('.block') !== null) {
            evt.preventDefault();
            toggleDetails(el);
        }
    });
}

// Close on dark area click
document.body.addEventListener('click', function(evt) {
    if (evt.target && evt.target.classList.contains('dark')) {
        toggleDetails();
    }
});

// Toggle download links
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.classList.contains('toggle-links')) {
        evt.preventDefault();
        var changelogBox = evt.target.nextElementSibling.nextElementSibling.nextElementSibling.nextElementSibling;
        var linksBlock = evt.target.nextElementSibling.nextElementSibling.nextElementSibling;
        if (linksBlock.classList.contains('open')) {

            var calcSize = linksBlock.cloneNode(true);
            calcSize.classList.add('calcSize');
            linksBlock.parentNode.insertBefore(calcSize, linksBlock.nextSibling);
            var linksHeight = calcSize.clientHeight;
            calcSize.parentNode.removeChild(calcSize);
            linksBlock.classList.add('open');
            linksBlock.style.height = linksHeight+'px';

            setTimeout(function(){
                linksBlock.style.height = null;
            }, 40);

            linksBlock.classList.remove('open');
            return;
        } else {
            if (changelogBox.classList.contains('open')) {
                changelogBox.style.height = null;
                changelogBox.classList.remove('open');
            }
            var calcSize = linksBlock.cloneNode(true);
            calcSize.classList.add('calcSize');
            linksBlock.parentNode.insertBefore(calcSize, linksBlock.nextSibling);
            var linksHeight = calcSize.clientHeight;
            calcSize.parentNode.removeChild(calcSize);
            linksBlock.classList.add('open');
            linksBlock.style.height = linksHeight+'px';
            setTimeout(function(){
                linksBlock.style.height = 'auto';
            }, 200)
        }
    }
    if (evt.target && evt.target.classList.contains('toggle-changelog')) {
        evt.preventDefault();
        var linksBlock = evt.target.nextElementSibling;
        var changelogBox = evt.target.nextElementSibling.nextElementSibling;
        if (changelogBox.classList.contains('open')) {
            changelogBox.style.height = null;
            changelogBox.classList.remove('open');
        } else {
            if (linksBlock.classList.contains('open')) {
                linksBlock.style.height = null;
                linksBlock.classList.remove('open');
            }
            var calcChangeSize = changelogBox.cloneNode(true);
            calcChangeSize.classList.add('calcSize');
            changelogBox.parentNode.insertBefore(calcChangeSize, changelogBox.nextSibling);
            var changeHeight = calcChangeSize.clientHeight;
            calcChangeSize.parentNode.removeChild(calcChangeSize);
            changelogBox.classList.add('open');
            changelogBox.style.height = changeHeight+'px';
        }
    }
});

new Clipboard('.clip', {
    text: function(trigger) {
        var anchors = trigger.parentNode.querySelectorAll('a.item');
        anchors = trigger.parentNode.querySelectorAll('a.item');
        var links = [];
        for (var i = 0; i < anchors.length; i++) {
            var node = anchors[i];
            links.push(node.href);
        }
        return links.join('\n');
    }
});

// "Open all links" button
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.classList.contains('open-all') || evt.target.closest('.open-all')) {
        var target = evt.target;
        if (evt.target.closest('.open-all')) {
            target = evt.target.closest('.open-all');
        }
        var urls = [];
        var anchors = target.parentNode.querySelectorAll('a.item');
        anchors = target.parentNode.querySelectorAll('a.item');
        for (var i = 0; i < anchors.length; i++) {
            var node = anchors[i];
            urls.push(node.href);
        }
        var delay = 0;
        for (var i = 0; i < urls.length; i++) {
            if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1){
                (function(index) {
                    setTimeout(function(){
                        var a = document.createElement('a');
                        a.download = '';
                        a.href = urls[index];
                        a.target = '_blank';
                        a.dispatchEvent(new MouseEvent('click'));
                    }, 100 * ++delay);
                })(i);
            } else {
                (function(index) {
                    setTimeout(function(){
                        window.open(urls[index], '_blank');
                    }, 1000);
                })(i);
            }
        }
    }
}, false);

// Switch view instantly if javascript is enabled
if (document.querySelector('.view-changer > form')) {
    document.querySelector('.view-changer > form').addEventListener('submit', function(evt){
        evt.preventDefault();
        var view = document.activeElement.value;
        var gameContainers = document.getElementsByClassName('game-blocks');
        for (var i = gameContainers.length - 1; i >= 0; i--) {
            gameContainers[i].setAttribute('class', 'game-blocks'); // remove all classes
            gameContainers[i].classList.add(view+'-view');
        }
        http.post({
            url: '/',
            data: {setview: view}
        });
    });
}

// Search bar
document.querySelector('#search-bar').addEventListener('submit', function(evt){
    evt.preventDefault();
    var term = this.querySelector('input').value;
    window.location.href = '/search/'+encodeURIComponent(term);
});

// If on game page
if (document.querySelector('.container.game')) {
    var id = document.querySelector('.container.game').dataset.id;
    http.get({
        url: 'https://api.gog.com/products/'+id,
        data: {
            expand: 'changelog',
            x: Date.now()
        }
    }, function(res){
        var goginfo = JSON.parse(res);
        var changelog = goginfo.changelog;
        if (changelog) {
            var gameDetails = document.getElementById('game-details');
            gameDetails.getElementsByClassName('toggle-changelog')[0].classList.add('fadeIn');
            gameDetails.getElementsByClassName('changelog')[0].innerHTML = changelog;
        }
    });
}



document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.matches('label.item, label.item *')) {
        evt.stopPropagation();
        var linksContainer;
        var target;
        if (evt.target.tagName !== 'LABEL') {
            linksContainer = evt.target.closest('label.item').nextElementSibling.nextElementSibling.children;
            target = evt.target.closest('label.item');
        } else {
            linksContainer = evt.target.nextElementSibling.nextElementSibling.children;
            target = evt.target;
        }
        var icon = target.getElementsByClassName('fas')[0];
        if (icon.classList.contains('fa-minus')) {
            icon.classList.remove('fa-minus');
            icon.classList.add('fa-plus');
        } else {
            icon.classList.remove('fa-plus');
            icon.classList.add('fa-minus');
            icon.title = icon.dataset.toggledText;
        }
    }
}, false);

var captcha;
document.addEventListener('click', function(evt) {
    if (evt.target && evt.target.matches('.__vote-modal-trigger')) {
        evt.stopPropagation();
        var id =  parseInt(evt.target.dataset.id);
        voteBtn.classList.add('hidden');
        document.getElementById('vote-captcha-message').classList.add('hidden');
        document.getElementById('vote-captcha').classList.remove('hidden');
        document.getElementById('vote-captcha-success').classList.add('hidden');
        captcha = visualCaptcha('vote-captcha', {
            captcha: {
                numberOfImages: 9,
                url: window.location.origin+'/annoyanator',
                randomParam: 'what-are-you-looking-at',
                routes: {
                    start: '/begin',
                    image: '/img',
                },
                callbacks: {
                    loaded: function(captcha){
                        // Open modal
                        setModalHeight('#modal-captcha');
                        document.getElementById('modal-captcha').checked = true;
                        captcha.gameid = id; // hacky hack so vote button can get it
                        captcha.voteTrigger = evt.target; // hacky hack so vote button can get it

                        // Stop # when clicking anchors
                        var anchorOptions = document.getElementById('vote-captcha').getElementsByClassName('img');
                        var anchorList = Array.prototype.slice.call(anchorOptions);
                        anchorList.forEach(function(anchor){
                            anchor.addEventListener('click', function(evt){
                                evt.preventDefault();
                                voteBtn.classList.remove('hidden');
                                setModalHeight('#modal-captcha');
                            }, false);
                        });
                    }
                }
            }
        });
    }
}, false);

// Validate when click vote button
var voteBtn = document.getElementsByClassName('__vote')[0];
voteBtn.addEventListener('click', function(evt){
    evt.preventDefault();
    var captchaData = captcha.getCaptchaData();
    if (captchaData.valid) {
        var capName = captcha.imageFieldName();
        var capValue = captchaData.value;
        var postData = {id: captcha.gameid};
        postData[capName] = capValue;
        var captchaMsg = document.getElementById('vote-captcha-message');
        var captchaMsgSuccess = document.getElementById('vote-captcha-success');
        http.post({
            url: '/api/public/vote',
            data: postData
        }, function(res){
            var ret = JSON.parse(res);
            if (ret.SUCCESS) {
                captcha.voteTrigger.classList.add('hidden');
                document.getElementById('vote-captcha').classList.add('hidden');
                captchaMsgSuccess.classList.remove('hidden');
                voteBtn.classList.add('hidden');
                captchaMsg.classList.add('hidden');
                setModalHeight('#modal-captcha');
            } else {
                captcha.refresh();
                captchaMsgSuccess.classList.add('hidden');
                voteBtn.classList.add('hidden');
                captchaMsg.classList.remove('hidden');
                captchaMsg.classList.add('txt-red');
                captchaMsg.innerText = ret.MSG;
            }
        });
    }
}, false);
