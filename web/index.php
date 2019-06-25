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

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require '../config.php';
require '../db.php';
require '../memcached.php';
require '../Elastic.class.php';
require '../twig.ext.php';

session_set_cookie_params(60*60*24*365, '/', $_SERVER['HTTP_HOST'], ($CONFIG["DEV"] ? false : true), true);
session_start();

// Some shitty functions
function getOption($settingName) {
    global $Memcached, $dbh;
    $settingVal = $Memcached->get($settingName);
    if ($settingVal === false) {
        $get = $dbh->prepare("SELECT `value` FROM `site` WHERE `name` = :name");
        $get->bindParam(':name', $settingName, \PDO::PARAM_STR);
        $get->execute();
        $settingVal = $get->fetchColumn();
        $Memcached->add("god_setting_$settingName", $settingVal, 0);
    }
    if (@unserialize($settingVal) !== false) {
        $settingVal = unserialize($settingVal);
    }
    return $settingVal;
}

function setOption($settingName, $settingVal) {
    global $Memcached, $dbh;
    if (is_array($settingVal)) {
        $settingVal = serialize($settingVal);
    }
    $set = $dbh->prepare("REPLACE INTO `site` (`name`, `value`) VALUES (:name, :value)");
    $set->bindParam(':name', $settingName, \PDO::PARAM_STR);
    $set->bindParam(':value', $settingVal, \PDO::PARAM_STR);
    $Memcached->set("god_setting_$settingName", $settingVal, 0);
    return $set->execute();
}

