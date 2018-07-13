<?php
require_once dirname (__DIR__) . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "explorer.php";
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
 * Shared Inbox Handler
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apSharedInboxAction extends ManagedAction
{
        protected $needLogin = false;
        protected $canPost   = true;

        /**
         * Handle the Shared Inbox request
         *
         * @return void
         */
        protected function handle ()
        {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        ActivityPubReturn::error ("Only POST requests allowed.");
                }

                $data = json_decode (file_get_contents ('php://input'));

                // Validate data
                if (!isset($data->type)) {
                        ActivityPubReturn::error ("Type was not specified.");
                }
                if (!isset($data->actor)) {
                        ActivityPubReturn::error ("Actor was not specified.");
                }
                if (!isset($data->object)) {
                        ActivityPubReturn::error ("Object was not specified.");
                }

                $discovery = new Activitypub_explorer;

                // Get valid Actor object
                try {
                        $actor_profile = $discovery->lookup ($data->actor);
                        $actor_profile = $actor_profile[0];
                } catch (Exception $e) {
                        ActivityPubReturn::error ("Invalid Actor.", 404);
                }

                unset ($discovery);

                // Public To:
                $public_to = array ("https://www.w3.org/ns/activitystreams#Public",
                                    "Public",
                                    "as:Public");

                // Process request
                switch ($data->type) {
                        case "Create":
                                if (!isset($data->to)) {
                                        ActivityPubReturn::error ("To was not specified.");
                                }
                                $discovery = new Activitypub_Discovery;
                                $to_profiles = array ();
                                // Generate To objects
                                if (is_array ($data->to)) {
                                        // Remove duplicates from To actors set
                                        array_unique ($data->to);
                                        foreach ($data->to as $to_url) {
                                                try {
                                                        $to_profiles = array_merge ($to_profiles, $discovery->lookup ($to_url));
                                                } catch (Exception $e) {
                                                        // XXX: Invalid actor found, not sure how we handle those
                                                }
                                        }
                                } else if (empty ($data->to) || in_array ($data->to, $public_to)) {
                                        // No need to do anything else at this point, let's just break out the if
                                } else {
                                        try {
                                                $to_profiles[]= $discovery->lookup ($data->to);
                                        } catch (Exception $e) {
                                                ActivityPubReturn::error ("Invalid Actor.", 404);
                                        }
                                }
                                unset ($discovery);
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Create.php";
                                break;
                        case "Follow":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Follow.php";
                                break;
                        case "Like":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Like.php";
                                break;
                        case "Announce":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Announce.php";
                                break;
                        case "Undo":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Undo.php";
                                break;
                        default:
                                ActivityPubReturn::error ("Invalid type value.");
                }
    }
}
