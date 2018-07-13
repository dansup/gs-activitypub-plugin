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
 * ActivityPub Profile
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_profile extends Profile
{
        public $__table = 'Activitypub_profile';

        protected $_profile = null;

        /**
         * Return table definition for Schema setup and DB_DataObject usage.
         *
         * @return array array of column definitions
         */
        static function schemaDef ()
        {
            return array (
                'fields' => array (
                    'uri' => array ('type' => 'varchar', 'length' => 191, 'not null' => true),
                    'profile_id' => array ('type' => 'integer'),
                    'inboxuri' => array ('type' => 'varchar', 'length' => 191),
                    'sharedInboxuri' => array ('type' => 'varchar', 'length' => 191),
                    'created' => array ('type' => 'datetime', 'not null' => true),
                    'modified' => array ('type' => 'datetime', 'not null' => true),
                ),
                'primary key' => array ('uri'),
                'unique keys' => array (
                    'Activitypub_profile_profile_id_key' => array ('profile_id'),
                    'Activitypub_profile_inboxuri_key' => array ('inboxuri'),
                ),
                'foreign keys' => array (
                    'Activitypub_profile_profile_id_fkey' => array ('profile', array ('profile_id' => 'id')),
                ),
            );
        }

        /**
         * Generates a pretty profile from a Profile object
         *
         * @param Profile $profile
         * @return pretty array to be used in a response
         */
        public static function profile_to_array ($profile)
        {
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
                        'summary'         => ($desc = $profile->getDescription ()) == null ? "" : $desc,
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

        /**
         * Insert the current objects variables into the database
         *
         * @access public
         * @throws ServerException
         */
        public function doInsert ()
        {
                $profile = new Profile ();

                $profile->created = $this->created = $this->modified = common_sql_now ();

                $fields = array (
                            'uri'      => 'profileurl',
                            'nickname' => 'nickname',
                            'fullname' => 'fullname',
                            'bio'      => 'bio'
                            );

                foreach ($fields as $af => $pf) {
                        $profile->$pf = $this->$af;
                }

                $this->profile_id = $profile->insert ();
                if ($this->profile_id === false) {
                        $profile->query ('ROLLBACK');
                        throw new ServerException ('Profile insertion failed.');
                }

                $ok = $this->insert ();

                if ($ok === false) {
                        $profile->query ('ROLLBACK');
                        throw new ServerException ('Cannot save ActivityPub profile.');
                }
        }

        /**
         * Fetch the locally stored profile for this Activitypub_profile
         * @return Profile
         * @throws NoProfileException if it was not found
         */
        public function localProfile ()
        {
                $profile = Profile::getKV ('id', $this->profile_id);
                if (!$profile instanceof Profile) {
                        throw new NoProfileException ($this->profile_id);
                }
                return $profile;
        }

        /**
         * Generates an Activitypub_profile from a Profile
         *
         * @param Profile $profile
         * @return Activitypub_profile
         * @throws Exception if no Activitypub_profile exists for given Profile
         */
        static function fromProfile (Profile $profile)
        {
                $profile_id = $profile->getID ();

                $aprofile = Activitypub_profile::getKV ('profile_id', $profile_id);
                if (!$aprofile instanceof Activitypub_profile) {
                        throw new Exception('No Activitypub_profile for Profile ID: '.$profile_id);
                }

                foreach ($profile as $key => $value) {
                        $aprofile->$key = $value;
                }

                return $aprofile;
        }

        /**
         * Returns sharedInbox if possible, inbox otherwise
         *
         * @return string Inbox URL
         */
        public function getInbox ()
        {
                if (is_null ($this->sharedInboxuri)) {
                        return $this->inboxuri;
                }

                return $this->sharedInboxuri;
        }
}