function getGame($id, $ipAddress) {
    global $dbh;
    // Prepare SQL query
    $get = $dbh->prepare("
        SELECT `id`, `title`, `slug`, `thumb_id`, `bg_id`, `url`, `developer`, `publisher`, `category`, `uploading`, `last_upload`, COUNT(`id`) as `votes`
        FROM `games`
        LEFT JOIN `votes` ON votes.`game_id` = games.`id`
        WHERE `id` = :gameid AND `hidden` = 0
        GROUP BY `id`
    ");
    $get->bindParam(':gameid', $id, \PDO::PARAM_STR);
    $get->execute();
    $game = $get->fetch(\PDO::FETCH_ASSOC);

    $getHasVoted = $dbh->prepare("SELECT COUNT(*) as `has_voted` FROM `votes` WHERE `game_id` = :gameid AND `uid` = INET6_ATON(:ip)");
    $getHasVoted->bindParam(':gameid', $id, \PDO::PARAM_STR);
    $getHasVoted->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
    $getHasVoted->execute();
    $hasVoted = $getHasVoted->fetch(PDO::FETCH_ASSOC);

    // Get list of files
    $files = $dbh->prepare("SELECT `name`, `type`, `size` FROM `files` WHERE `game_id` = :gameid ORDER BY `name` ASC");
    $files->bindParam(':gameid', $game['id'], \PDO::PARAM_INT);
    $files->execute();
    $fileresults = $files->fetchAll(\PDO::FETCH_ASSOC);

    if (count($fileresults) === 0) {
        $filelist = false;
    } else {
        $filelist = ['GAME' => [], 'PATCHES' => [], 'GOODIES' => []];
        foreach ($fileresults as $key => $file) {
            $new = [];
            $new['name'] = $file['name'];
            $new['size'] = $file['size'];
            $filelist[$file['type']][] = $new;
        }
    }
    // Get list of links
    $links = $dbh->prepare("SELECT
                                IF(links.`name` IS NULL, IF(`link_safe` IS NULL, `link`, `link_safe`), links.`name`) as `name`,
                                IF(`link_safe` IS NULL, `link`, `link_safe`) as `link`,
                                `host`,
                                hosters.`name` as `host_name`,
                                `icon_html`,
                                `type`
                            FROM `links`
                            LEFT JOIN `hosters`
                            ON links.`host` = hosters.`id`
                            WHERE `game_id` = :gameid AND links.`hidden` = 0 ORDER BY `type` ASC, hosters.`order` ASC, `name` ASC");
    $links->bindParam(':gameid', $game['id'], \PDO::PARAM_INT);
    $links->execute();
    $linkresults = $links->fetchAll(\PDO::FETCH_ASSOC);

    $hasDrive = false;
    if (count($linkresults) === 0) {
        $linklist = false;
    } else {
        $linklist = ['GAME' => [], 'PATCHES' => [], 'GOODIES' => []];
        foreach ($linkresults as $key => $link) {
            $newitem = [];
            $newitem['name'] = $link['name'];
            $newitem['link'] = $link['link'];
            $linklist[$link['type'].'_temp'][$link['host']]['slug'] = $link['host'];
            $linklist[$link['type'].'_temp'][$link['host']]['name'] = $link['host_name'];
            $linklist[$link['type'].'_temp'][$link['host']]['icon'] = $link['icon_html'];
            $linklist[$link['type'].'_temp'][$link['host']]['links'][] = $newitem;
            $linklist[$link['type']] = array_values($linklist[$link['type'].'_temp']);
            if ($link['host'] === 'gdrive_folder' || $link['host'] === 'gdrive') {
                $hasDrive = true;
            }
        }
        foreach ($linklist as $key => $type) {
            unset($linklist[$key.'_temp']);
        }
    }
    $game['files'] = $filelist;
    $game['links'] = $linklist;

    $game['can_vote'] = !boolval(intval($hasVoted["has_voted"])); // If already voted then cannot vote
    if ($game['can_vote'] === true) {
        $game['can_vote'] = ($game['uploading'] == 0 || $game['updated'] == 1 || $game['new'] == 1) && (date_create_from_format('U', $game['last_upload'])->modify('+30 day')->format('U') < (new DateTime('now'))->format('U'));
    }
    if ($hasDrive && boolval(getOption('disable_drive_voting'))) {
        $game['can_vote'] = false;
    }
    return $game;
}

$configuration = [
    'settings' => [
        'displayErrorDetails' => $CONFIG["DEV"],
    ],
];

// Make Mock Environment if running via command line
if (PHP_SAPI === 'cli') {
    if (is_array($SLIM_MOCK_SETTINGS)) {
        $env = \Slim\Http\Environment::mock($SLIM_MOCK_SETTINGS);
        $configuration['environment'] = $env;
    } else {
        die;
    }
}

$container = new \Slim\Container($configuration);
$app = new \Slim\App($container);

// Get ip address
$app->add(new RKA\Middleware\IpAddress(true));

// Inject VisualCaptcha into app
$container['visualCaptcha'] = function ($container) {
    global $CONFIG;
    $session = new \visualCaptcha\Session();
    return new \visualCaptcha\Captcha($session, "{$CONFIG['BASEDIR']}/captcha", json_decode(file_get_contents("{$CONFIG['BASEDIR']}/captcha/images.json"), true));
};

// Set headers for all requests
$app->add(function ($request, $response, $next) {
    $nonceJS = base64_encode(random_bytes(24));
    $nonceCSS = base64_encode(random_bytes(24));
    $CORS = "default-src https:; script-src 'self' 'nonce-$nonceJS'; object-src 'self'; style-src 'self' 'nonce-$nonceCSS'; img-src 'self' images.gog.com; media-src 'self'; child-src 'none'; font-src 'self'; connect-src 'self' https://api.gog.com";

    // Add global variable to Twig
    $view = $this->get('view');
    $view->getEnvironment()->addGlobal('nonce', ['script' => $nonceJS, 'style' => $nonceCSS]);

    $response = $next($request, $response);
    return $response
            ->withHeader('Content-Security-Policy', $CORS)
            ->withHeader('X-Content-Security-Policy', $CORS)
            ->withHeader('X-WebKit-CSP', $CORS)
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Follow-The-White-Rabbit', "https://www.youtube.com/watch?v=6GggY4TEYbk");
});

// Set language
$app->add(function ($request, $response, $next) {
    global $CONFIG;
    // Language Stuff
    $allowedLanguages = [
        'de_DE' => [
            'ISO-639-1' => 'de'
        ],
        'es_ES' => [
            'ISO-639-1' => 'es'
        ],
        'ru_RU' => [
            'ISO-639-1' => 'ru'
        ]
    ];

    if (isset($_POST['setlang'])) {
        if (isset($_POST['setlang']) && isset($allowedLanguages[$_POST['setlang']])) { // isset() is faster than in_array()
            setcookie('language', $_POST['setlang'], time()+60*60*24*365, '/', $request->getUri()->getHost(), ($CONFIG["DEV"] ? false : true), true);
            $_COOKIE["language"] = $_POST['setlang']; // For current
        } else {
            unset($_COOKIE["language"]);
            setcookie('language', '', -1, '/', $request->getUri()->getHost());
        }
    }

    $language = null;
    if (isset($_COOKIE["language"]) && isset($allowedLanguages[$_COOKIE["language"]])) { // isset() is faster than in_array()
        $language = $_COOKIE["language"];
        putenv("LC_ALL=$language");
        setlocale(LC_ALL, $language);
        bindtextdomain('messages', '../locale');
        bind_textdomain_codeset('messages', 'UTF-8');
        textdomain('messages');
    }

    // Add global variable to Twig
    $view = $this->get('view');
    if ($language !== null) {
        $view->getEnvironment()->addGlobal('language', $allowedLanguages[$language]);
    } else {
        $view->getEnvironment()->addGlobal('language', null);
    }

    $response = $next($request, $response);
    if (isset($_POST['setlang'])) {
        return $response->withRedirect((string)$request->getUri()->withPort(null), 302);
    }
    return $response;
});

// Set game view
$app->add(function ($request, $response, $next) {
    global $CONFIG;

    // Set view mode
    $gameView = null;
    if (isset($_POST['setview'])) {
        if (isset($_POST['setview']) && $_POST['setview'] === 'list') { // isset() is faster than in_array()
            setcookie('game_view', $_POST['setview'], time()+60*60*24*365, '/', $request->getUri()->getHost(), ($CONFIG["DEV"] ? false : true), true);
            $_COOKIE["game_view"] = $_POST['setview'];
            $gameView = 'list';
        } else {
            unset($_COOKIE["game_view"]);
            setcookie('game_view', '', -1, '/', $request->getUri()->getHost());
        }
    }

    if (isset($_COOKIE["game_view"]) && $_COOKIE["game_view"] === 'list') { // isset() is faster than in_array()
        $gameView = $_COOKIE["game_view"];
    }

    // Add global variable to Twig
    $view = $this->get('view');
    $view->getEnvironment()->addGlobal('game_view', $gameView);

    $response = $next($request, $response);
    if (isset($_POST['setview'])) {
        return $response->withRedirect((string)$request->getUri()->withPort(null), 302);
    }
    return $response;
});

// Global Site Shit
$app->add(function ($request, $response, $next) {
    $view = $this->get('view');
    $view->getEnvironment()->addGlobal('disable_drive_voting', boolval(getOption('disable_drive_voting')));
    $view->getEnvironment()->addGlobal('donations', getOption('donations_arr'));
    $view->getEnvironment()->addGlobal('genres', getOption('genres'));
    $response = $next($request, $response);
    return $response;
});

// Check if Ascension client
$app->add(function ($request, $response, $next) {
    $view = $this->get('view');
    $request = $request->withAttribute('isAscension', false);
    if (preg_match('/Ascension\/([0-9.]+) Chrome/', $request->getHeader('user-agent')[0], $matches)) {
        $view->getEnvironment()->addGlobal('is_ascension', true);
        $view->getEnvironment()->addGlobal('ascension_version', $matches[1]);
        $request = $request->withAttribute('isAscension', true);
        $request = $request->withAttribute('ascensionVersion', $matches[1]);
    }
    $response = $next($request, $response);
    return $response;
});

// Register component on container
$container['view'] = function ($container) {
    global $CONFIG;
    $view = new \Slim\Views\Twig("{$CONFIG['BASEDIR']}/templates", [
        'cache' => ($CONFIG['DEV'] ? false : "{$CONFIG['BASEDIR']}/twig_cache")
    ]);
    $twig = $view->getEnvironment();

    // Add extensions
    $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
    $view->addExtension(new \Slim\Views\TwigExtension($container->get('router'), $uri->withPort(null)));

    $twig->addExtension(new Twig_Extensions_Extension_I18n());
    $twig->addExtension(new Twig_Extensions_Extension_Date());
    $twig->addExtension(new AppExtension());
    $twig->addGlobal('config', $CONFIG);
    $twig->addGlobal('was_user', isset($_COOKIE['was_user']));
    $twig->addGlobal('session', $_SESSION);
    return $view;
};

// Remove slashes
$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path != '/' && substr($path, -1) == '/') {
        $uri = $uri->withPath(substr($path, 0, -1))->withPort(null);
        if ($request->getMethod() == 'GET') {
            return $response->withRedirect((string)$uri, 301);
        } else {
            return $next($request->withUri($uri), $response);
        }
    }
    return $next($request, $response);
});

