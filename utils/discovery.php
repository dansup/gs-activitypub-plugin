<?php

use Profile;

/**
 * GNU social - a federating social network
 *
 * An activity
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
 * @category  Feed
 * @package   GNUsocial
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2018 Free Software Foundaction, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://gnu.io/social
 */

if (!defined('GNUSOCIAL')) { exit(1); }

class Activitypub_Discovery {
    private $discovered_actor_profiles = array ();
    
    public function lookup ($url)
    {
        $this->discovered_actor_profiles = array ();
        
        return $this->_lookup ($url);;
    }
    
    private function _lookup ($url)
    {
        // First check if we already have it locally and, if so, return it
        // If the local fetch fails: grab it remotely, store locally and return
        $this->grab_local_user ($url) || $this->grab_remote_user($url);

        return $this->discovered_actor_profiles;
    }
    
    private function grab_local_user ($url) 
    {
        if (($actor_profile = Profile::getKV ("profileurl", $url)) != false) {
            $this->discovered_actor_profiles[]= $actor_profile;
            return true;
        } else { // XXX:
            // Sometimes it is not true that the user is not locally available,
            // mostly when it is a local user and URLs slightly changed
            // e.g.: GS instance owner changed from standard urls to pretty urls
            // (not sure if this is necessary, but anyway)

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
    
    private function grab_remote_user ($url)
    {
        $client         = new HTTPClient ();
        $headers        = array();
        $headers[]      = 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        $headers[]      = 'User-Agent: GNUSocialBot v0.1 - https://gnu.io/social';
        $response       = $client->get ($url, $headers);
        if (!$response->isOk()) {
            throw new NoResultException ("Invalid Actor URL.");
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
    
    private function store_profile ($res) {
        $profile             = new Profile;
        $profile->profileurl = $res["url"];
        $profile->nickname   = $res["nickname"];
        $profile->fullname   = $res["display_name"];
        $profile->bio        = substr ($res["summary"], 0, 1000);
        $profile->insert ();
        
        return $profile;
    }

    private function validate_remote_response ($res)
    {
        if (!isset ($res["url"], $res["nickname"], $res["display_name"], $res["summary"])) {
            return false;
        }
        
        return true;
    }
}
