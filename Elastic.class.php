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

class Elastic
{
 
    private $client = null;
 
    public function __construct()
    {
        $this->client = Elasticsearch\ClientBuilder::create()->build();
    }
    public function Mapping(){
        $params = [
            'index' => 'gg',
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'analysis' => [
                        'filter' => [
                            'autocomplete_filter' => [
                                'type' => 'ngram',
                                'min_gram' => 3,
                                'max_gram' => 15
                            ]
                        ],
                        'analyzer' => [
                            'autocomplete' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => [
                                    'lowercase',
                                    'autocomplete_filter' 
                                ]
                            ],
                            'lowerkey' => [
                                'type' => 'custom',
                                'tokenizer' => 'keyword',
                                'filter' => [
                                    'lowercase'
                                ]
                            ]
                        ],
                        'normalizer' => [
                            'lowernormalizer' => [
                                'type' => 'custom',
                                'filter' => [
                                    'lowercase'
                                ]
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'gg_game' => [
                        'properties' => [
                            'id' => [
                                'type' => 'integer'
                            ],
                            'title' => [
                                'type' => 'text',
                                //'analyzer' => 'autocomplete',
                                'fields' => [
                                    'raw' => [
                                        'type' => 'keyword',
                                        'normalizer' => 'lowernormalizer'
                                    ]
                                ]
                            ],
                            'indev' => [
                                'type' => 'integer'
                            ],
                            'new' => [
                                'type' => 'integer'
                            ],
                            'updated' => [
                                'type' => 'integer'
                            ],
                            'last_upload' => [
                                'type' => 'date',
                                'format' => 'epoch_second'
                            ],
                            'last_update' => [
                                'type' => 'date',
                                'format' => 'epoch_second'
                            ],
                            'has_background' => [
                                'type' => 'integer'
                            ],
                            'has_thumbnail' => [
                                'type' => 'integer'
                            ],
                            'slug' => [
                                'type' => 'text'
                            ],
                            'slug_folder' => [
                                'type' => 'text'
                            ],
                            'url' => [
                                'type' => 'text'
                            ],
                            'release_date' => [
                                'type' => 'date',
                                'format' => 'epoch_second'
                            ],
                            'developer' => [
                                'type' => 'text',
                                'analyzer' => 'lowerkey'
                            ],
                            'publisher' => [
                                'type' => 'text',
                                'analyzer' => 'lowerkey'
                            ],
                            'category' => [
                                'type' => 'keyword'
                            ],
                            'hidden' => [
                                'type' => 'integer'
                            ],
                            'uploading' => [
                                'type' => 'integer'
                            ],
                            'queued' => [
                                'type' => 'integer'
                            ],
                            'old_view' => [
                                'type' => 'boolean'
                            ],
                            'votes' => [
                                'type' => 'integer'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->client->indices()->create($params);
    }
    public function Clear(){
        $this->client->indices()->delete(['index' => 'gg']);
    }
    public function InsertAll(){
        global $dbh;
        $this->Mapping();
        $client = $this->client;
        $getGames = $dbh->prepare("SELECT `game_id`, games.*, COUNT(`game_id`) as `votes`
                                    FROM `games`
                                    LEFT JOIN `votes` ON `game_id` = `id`
                                    WHERE `hidden` = 0
                                    GROUP BY `id`");
        $getGames->execute();
        $games = $getGames->fetchAll(\PDO::FETCH_ASSOC);
        $games = array_chunk($games, 1000);

        foreach ($games as $key_chunk => $game_chunk) {
            $params = [];
            foreach ($game_chunk as $key => $game) {
                $gameId = $game['id'];

                $params['body'][] = array(
                    'index' => array(
                        '_index' => 'gg',
                        '_type' => 'gg_game',
                        '_id' => $gameId
                    ),
                );

                $params['body'][] = [
                    'title' => $game['title'],
                    'indev' => $game['indev'],
                    'new' => $game['new'],
                    'updated' => $game['updated'],
                    'last_upload' => intval($game['last_upload']),
                    'last_update' => intval($game['last_update']),
                    'has_background' => $game['has_background'],
                    'has_thumbnail' => $game['has_thumbnail'],
                    'slug' => $game['slug'],
                    'slug_folder' => $game['slug_folder'],
                    'url' => $game['url'],
                    'release_date' => $game['release_date'],
                    'developer' => $game['developer'],
                    'publisher' => $game['publisher'],
                    'category' => $game['category'],
                    'hidden' => $game['hidden'],
                    'uploading' => $game['uploading'],
                    'queued' => $game['queued'],
                    'votes' => $game['votes']
                ];
            }
            $responses = $client->bulk($params);
            unset($responses);
            $params = [];
        }
        return true;
    }
    public function UpdateGame($gameId)
    {
        global $dbh;
        try {
            if ($gameId == null) {
                throw new \Exception("Game ID is null");
            }

            $getGame = $dbh->prepare("SELECT `game_id`, games.*, COUNT(`game_id`) as `votes`
                                        FROM `games`
                                        LEFT JOIN `votes` ON `game_id` = `id`
                                        WHERE `id` = :game_id
                                        GROUP BY `id`");
            $getGame->bindParam(':game_id', $gameId, \PDO::PARAM_INT);
            $getGame->execute();
            $game = $getGame->fetch(\PDO::FETCH_ASSOC);
            if ($game['hidden'] == 1) {
                return $this->client->delete(['index' => 'gg','type' => 'gg_game','id' => $gameId]);
            }
            $gameId = $game['id'];

            $params = [
                'index' => 'gg',
                'type' => 'gg_game',
                'id' => $gameId,
                'body' => [
                    'doc' => [
                        'title' => $game['title'],
                        'indev' => $game['indev'],
                        'new' => $game['new'],
                        'updated' => $game['updated'],
                        'last_upload' => intval($game['last_upload']),
                        'last_update' => intval($game['last_update']),
                        'has_background' => $game['has_background'],
                        'has_thumbnail' => $game['has_thumbnail'],
                        'slug' => $game['slug'],
                        'slug_folder' => $game['slug_folder'],
                        'url' => $game['url'],
                        'release_date' => $game['release_date'],
                        'developer' => $game['developer'],
                        'publisher' => $game['publisher'],
                        'category' => $game['category'],
                        'hidden' => $game['hidden'],
                        'uploading' => $game['uploading'],
                        'queued' => $game['queued'],
                        'votes' => $game['votes']
                    ],
                    'doc_as_upsert' => true
                ]
            ];
            $responses = $this->client->update($params);
        } catch(\Exception $e){
            return $e->getMessage();
        }
        return $responses;
    }
    public function msearch($params){
        return $this->client->msearch($params);
    }
    public function search($params){
        return $this->client->search($params);
    }
    public function count($params){
        return $this->client->count($params);
    }
}