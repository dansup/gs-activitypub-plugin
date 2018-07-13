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
 * ActivityPub's own Explorer
 *
 * Allows to discovery new (or the same) ActivityPub profiles
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_explorer
{
        private $discovered_actor_profiles = array ();

        /**
         * Get every profile from the given URL
         * This function cleans the $this->discovered_actor_profiles array
         * so that there is no erroneous data
         *
         * @param string $url User's url
         * @return array of Profile objects
         */
        public function lookup ($url)
        {
                $this->discovered_actor_profiles = array ();

                return $this->_lookup ($url);
        }

        /**
         * Get every profile from the given URL
         * This is a recursive function that will accumulate the results on
         * $discovered_actor_profiles array
         *
         * @param string $url User's url
         * @return array of Profile objects
         */
        private function _lookup ($url)
        {
                // First check if we already have it locally and, if so, return it
                // If the local fetch fails: grab it remotely, store locally and return
                if (! ($this->grab_local_user ($url) || $this->grab_remote_user ($url))) {
                    throw new Exception ("User not found");
                }


                return $this->discovered_actor_profiles;
        }

        /**
         * Get a local user profiles from its URL and joins it on
         * $this->discovered_actor_profiles
         *
         * @param string $url User's url
         * @return boolean success state
         */
        private function grab_local_user ($url)
        {
                if (($actor_profile = self::get_profile_by_url ($url)) != false) {
                        $this->discovered_actor_profiles[]= $actor_profile;
                        return true;
                } else {
                        /******************************** XXX: ********************************
                         * Sometimes it is not true that the user is not locally available,   *
                         * mostly when it is a local user and URLs slightly changed           *
                         * e.g.: GS instance owner changed from standard urls to pretty urls  *
                         * (not sure if this is necessary, but anyway)                        *
                         **********************************************************************/

                        // Iff we really are in the same instance
                        $root_url_len = strlen (common_root_url ());
                        if (substr ($url, 0, $root_url_len) == common_root_url ()) {
                                // Grab the nickname and try to get the user
                                if (($actor_profile = Profile::getKV ("nickname", substr ($url, $root_url_len))) != false) {
                                        $this->discovered_actor_profiles[]= $actor_profile;
                                        return true;
                                }
                        }
                }
                return false;
        }

        /**
         * Get a remote user(s) profile(s) from its URL and joins it on
         * $this->discovered_actor_profiles
         *
         * @param string $url User's url
         * @return boolean success state
         */
        private function grab_remote_user ($url)
        {
                $client    = new HTTPClient ();
                $headers   = array();
                $headers[] = 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
                $headers[] = 'User-Agent: GNUSocialBot v0.1 - https://gnu.io/social';
                $response  = $client->get ($url, $headers);
                if (!$response->isOk ()) {
                    throw new Exception ("Invalid Actor URL.");
                }
                $res = json_decode ($response->getBody (), JSON_UNESCAPED_SLASHES);
                if (isset ($res["orderedItems"])) { // It's a potential collection of actors!!!
                        foreach ($res["orderedItems"] as $profile) {
                                if ($this->_lookup ($profile) == false) {
                                        // XXX: Invalid actor found, not sure how we handle those
                                }
                        }
                        // Go through entire collection
                        if (!is_null ($res["next"])) {
                                $this->_lookup ($res["next"]);
                        }
                        return true;
                } else if ($this->validate_remote_response ($res)) {
                        $this->discovered_actor_profiles[]= $this->store_profile ($res);
                        return true;
                }

                return false;
        }

        /**
         * Save remote user profile in local instance
         *
         * @param array $res remote response
         * @return Profile remote Profile object
         */
        private function store_profile ($res)
        {
                $aprofile                 = new Activitypub_profile;
                $aprofile->uri            = $res["url"];
                $aprofile->nickname       = $res["nickname"];
                $aprofile->fullname       = $res["display_name"];
                $aprofile->bio            = substr ($res["summary"], 0, 1000);
                $aprofile->inboxuri       = $res["inbox"];
                $aprofile->sharedInboxuri = $res["sharedInbox"];

                $aprofile->do_insert ();

                return $aprofile->local_profile ();
        }

        /**
         * Validates a remote response in order to determine whether this
         * response is a valid profile or not
         *
         * @param array $res remote response
         * @return boolean success state
         */
        private function validate_remote_response ($res)
        {
                if (!isset ($res["url"], $res["nickname"], $res["display_name"], $res["summary"], $res["inbox"], $res["sharedInbox"])) {
                        return false;
                }

                return true;
        }

        /**
         * Get a profile from it's profileurl
         * Unfortunately GNU Social cache is not truly reliable when handling
         * potential ActivityPub remote profiles, as so it is important to use
         * this hacky workaround (at least for now)
         *
         * @param string $v URL
         * @return boolean|Profile false if fails | Profile object if successful
         */
        static function get_profile_by_url ($v)
        {
                $i = Managed_DataObject::getcached(Profile, "profileurl", $v);
                if (empty ($i)) { // false = cache miss
                        $i = new Profile;
                        $result = $i->get ("profileurl", $v);
                        if ($result) {
                                // Hit!
                                $i->encache();
                        } else {
                            return false;
                        }
                }
                return $i;
        }
}
