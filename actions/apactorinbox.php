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
 * Actor's Inbox
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apActorInboxAction extends ManagedAction
{
        protected $needLogin = false;
        protected $canPost   = true;

        /**
         * Handle the Actor Inbox request
         *
         * @return void
         */
        protected function handle ()
        {
                $nickname  = $this->trimmed ('nickname');
                try {
                        $user    = User::getByNickname ($nickname);
                        $profile = $user->getProfile ();
                        $url     = $profile->profileurl;
                } catch (Exception $e) {
                        ActivityPubReturn::error ("Invalid username.");
                }

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        ActivityPubReturn::error ("C2S not implemented just yet.");
                }

                $data = json_decode (file_get_contents ('php://input'));

                // Validate data
                if (!(isset($data->type))) {
                        ActivityPubReturn::error ("Type was not specified.");
                }
                if (!isset($data->actor)) {
                        ActivityPubReturn::error ("Actor was not specified.");
                }
                if (!isset($data->object)) {
                        ActivityPubReturn::error ("Object was not specified.");
                }

                // Get valid Actor object
                try {
                        require_once dirname (__DIR__) . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "explorer.php";
                        $actor_profile = new Activitypub_explorer;
                        $actor_profile = $actor_profile->lookup ($data->actor);
                        $actor_profile = $actor_profile[0];
                } catch (Exception $e) {
                        ActivityPubReturn::error ("Invalid Actor.", 404);
                }

                $to_profiles = array ($user);

                // Process request
                switch ($data->type) {
                        case "Create":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Create.php";
                                break;
                        case "Delete":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Delete.php";
                                break;
                        case "Follow":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Follow.php";
                                break;
                        case "Like":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Like.php";
                                break;
                        case "Undo":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Undo.php";
                                break;
                        case "Announce":
                                require_once __DIR__ . DIRECTORY_SEPARATOR . "inbox" . DIRECTORY_SEPARATOR . "Announce.php";
                                break;
                        default:
                                ActivityPubReturn::error ("Invalid type value.");
                }
        }
}
