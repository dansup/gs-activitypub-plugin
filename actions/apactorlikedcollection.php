<?php
/**
 * GNU social - a federating social network
 *
 * Todo: Description
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @copyright 2015 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class apActorLikedCollectionAction extends ManagedAction
{
    protected $needLogin = false;
    protected $canPost   = true;

    protected function handle()
    {
        $nickname = $this->trimmed('nickname');
        try {
          $user    = User::getByNickname($nickname);
          $profile = $user->getProfile();
          $url     = $profile->profileurl;
        } catch (Exception $e) {
          throw new \Exception('Invalid username');
        }
        
        $limit    = intval($this->trimmed('limit'));
        $since_id = intval($this->trimmed('since_id'));
        $max_id   = intval($this->trimmed('max_id'));

        $limit    = empty ($limit) ? 40 : $limit; // Default is 40
        $since_id = empty ($since_id) ? null : $since_id;
        $max_id   = empty ($max_id) ? null : $max_id;
        
        if ($limit > 80) $limit = 80; // Max is 80
        
        $fave = $this->fetch_faves ($user->getID(), $limit, $since_id, $max_id);

        $faves = [];
        while ($fave->fetch())
          $faves[] = $this->pretty_fave (clone($fave));
        
        $res = [
          '@context'          => [
            "https://www.w3.org/ns/activitystreams",
            [
              "@language" => "en"
            ]
          ],
          'id'                => "{$url}/liked.json",
          'type'              => 'OrderedCollection',
          'totalItems'        => Fave::countByProfile ($profile),
          'orderedItems'      => $faves
        ];

        header('Content-Type: application/json');

        echo json_encode($res, JSON_UNESCAPED_SLASHES | (isset($_GET["pretty"]) ? JSON_PRETTY_PRINT : null));
    }
    
    protected function pretty_fave ($fave_object)
    {
        $res = array ("uri"       => $fave_object->uri,
                      "created"   => $fave_object->created,
                      "object"    => Activitypub_notice::noticeToObject(Notice::getByID($fave_object->notice_id)));
        
        return $res;
    }
    
    private static function fetch_faves ($user_id, $limit = 40, $since_id = null, $max_id = null)
    {
        $fav = new Fave();

        $fav->user_id = $user_id;
        
        $fav->orderBy('modified DESC');

        if ($since_id != null)
            $fav->whereAdd("notice_id  > {$since_id}");
        if ($max_id != null)
            $fav->whereAdd("notice_id  < {$max_id}");
        
        $fav->limit($limit);

        $fav->find();

        return $fav;
    }
}