// 404
$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        return $container->view->render($response->withStatus(404), '404.twig');
    };
};

$app->get('/', function ($request, $response, $args) {
    global $dbh;
    // Backwards compat
    if (isset($_GET['game']) && is_numeric($_GET['game'])) {
        $gameid = intval($_GET['game']);
        $getSlug = $dbh->prepare("SELECT `slug` FROM `games` WHERE `id` = :id");
        $getSlug->bindParam(':id', $gameid, \PDO::PARAM_INT);
        $getSlug->execute();
        $slug = $getSlug->fetchColumn(0);
        return $response->withRedirect("/game/$slug", 301);
    }

    $ipAddress = $request->getAttribute('ip_address');
    $getNew = $dbh->prepare("
        SELECT *
           FROM `games`
           WHERE `new` = 1
           AND `hidden` != 1
        GROUP BY `id`
        ORDER BY `last_update` DESC, `title` ASC
    ");
    $getNew->execute();
    $new = $getNew->fetchAll(PDO::FETCH_ASSOC);

    $getUpdated = $dbh->prepare("
        SELECT *
           FROM `games`
           WHERE `updated` = 1
           AND `new` = 0
           AND `hidden` != 1
        GROUP BY `id`
        ORDER BY `last_update` DESC, `title` ASC
    ");
    $getUpdated->execute();
    $updated = $getUpdated->fetchAll(PDO::FETCH_ASSOC);

    return $this->view->render($response, 'index.twig', [
        'updated' => $updated,
        'new' => $new,
        'notice' => getOption('notice')
    ]);
});

$app->get('/search', function ($request, $response, $args) {
    if (isset($_GET['s'])) {
        return $response->withRedirect("/search/".$_GET['s'], 302);
    }
    return $response->withRedirect("/search/all", 302);
});

$app->get('/search/{term}[/{page}[/{sort}[/{sortby}[/{genre}[/{developer}]]]]]', function ($request, $response, $args) {
    global $CONFIG;
    $Elastic = new Elastic($CONFIG['ES']['HOSTS']);

    // Check dev
    $developer = null;
    if (isset($args['developer'])) {
        $developer = strtolower(trim(html_entity_decode($args['developer'])));
    }

    // Check genre
    $genre = 'any';
    if (isset($args['genre'])) {
        $genres = getOption('genres');
        if (array_key_exists($args['genre'], $genres)) {
            $genre = $genres[$args['genre']];
        }
    }

    // Term
    $all = false;
    if ($args['term'] === 'all') {
        $all = true;
        $term = 'all';
    } else {
        $term = trim($args['term']);
    }

    // Sorting
    $sortKey = null;
    if (isset($args['sort'])) {
        $sortKey = $args['sort'];
    }
    $sortBy = null;
    if (isset($args['sortby'])) {
        $sortBy = $args['sortby'];
    }
    if ($sortKey === null && !$all) {
        $sortKey = 'relevance';
    }
    if ($sortKey === null && $all) {
        $sortKey = 'title';
    }

    $validSortBys = ['asc', 'desc'];
    $order = "asc";
    if (in_array($sortBy, $validSortBys)) {
        $order = $sortBy;
    }

    $sort = [];
    switch ($sortKey) {
        case 'title':
            $sort[] = ['title.raw' => [ 'order' => $order ]];
            break;
        case 'date':
            $sort[] = ['last_update' => [ 'order' => $order ]];
            break;
        case 'relevance':
            $sort = [];
            break;
    }

    // Query
    $query = [];
    if ($all) {
        if ($genre != 'any') {
            $query['query']['bool']['filter'] = [
                [
                    'term' => [
                        'category' => $genre
                    ]
                ]
            ];
        }
        if ($developer !== null) {
            $query['query']['bool']['filter'] = [
                [
                    'term' => [
                        'developer' => $developer
                    ]
                ]
            ];
        }
        $queryCount = '';
    } else {
        $query['min_score'] = 2;
        $query['query']['bool']['must'][] = [
            [
                'match' => [
                    'title' => [
                        'query' => $term
                    ]
                ]
            ]
        ];
            
        if ($genre != 'any') {
            $query['query']['bool']['filter'] = [
                [
                    'term' => [
                        'category' => $genre
                    ]
                ]
            ];
        }
        if ($developer !== null) {
            $query['query']['bool']['filter'] = [
                [
                    'term' => [
                        'developer' => $developer
                    ]
                ]
            ];
        }
        $queryCount = ['query' => $query['query']];
    }
    $query['sort'] = $sort;

    // Don't show hidden
    $query['query']['bool']['must'][] = [
        'term' => [
            'hidden' => 0
        ]
    ];

    // Pagination
    $limit = 28; // so it can be even

    $page = 1;
    if (isset($args['page'])) {
        $page = $args['page'];
    }
    $pageCurrent = intval($page);

    // In case page is less than first page
    if ($pageCurrent < 1) {
        $pageCurrent = 1;
        $offset = 0;
    } else {
        $offset = ($pageCurrent - 1) * $limit;
    }

    $countParams = [
        'index' => 'gg', 
        'type' => 'gg_game',
        'body'  => $queryCount
    ];
    $countTotal = $Elastic->count($countParams)['count'];

    $params = [
        'index' => 'gg', 
        'type' => 'gg_game',
        'from' => $offset,
        'size' => $limit,
        'body'  => $query
    ];
    $results = $Elastic->search($params);
    if ($results['hits']['total'] < $countTotal) {
        $countTotal = $results['hits']['total'];
    }

    if ($request->getAttribute('isAscension')) {
        # code...
    } else {
        return $this->view->render($response, 'search.twig', [
            'elastic' => true,
            'developer' => $developer,
            'term' => $term,
            'games' => $results['hits'],
            'all' => $all,
            'total' => $countTotal,
            'pagination' => [
                'total' => $countTotal,
                'page' => $pageCurrent,
                'offset' => $offset,
                'limit' => $limit,
                'path' => $args['term'],
                'sort' => $sortKey,
                'sortby' => $order,
                'genre' => $genre
            ]
        ]);
    }
});

$app->get('/queue', function ($request, $response, $args) {
    global $dbh;
    $ipAddress = $request->getAttribute('ip_address');
    $getUploading = $dbh->prepare("
        SELECT `title`, `new`, `updated`, `slug` FROM `games`
        WHERE `uploading` = 1
    ");
    $getUploading->execute();
    $uploading = $getUploading->fetchAll(PDO::FETCH_ASSOC);

    $getNew = $dbh->prepare("
        SELECT `title`, `last_update`, `new`, `updated`, `queued`, `slug` FROM `games`
        WHERE `new` = 1 AND `uploading` = 0 AND `queued` = 1
        ORDER BY `new` DESC, `updated` DESC, `last_update` ASC
    ");
    $getNew->execute();
    $new = $getNew->fetchAll(PDO::FETCH_ASSOC);

    $getUpdated = $dbh->prepare("
        SELECT `title`, `last_update`, `new`, `updated`, `queued`, `slug` FROM `games`
        WHERE `updated` = 1 AND `uploading` = 0 AND `queued` = 1
        ORDER BY `new` DESC, `updated` DESC, `last_update` ASC
    ");
    $getUpdated->execute();
    $updated = $getUpdated->fetchAll(PDO::FETCH_ASSOC);

    $getVoted = $dbh->prepare("
        SELECT `id`, `title`, `slug`, SUM(IF(V.`uid` = INET6_ATON(:ip), 1, 0)) as `has_voted`, COUNT(`uid`) as `votes`
        FROM `votes` V
        LEFT JOIN `games` G ON V.`game_id` = G.`id`
        WHERE `uploading` = 0
        GROUP BY V.`game_id`
        ORDER BY `votes` DESC, V.`game_id` DESC
    ");
    $getVoted->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
    $getVoted->execute();
    $votes = $getVoted->fetchAll(PDO::FETCH_ASSOC);

    $out = ['uploading' => $uploading, 'new' => $new, 'updated' => $updated, 'votes' => $votes];
    return $this->view->render($response, 'queue.twig', $out);
});

$app->get('/faq', function ($request, $response, $args) {
    return $this->view->render($response, 'faq.twig');
});

$app->get('/google-drive-bypass-tutorial', function ($request, $response, $args) {
    return $this->view->render($response, 'drive_tutorial.twig');
});

$app->get('/donate', function ($request, $response, $args) {
    return $this->view->render($response, 'donate.twig');
});

$app->get('/rss', function ($request, $response, $args) {
    global $dbh;
    $getGames = $dbh->prepare('
        SELECT `id`, `title`, `last_update`, `slug` FROM `games`
        WHERE `hidden` != 1
        ORDER BY `last_update` DESC
        LIMIT 0,50
    ');
    $getGames->execute();
    $games = $getGames->fetchAll(PDO::FETCH_ASSOC);
    return $this->view->render($response, 'rss.twig', [
        'games' => $games
    ])->withHeader('Content-type', 'application/rss+xml');
});

$app->get('/admin', function ($request, $response, $args) {
    global $CONFIG, $dbh;
    if ($_SESSION['user'] === $CONFIG['USER']['NAME']) {
        $games = $dbh->prepare('SELECT COUNT(*) FROM `games` WHERE `hidden` = 0');
        $games->execute();

        $gamesHidden = $dbh->prepare('SELECT COUNT(*) FROM `games` WHERE `hidden` = 1');
        $gamesHidden->execute();

        $gamesNew = $dbh->prepare('SELECT COUNT(*) FROM `games` WHERE `hidden` = 0 AND `new` = 1');
        $gamesNew->execute();

        $gamesUpdated = $dbh->prepare('SELECT COUNT(*) FROM `games` WHERE `hidden` = 0 AND `updated` = 1');
        $gamesUpdated->execute();

        $gamesTotal = $dbh->prepare('SELECT COUNT(*) FROM `games`');
        $gamesTotal->execute();

        $hosters = $dbh->prepare('SELECT * FROM `hosters` ORDER BY `order`');
        $hosters->execute();

        return $this->view->render($response, 'admin.twig',
            [
                'game_amount' => number_format($games->fetchColumn()),
                'game_hidden_amount' => number_format($gamesHidden->fetchColumn()),
                'game_new_amount' => number_format($gamesNew->fetchColumn()),
                'game_updated_amount' => number_format($gamesUpdated->fetchColumn()),
                'game_total_amount' => number_format($gamesTotal->fetchColumn()),
                'hosters' => $hosters->fetchAll(\PDO::FETCH_ASSOC),
                'settings' => [
                    'notice' => getOption('notice')
            ]
        ]);
    } else {
        $notFoundHandler = $this['notFoundHandler'];
        return $notFoundHandler($request, $response);
    }
});

$app->post('/admin', function ($request, $response, $args) {
    global $CONFIG, $dbh;
    if ($_SESSION['user'] !== $CONFIG['USER']['NAME']) {
        $notFoundHandler = $this['notFoundHandler'];
        return $notFoundHandler($request, $response);
    }

    if (isset($_POST['notice_save'])) {
        setOption('notice', $_POST['notice']);
        return $response->withRedirect("/admin", 302);
    }

    if (isset($_POST['reindex_search'])) {
        $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
        try {
            $Elastic->Clear();
        } catch (Exception $e) {

        }
        if ($Elastic->InsertAll()){
            return $response->withRedirect("/admin", 302);
        }
        return 'R.I.P.';
    }

    if (isset($_POST['donations_save'])) {
        $amount = floatval($_POST['donations_amount']);
        $goal = floatval($_POST['donations_goal']);
        $percent = ($amount/$goal)*100;
        setOption('donations_arr', [
            'amount' => $amount,
            'goal' => $goal,
            'percent' => $percent
        ]);
        return $response->withRedirect("/admin", 302);
    }

    if (isset($_POST['disable_drive_voting'])) {
        $driveVoting = getOption('disable_drive_voting');
        $selected = intval($_POST['disable_drive_voting_value']);
        setOption('disable_drive_voting', $selected);
        return $response->withRedirect("/admin", 302);
    }

    if (isset($_POST['genres_refresh'])) {
        $getGenres = $dbh->prepare("SELECT `category` FROM `games`
                                    WHERE `hidden` = 0 AND (`category` IS NOT NULL) || (`category` != '')
                                    GROUP BY `category`");
        $getGenres->execute();
        $genres = $getGenres->fetchAll(PDO::FETCH_COLUMN, 0);
        $multi = [];
        foreach ($genres as $key => $value) {
            $multi[strtolower($value)] = $value;
        }
        setOption('genres', $multi);
        return $response->withRedirect("/admin", 302);
    }
});

$app->get('/'.$CONFIG['LOGIN_PATH'], function ($request, $response, $args) {
    return $this->view->render($response, 'login.twig');
})->setName('login');

$app->post('/'.$CONFIG['LOGIN_PATH'], function ($request, $response, $args) {
    global $CONFIG;
    // We don't need an actual login system
    if (isset($_POST['login'])) {
        if ($_POST['username'] === $CONFIG['USER']['NAME'] && $_POST['password'] === $CONFIG['USER']['PASS']) {
            unset($_SESSION['user']);
            session_set_cookie_params(60*60*24*365, '/', $request->getUri()->getHost(), ($CONFIG["DEV"] ? false : true), true);
            session_start();
            $_SESSION['user'] = $CONFIG['USER']['NAME'];
            setcookie("was_user", "1", 2147483647, '/');
            return $response->withRedirect("/admin", 302);
        }
    }
});

$app->get('/logout', function ($request, $response, $args) {
    unset($_SESSION['user']);
    return $response->withRedirect("/", 302);
});

$app->get('/game/{game_id}', function ($request, $response, $args) {
    global $dbh;
    $ipAddress = $request->getAttribute('ip_address');
    if (!isset($args["game_id"])) {
        echo "nope";
        die;
    }
    $id = $args["game_id"];
    if (is_numeric($id)) {
        $game = getGame($id, $ipAddress);
    } else {
        $getId = $dbh->prepare("SELECT `id` FROM `games` WHERE `slug` = :id");
        $getId->bindParam(':id', $id, \PDO::PARAM_STR);
        $getId->execute();
        $gameId = $getId->fetchColumn();
        $game = getGame($gameId, $ipAddress);
    }
    return $this->view->render($response, 'game.twig', ['game' => [$game]]);
});

// API
$app->group('/api/v1', function () use ($app) {
    $app->get('/updateGamesImages', function ($request, $response, $args) {
        global $CONFIG;
        if (PHP_OS === "WINNT"){
            pclose(popen("start php {$CONFIG['BASEDIR']}/cron.php updateGamesImages", "r")); 
        } else {
            exec("php {$CONFIG['BASEDIR']}/cron.php updateGamesImages > /dev/null &");
        }
    });

    $app->get('/updateImages', function ($request, $response, $args) {
        global $CONFIG;
        if (PHP_OS === "WINNT"){
            pclose(popen("start php {$CONFIG['BASEDIR']}/cron.php updateImages", "r")); 
        } else {
            exec("php {$CONFIG['BASEDIR']}/cron.php updateImages > /dev/null &");
        }
    });

    $app->post('/addgamebasedonid', function ($request, $response, $args) {
        global $dbh, $CONFIG;
        $id = intval($_POST['id']);
        $client = new GuzzleHttp\Client();

        $add = $dbh->prepare("
            INSERT IGNORE INTO `games`
            (`id`, `title`, `indev`, `slug`, `thumb_id`, `bg_id`, `slug_folder`, `url`, `release_date`, `developer`, `publisher`, `category`, `hidden`)
            VALUES (:id, :title, :indev, :slug, :thumb_id, :bg_id, :slug, :url, :rlsdate, :dev, :pub, :cat, 1);
        ");
        $add->bindParam(':id', $id, \PDO::PARAM_INT);
        $add->bindParam(':title', $title, \PDO::PARAM_STR);
        $add->bindParam(':indev', $inDev, \PDO::PARAM_INT);
        $add->bindParam(':slug', $slug, \PDO::PARAM_STR);
        $add->bindParam(':thumb_id', $thumb, \PDO::PARAM_STR);
        $add->bindParam(':bg_id', $bg, \PDO::PARAM_STR);
        $add->bindParam(':url', $url, \PDO::PARAM_STR);
        $add->bindParam(':rlsdate', $releaseDate, \PDO::PARAM_INT);
        $add->bindParam(':dev', $developer, \PDO::PARAM_STR);
        $add->bindParam(':pub', $publisher, \PDO::PARAM_STR);
        $add->bindParam(':cat', $category, \PDO::PARAM_STR);

        $rand = md5(openssl_random_pseudo_bytes(5));
        try {
            $res = $client->request('GET', "https://api.gog.com/products/$id?$rand");
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $fail[] = $id;
        }
        $json = json_decode($res->getBody(), true);
        $id = $json['id'];
        $title = $json['title'];
        $inDev = $json['in_development']['active'];
        $slug = $json['slug'];
        $url = $json['links']['product_card'];

        $thumb = $json['images']['logo'];
        preg_match('/gog\.com\/([a-z0-9]{64})_glx_logo\.jpg/', $thumb, $thumbmatch);
        $thumb = $thumbmatch[1];

        $bg = $json['images']['background'];
        preg_match('/gog\.com\/([a-z0-9]{64})\.jpg/', $bg, $bgmatch);
        $bg = $bgmatch[1];

        $parseDate = date_parse($json['release_date']);
        $releaseDate = date('U', mktime($parseDate['hour'], $parseDate['minute'], $parseDate['second'], $parseDate['month'], $parseDate['day'], $parseDate['year']));

        $developer = '';
        $publisher = '';
        $category = '';

        $success = $add->execute();
        if ($success) {
            $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
            $Elastic->UpdateGame($id);
            return $response->withJson(['SUCCESS' => true, 'MSG' => "$title added. (hidden)"]);
        } else {
            return $response->withJson(['SUCCESS' => false]);
        }
    });

    // Logging
    $app->get('/getlog', function ($request, $response, $args) {
        global $dbh;

        // Prepare SQL query
        $get = $dbh->prepare("SELECT * FROM `log` ORDER BY `id` DESC, `date` LIMIT 0,30");
        $get->execute();
        $logs = $get->fetchAll(PDO::FETCH_ASSOC);

        return $response->withJson($logs);
    });

    // Gets item that needs to be uploaded next
    $app->get('/queue', function ($request, $response, $args) {
        global $dbh;
        $game = [];
        $get = $dbh->prepare("
            SELECT `id`, `title`, `slug`, `slug_folder`, `new`, `updated`, `queued`, `uploading`, COUNT(V.`game_id`) as `votes` FROM `games`
            LEFT JOIN `votes` V ON `game_id` = `id`
            GROUP BY `id`
            HAVING (`new` = 1 OR `updated` = 1 OR `votes` > 0) AND (`queued` = 1 OR `votes` > 0) AND `uploading` = 0
            ORDER BY `new` DESC, `updated` DESC, `votes` DESC, `last_update` ASC
            LIMIT 1
        ");
        $get->execute();
        if ($get->rowCount() > 0) {
            $game = $get->fetch(PDO::FETCH_ASSOC);
        }
        return $response->withJson($game);
    });

    // For upload script
    $app->get('/games/info', function ($request, $response, $args) {
        global $dbh;
        $get = $dbh->prepare("SELECT `slug_folder` FROM `games` WHERE `id` = :id");
        $get->bindParam(':id', $_GET['id'], \PDO::PARAM_INT);
        if (!$get->execute()) {
            return $response->withJson(['SUCCESS' => false]);
        }
        $games = $get->fetch(PDO::FETCH_ASSOC);
        return $response->withJson(['SUCCESS' => true, 'DATA' => $games]);
    });
    $app->post('/games/clearlinks', function ($request, $response, $args) {
        global $dbh;
        $del = $dbh->prepare("DELETE FROM `links` WHERE `game_id` = :id");
        $del->bindParam(':id', $_POST['id'], \PDO::PARAM_INT);
        return $response->withJson($del->execute());
    });
    $app->post('/games/preupload', function ($request, $response, $args) {
        global $dbh, $CONFIG;
        $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
        $json = $request->getParsedBody();
        // Set game to "uploading"
        $s = $dbh->prepare("UPDATE `games` SET `uploading` = 1 WHERE `id` = :id");
        $s->bindParam(':id', $json['id'], \PDO::PARAM_INT);
        $s->execute();

        // Delete old links
        $del = $dbh->prepare("DELETE FROM `links` WHERE `game_id` = :id");
        $del->bindParam(':id', $json['id'], \PDO::PARAM_INT);
        $del->execute();
        $Elastic->UpdateGame($json['id']);
        return $response->withJson(['SUCCESS' => true]);
    });
    $app->post('/games/addlink', function ($request, $response, $args) {
        global $dbh;
        $json = $request->getParsedBody();
        $has_safelink = false;
        if (!empty($json['link_safe'])) {
            $has_safelink = true;
        }
        $add = $dbh->prepare("
            INSERT INTO `links` (`game_id`, `link`, `link_safe`, `name`, `type`, `host`)
            VALUES (:id, :link, :safelink, :filename, :type, :host)");
        $add->bindParam(':id', $json['id'], \PDO::PARAM_INT);
        $add->bindParam(':link', $json['link'], \PDO::PARAM_STR);
        if ($has_safelink) {
            $add->bindParam(':safelink', $json['link_safe'], \PDO::PARAM_STR);
        } else {
            $add->bindValue(':safelink', null, \PDO::PARAM_INT);
        }
        if ($json['filename'] === '' || $json['filename'] === 'null') {
            $add->bindValue(':filename', null, \PDO::PARAM_INT);
        } else {
            $add->bindParam(':filename', $json['filename'], \PDO::PARAM_STR);
        }
        $add->bindParam(':type', $json['type'], \PDO::PARAM_STR);
        $add->bindParam(':host', $json['host'], \PDO::PARAM_STR);
        return $response->withJson($add->execute());
    });
    $app->post('/games/postupload', function ($request, $response, $args) {
        global $dbh, $CONFIG;
        $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
        $json = $request->getParsedBody();
        $id = $json['id'];
        // Unset "uploading" and "queued" = 0
        $u = $dbh->prepare("UPDATE `games` SET `uploading` = 0, `queued` = 0 WHERE `id` = :id");
        $u->bindParam(':id', $id, \PDO::PARAM_INT);
        $u->execute();
        
        // Clear votes
        $v = $dbh->prepare("DELETE FROM `votes` WHERE `game_id` = :id");
        $v->bindParam(':id', $json['id'], \PDO::PARAM_INT);
        $v->execute();

        // Set `last_upload` to now
        $set = $dbh->prepare("UPDATE `games` SET `last_upload` = UNIX_TIMESTAMP() WHERE `id` = :id");
        $set->bindParam(':id', $id, \PDO::PARAM_INT);
        $set->execute();
        $Elastic->UpdateGame($id);
        return $response->withJson(['SUCCESS' => true]);
    });
    $app->post('/games/clearfiles', function ($request, $response, $args) {
        global $dbh;
        $json = $request->getParsedBody();
        $del = $dbh->prepare("DELETE FROM `files` WHERE `game_id` = :id");
        $del->bindParam(':id', $json['id'], \PDO::PARAM_INT);
        return $response->withJson($del->execute());
    });

    $app->post('/games/addfiles', function ($request, $response, $args) {
        global $dbh;
        $json = $request->getParsedBody();
        $files = $json['FILES'];
        $id = $json['id'];;
        $add = $dbh->prepare("INSERT INTO `files` (`game_id`, `name`, `type`, `size`)
                              VALUES (:id, :filename, :type, :size)");
        $add->bindParam(':id', $id, \PDO::PARAM_INT);
        $add->bindParam(':filename', $filename, \PDO::PARAM_STR);
        $add->bindParam(':type', $type, \PDO::PARAM_STR);
        $add->bindParam(':size', $size, \PDO::PARAM_INT);
        foreach ($files as $type => $filelist) {
            foreach ($filelist as $key => $file) {
                $filename = $file['name'];
                $size = $file['size'];
                $add->execute();
            }
        }
        return $response->withJson($files);
    });

    // Batch editing
    $app->post('/games/batchedit', function ($request, $response, $args) {
        global $dbh;
        $slugs = trim(preg_replace("/[\r\n]+/", "\n", $_POST["slugs"]));
        $slugs = explode("\n", $slugs);
        $setHidden = $dbh->prepare('UPDATE `games` SET `hidden` = 1 WHERE `slug_folder` = :slug');
        $setHidden->bindParam(':slug', $slug, PDO::PARAM_STR);

        $unsetHidden = $dbh->prepare('UPDATE `games` SET `hidden` = 0 WHERE `slug_folder` = :slug');
        $unsetHidden->bindParam(':slug', $slug, PDO::PARAM_STR);

        $setUpdated = $dbh->prepare('UPDATE `games` SET `new` = 0, `updated` = 1, `queued` = 1, `last_update` = UNIX_TIMESTAMP(), `last_upload` = UNIX_TIMESTAMP() WHERE `slug_folder` = :slug');
        $setUpdated->bindParam(':slug', $slug, PDO::PARAM_STR);
        
        $setNew = $dbh->prepare('UPDATE `games` SET `hidden` = 0, `new` = 1, `updated` = 0, `queued` = 1, `last_update` = UNIX_TIMESTAMP(), `last_upload` = UNIX_TIMESTAMP() WHERE `slug_folder` = :slug');
        $setNew->bindParam(':slug', $slug, PDO::PARAM_STR);

        // Clear links
        $delLinks = $dbh->prepare("
            DELETE `links` FROM `links`
            JOIN `games` ON `game_id` = games.`id`
            WHERE `slug_folder` = :slug
        ");
        $delLinks->bindParam(':slug', $slug, \PDO::PARAM_STR);

        // Clear files
        $delFiles = $dbh->prepare("
            DELETE `files` FROM `files`
            JOIN `games` ON `game_id` = games.`id`
            WHERE `slug_folder` = :slug
        ");
        $delFiles->bindParam(':slug', $slug, \PDO::PARAM_STR);

        $changed = 0;
        switch ($_POST['action']) {
            case 'hide':
                foreach ($slugs as $key => $slug) {
                    $setHidden->execute();
                    if ($setHidden->rowCount() > 0) {
                        $changed++;
                    }
                }
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$changed games hidden."]);
                break;
            case 'unhide':
                foreach ($slugs as $key => $slug) {
                    $unsetHidden->execute();
                    if ($unsetHidden->rowCount() > 0) {
                        $changed++;
                    }
                }
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$changed games unhidden."]);
                break;
            case 'update':
                foreach ($slugs as $key => $slug) {
                    $delFiles->execute();
                    $delLinks->execute();
                    $setUpdated->execute();
                    if ($setUpdated->rowCount() > 0) {
                        $changed++;
                    }
                }
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$changed games set to \"updated\"."]);
                break;
            case 'new':
                foreach ($slugs as $key => $slug) {
                    $setNew->execute();
                    if ($setNew->rowCount() > 0) {
                        $changed++;
                    }
                }
                return $response->withJson(['SUCCESS' => true, 'MSG' => "$changed games set to \"new\"."]);
                break;
            default:
                return $response->withJson(['SUCCESS' => false, 'MSG' => "Invalid Action"]);
                break;
        }
    });

    // Get games for Handsontable
    $app->get('/getgames', function ($request, $response, $args) {
        global $dbh;
        $return = [];

        // Prepare SQL query
        $sort = "";
        if (isset($_GET['sort'])) {
            if ($_GET['sortOrder'] === 'true') {
                $dir = 'DESC';
            } else {
                $dir = 'ASC';
            }
            $sort = "ORDER BY `".$_GET['sort']."` $dir"; // don't forget to sanitize this wtf im so tired kill me
        }

        $limit = 10;
        if (isset($_GET['limit'])) {
            $limit = intval($_GET['limit']);
        }

        $showHidden = "`hidden` != 1";
        if ($_GET['showHidden'] === 'true') {
            $showHidden = "(`hidden` != 1 OR `hidden` != 0)";
        }

        $term = "AND `title` IS NOT NULL";
        $hasTerm = false;
        if (isset($_GET['term']) && $_GET['term'] != 'null') { // lmao
            $hasTerm = true;
            $term = "AND (`title` LIKE :term OR `slug` LIKE :slugterm)";
        }

        $get = $dbh->prepare("
            SELECT
                `id`,
                `title`,
                CASE WHEN `hidden` = 1 THEN 'true'
                     WHEN `hidden` = 0 then 'false' END AS `hidden`,
                CASE WHEN `indev` = 1 THEN 'true'
                     WHEN `indev` = 0 then 'false' END AS `indev`,
                CASE WHEN `new` = 1 THEN 'true'
                     WHEN `new` = 0 then 'false' END AS `new`,
                CASE WHEN `updated` = 1 THEN 'true'
                     WHEN `updated` = 0 then 'false' END AS `updated`,
                CASE WHEN `uploading` = 1 THEN 'true'
                     WHEN `uploading` = 0 then 'false' END AS `uploading`,
                CASE WHEN `queued` = 1 THEN 'true'
                     WHEN `queued` = 0 then 'false' END AS `queued`,
                `last_upload`,
                `last_update`,
                `slug`,
                `slug_folder`,
                `url`,
                `release_date`,
                `developer`,
                `publisher`,
                `category`,
                `thumb_id`,
                `bg_id`
                FROM `games`
                WHERE $showHidden $term
                $sort
                LIMIT $limit");
        if ($hasTerm) {
            $term = "%".$_GET['term']."%";
            $get->bindParam(':term', $term, \PDO::PARAM_STR);
            $get->bindParam(':slugterm', $term, \PDO::PARAM_STR);
        }
        $get->execute();
        $games = $get->fetchAll(PDO::FETCH_NUM);

        $get->execute();
        $cols = $get->fetch(PDO::FETCH_ASSOC);
        if (count($games) > 0) {
            $return['headers'] = array_keys($cols);
            $return['data'] = $games;
        }
        return $response->withJson($return);
    });

    // Sabe
    $app->post('/savegames', function ($request, $response, $args) {
        global $dbh, $CONFIG;
        $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
        $data = json_decode($_POST['data']);
        foreach ($data as $key => $value) {
            $id = $value->id;
            $column = $value->column;
            $oldVal = $value->old;
            $newVal = $value->new;
            if ($oldVal === 'true') { $oldVal = 1; }
            if ($oldVal === 'false') { $oldVal = 0; }
            if ($newVal === 'true') { $newVal = 1; }
            if ($newVal === 'false') { $newVal = 0; }

            $where = "`$column` = :old";
            if ($oldVal === null) {
                $where = "`$column` is :old";
            }

            $extraSQL = '';
            if ($column === 'updated' && $oldVal === 0 && $newVal === 1) {
                $extraSQL = ', `last_update` = UNIX_TIMESTAMP()';
                // Clear links
                $delLinks = $dbh->prepare("DELETE FROM `links` WHERE `game_id` = :id");
                $delLinks->bindParam(':id', $id, \PDO::PARAM_INT);
                $delLinks->execute();

                // Clear files
                $delFiles = $dbh->prepare("DELETE FROM `files` WHERE `game_id` = :id");
                $delFiles->bindParam(':id', $id, \PDO::PARAM_INT);
                $delFiles->execute();
            }

            if ($column === 'new' && $oldVal === 0 && $newVal === 1) {
                $extraSQL = ', `last_update` = UNIX_TIMESTAMP(), `last_upload` = UNIX_TIMESTAMP()';

                // Clear files
                $delFiles = $dbh->prepare("DELETE FROM `files` WHERE `game_id` = :id");
                $delFiles->bindParam(':id', $id, \PDO::PARAM_INT);
                $delFiles->execute();

                // Clear links
                $delLinks = $dbh->prepare("DELETE FROM `links` WHERE `game_id` = :id");
                $delLinks->bindParam(':id', $id, \PDO::PARAM_INT);
                $delLinks->execute();
            }

            $set = $dbh->prepare("UPDATE `games` SET `$column` = :new $extraSQL WHERE $where AND `id` = :id"); // hooray sql injection
            $set->bindValue(':id', $id);
            $set->bindValue(':new', $newVal);
            $set->bindValue(':old', $oldVal);
            if ($set->execute()) {
                $Elastic->UpdateGame($id);
            }
        }
        return $response->withJson(['SUCCESS' => true]);
    });
})->add(function ($request, $response, $next) {
    global $CONFIG;
    // Check if admin
    $key = $request->getHeaders()['HTTP_X_API_KEY'][0]; // X-Api-Key
    if (isset($key)) {
        if ($key === $CONFIG['USER']['KEY']) {
            $response = $next($request, $response);
        } else {
            return $response->withJson(['SUCCESS' => false, 'MSG' => "Invalid API key. This request has been logged."], 401);    
        }
        return $response;
    } else {
        return $response->withJson(['SUCCESS' => false, 'MSG' => "API key not set."], 401);
    }
});

$app->group('/api/public', function () use ($app) {
    $app->get('/game', function ($request, $response, $args) {
        $ipAddress = $request->getAttribute('ip_address');
        $game = getGame($_GET['id'], $ipAddress);
        return $response->withJson($game);
    });
    $app->post('/vote', function ($request, $response, $args) {
        global $dbh, $CONFIG;
        $ipAddress = $request->getAttribute('ip_address');
        if (isset($_POST['id']) && is_numeric($_POST['id'])) {
            $captcha = $this->get('visualCaptcha');
            $id = $_POST['id'];

            // check captcha
            $frontendData = $captcha->getFrontendData();
            $captchaError = false;
            if (!$frontendData) {
                $captchaError = _('Invalid Captcha Data');
            } else {
                // If an image field name was submitted, try to validate it
                if ($imageAnswer = $request->getParsedBody()[$frontendData['imageFieldName']]){
                    // If incorrect
                    if (!$captcha->validateImage($imageAnswer)){
                        $captchaError = _('Incorrect Captcha Image. Please try again.');
                    }
                    // Generate new captcha or else the user can just rety the old one
                    $howMany = count($captcha->getImageOptions());
                    $captcha->generate($howMany);
                } else {
                    $captchaError = _('Invalid Captcha Data');
                }
            }

            if ($captchaError !== false) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => $captchaError]);
            }

            // if not allowing drive voting
            if (boolval(getOption('disable_drive_voting'))) {
                // check if has a google drive link
                $checkDrive = $dbh->prepare("SELECT COUNT(*) FROM `links` WHERE (`host` = 'gdrive' OR `host` = 'gdrive_folder') AND `id` = :game_id");
                $checkDrive->bindParam(':game_id', $id, \PDO::PARAM_INT);
                $checkDrive->execute();
                // don't vote if has drive link
                if (intval($checkDrive->fetchColumn(0)) > 0) {
                    return $response->withJson(['SUCCESS' => false, 'MSG' => _('Failed to vote, refresh and try again.')]);
                }
            }

            // check ip + amount of times voted
            $checkVoteCount = $dbh->prepare("SELECT COUNT(*) FROM `votes` WHERE `uid` = INET6_ATON(:ip)");
            $checkVoteCount->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
            $checkVoteCount->execute();
            if ($checkVoteCount->fetchColumn() >= 10) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => _('Your vote limit has exceeded.')]);
            }

            // check if game is old enough
            $chechUploadAge = $dbh->prepare("SELECT `last_upload` FROM `games` WHERE `id` = :game_id
                                        AND IF(DATE_ADD(FROM_UNIXTIME(`last_upload`), INTERVAL 30 DAY) >= NOW(), 0, 1)");
            $chechUploadAge->bindParam(':game_id', $id, \PDO::PARAM_INT);
            $chechUploadAge->execute();
            if ($chechUploadAge->rowCount() < 1) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => _('Game not old enough to vote on.')]);
            }

            // check if game uploading
            $checkGame = $dbh->prepare("SELECT `uploading` FROM `games` WHERE `id` = :game_id");
            $checkGame->bindParam(':game_id', $id, \PDO::PARAM_INT);
            $checkGame->execute();
            if ($checkGame->fetchColumn() == 1) {
                return $response->withJson(['SUCCESS' => false, 'MSG' => _('Game is already uploading.')]);
            }

            $vote = $dbh->prepare("INSERT INTO `votes` (`uid`, `game_id`) VALUES(INET6_ATON(:ip), :game_id)");
            $vote->bindParam(':ip', $ipAddress, \PDO::PARAM_STR);
            $vote->bindParam(':game_id', $id, \PDO::PARAM_INT);
            if ($vote->execute()) {
                $Elastic = new Elastic($CONFIG['ES']['HOSTS']);
                $Elastic->UpdateGame($id);
                return $response->withJson(['SUCCESS' => true]);
            } else {
                return $response->withJson(['SUCCESS' => false, 'MSG' => _('You already voted on this.')]);
            }
        }
        return $response->withJson(['SUCCESS' => false, 'MSG' => 'Invalid Game.']);
    });
});

$app->group('/annoyanator', function () use ($app) {
    // Populates captcha data into session object
    // -----------------------------------------------------------------------------
    // @param howmany is required, the number of images to generate
    $app->get('/begin/{howmany}', function ($request, $response, $args) {
        $captcha = $this->get('visualCaptcha');
        $captcha->generate($args['howmany']);
        return $response->withJson($captcha->getFrontEndData());
    });

    // Streams captcha images from disk
    // -----------------------------------------------------------------------------
    // @param index is required, the index of the image you wish to get
    $app->get('/img/{index}', function ($request, $response, $args) {
        $captcha = $this->get('visualCaptcha');
        $headers = [];
        $image = $captcha->streamImage($headers, $args['index'], false);
        if (!$image) {
            throw new \Slim\Exception\NotFoundException($request, $response);
        } else {
            // Set headers
            foreach ($headers as $key => $val) {
                $response = $response->withHeader($key, $val);
            }
            return $response;
        }
    });
});

$app->run();
