<?php
/**
 * GNU social - a federating social network
 *
 * ActivityPubPlugin implementation for GNU Social
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
 * @copyright 2018 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */
if (!defined ('GNUSOCIAL')) {
        exit (1);
}

/**
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apActorLikedCollectionAction extends ManagedAction
{
        protected $needLogin = false;
        protected $canPost   = true;

        /**
         * Handle the Liked Collection request
         *
         * @return void
         */
        protected function handle () {
                $nickname = $this->trimmed ('nickname');
                try {
                        $user    = User::getByNickname ($nickname);
                        $profile = $user->getProfile ();
                        $url     = $profile->profileurl;
                } catch (Exception $e) {
                        ActivityPubReturn::error ('Invalid username');
                }

                $limit    = intval ($this->trimmed ('limit'));
                $since_id = intval ($this->trimmed ('since_id'));
                $max_id   = intval ($this->trimmed ('max_id'));

                $limit    = empty ($limit) ? 40 : $limit;       // Default is 40
                $since_id = empty ($since_id) ? null : $since_id;
                $max_id   = empty ($max_id) ? null : $max_id;

                // Max is 80
                if ($limit > 80) {
                        $limit = 80;
                }

                $fave = $this->fetch_faves($user->getID(), $limit, $since_id, $max_id);

                $faves = array();
                while ($fave->fetch ()) {
                        $faves[] = $this->pretty_fave (clone ($fave));
                }

                $res = [
                  '@context'          => [
                    "https://www.w3.org/ns/activitystreams",
                    [
                      "@language" => "en"
                    ]
                  ],
                  'id'           => "{$url}/liked.json",
                  'type'         => 'OrderedCollection',
                  'totalItems'   => Fave::countByProfile ($profile),
                  'orderedItems' => $faves
                ];

                ActivityPubReturn::answer ($res);
        }

        /**
         * Take a fave object and turns it in a pretty array to be used
         * as a plugin answer
         *
         * @param \Fave $fave_object
         * @return array pretty array representating a Fave
         */
        protected function pretty_fave ($fave_object) {
                $res = array("uri" => $fave_object->uri,
                             "created" => $fave_object->created,
                             "object" => Activitypub_notice::noticeToObject (Notice::getByID ($fave_object->notice_id)));

                return $res;
        }

        /**
         * Fetch faves
         *
         * @param int32 $user_id
         * @param int32 $limit
         * @param int32 $since_id
         * @param int32 $max_id
         * @return \Fave fetchable fave collection
         */
        private static function fetch_faves ($user_id, $limit = 40, $since_id = null,
                                             $max_id = null) {
                $fav = new Fave ();

                $fav->user_id = $user_id;

                $fav->orderBy ('modified DESC');

                if ($since_id != null) {
                        $fav->whereAdd ("notice_id  > {$since_id}");
                }

                if ($max_id != null) {
                        $fav->whereAdd ("notice_id  < {$max_id}");
                }

                $fav->limit ($limit);

                $fav->find ();

                return $fav;
        }
}
