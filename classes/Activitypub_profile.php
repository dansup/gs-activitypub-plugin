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
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_profile extends Managed_DataObject
{
        /**
         * Generates a pretty profile from a Profile object
         *
         * @param \Profile $profile
         * @return pretty array to be used in a response
         */
        public static function profileToObject($profile) {
                $url = $profile->getURL ();
                $res = [
                        '@context'        => [
                                "https://www.w3.org/ns/activitystreams",
                                [
                                        "@language"   => "en"
                                ]
                        ],
                        'id'              => $profile->getID (),
                        'type'            => 'Person',
                        'nickname'        => $profile->getNickname (),
                        'is_local'        => $profile->isLocal (),
                        'inbox'           => "{$url}/inbox.json",
                        'sharedInbox'     => common_root_url ()."inbox.json",
                        'outbox'          => "{$url}/outbox.json",
                        'display_name'    => $profile->getFullname (),
                        'followers'       => "{$url}/followers.json",
                        'followers_count' => $profile->subscriberCount (),
                        'following'       => "{$url}/following.json",
                        'following_count' => $profile->subscriptionCount (),
                        'liked'           => "{$url}/liked.json",
                        'liked_count'     => Fave::countByProfile ($profile),
                        'summary'         => $profile->getDescription (),
                        'url'             => $profile->getURL (),
                        'avatar'          => [
                                'type'   => 'Image',
                                'width'  => 96,
                                'height' => 96,
                                'url'    => $profile->avatarUrl (AVATAR_PROFILE_SIZE)
                        ]
                ];
                return $res;
        }
}
