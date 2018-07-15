<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "postman.php";
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
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
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
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class ActivityPubPlugin extends Plugin
{
        /**
         * Route/Reroute urls
         *
         * @param URLMapper $m
         * @return void
         */
        public function onRouterInitialized (URLMapper $m)
        {
                ActivityPubURLMapperOverwrite::overwrite_variable ($m, ':nickname',
                                            ['action' => 'showstream'],
                                            ['nickname' => Nickname::DISPLAY_FMT],
                                            'apActorProfile');

                $m->connect (':nickname/liked.json',
                            ['action'    => 'apActorLikedCollection'],
                            ['nickname'  => Nickname::DISPLAY_FMT]);

                $m->connect (':nickname/followers.json',
                            ['action'    => 'apActorFollowers'],
                            ['nickname'  => Nickname::DISPLAY_FMT]);

                $m->connect (':nickname/following.json',
                            ['action'    => 'apActorFollowing'],
                            ['nickname'  => Nickname::DISPLAY_FMT]);

                $m->connect (':nickname/inbox.json',
                            ['action' => 'apActorInbox'],
                            ['nickname' => Nickname::DISPLAY_FMT]);

                $m->connect ('inbox.json',
                            array('action' => 'apSharedInbox'));
        }

        /**
         * Plugin version information
         *
         * @param array $versions
         * @return boolean true
         */
        public function onPluginVersion (array &$versions)
        {
                $versions[] = [ 'name' => 'ActivityPub',
                                'version' => GNUSOCIAL_VERSION,
                                'author' => 'Daniel Supernault, Diogo Cordeiro',
                                'homepage' => 'https://www.gnu.org/software/social/',
                                'rawdescription' =>
                                // Todo: Translation
                                'Adds ActivityPub Support'];

                return true;
        }

        /**
         * Make sure necessary tables are filled out.
         */
        function onCheckSchema ()
        {
            $schema = Schema::get ();
            $schema->ensureTable ('Activitypub_profile', Activitypub_profile::schemaDef());
            return true;
        }

            /********************************************************
             *                    Delivery Events                   *
             ********************************************************/

        /**
         * Having established a remote subscription, send a notification to the
         * remote ActivityPub profile's endpoint.
         *
         * @param Profile $profile  subscriber
         * @param Profile $other    subscribee
         * @return hook return value
         * @throws Exception
         */
        function onEndSubscribe (Profile $profile, Profile $other)
        {
                if (!$profile->isLocal () || $other->isLocal ()) {
                        return true;
                }

                try {
                        $other = Activitypub_profile::from_profile ($other);
                } catch (Exception $e) {
                        return true;
                }

                $postman = new Activitypub_postman ($profile, array ($other));

                $postman->follow ();

                return true;
        }

        /**
         * Notify remote server on unsubscribe.
         *
         * @param Profile $profile
         * @param Profile $other
         * @return hook return value
         */
        function onEndUnsubscribe (Profile $profile, Profile $other)
        {
                if (!$profile->isLocal () || $other->isLocal ()) {
                        return true;
                }

                try {
                        $other = Activitypub_profile::from_profile ($other);
                } catch (Exception $e) {
                        return true;
                }

                $postman = new Activitypub_postman ($profile, array ($other));

                $postman->undo_follow ();

                return true;
        }

        /**
         * Notify remote users when their notices get favorited.
         *
         * @param Profile $profile of local user doing the faving
         * @param Notice $notice Notice being favored
         * @return hook return value
         */
        function onEndFavorNotice (Profile $profile, Notice $notice)
        {
                // Only distribute local users' favor actions, remote users
                // will have already distributed theirs.
                if (!$profile->isLocal ()) {
                        return true;
                }

                $other = array ();
                try {
                        $other[] = Activitypub_profile::from_profile($notice->getProfile ());
                } catch (Exception $e) {
                        // Local user can be ignored
                }
                foreach ($notice->getAttentionProfiles() as $to_profile) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($to_profile);
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                }
                if ($notice->reply_to) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($notice->getParent ()->getProfile ());
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                        try {
                                $mentions = $notice->getParent ()->getAttentionProfiles ();
                                foreach ($mentions as $to_profile) {
                                        try {
                                                $other[] = Activitypub_profile::from_profile ($to_profile);
                                        } catch (Exception $e) {
                                                // Local user can be ignored
                                        }
                                }
                        } catch (NoParentNoticeException $e) {
                                // This is not a reply to something (has no parent)
                        } catch (NoResultException $e) {
                                // Parent author's profile not found! Complain louder?
                                common_log(LOG_ERR, "Parent notice's author not found: ".$e->getMessage());
                        }
                }

                $postman = new Activitypub_postman ($profile, $other);

                $postman->like ($notice);

                return true;
        }

        /**
         * Notify remote users when their notices get de-favorited.
         *
         * @param Profile $profile of local user doing the de-faving
         * @param Notice  $notice  Notice being favored
         * @return hook return value
         */
        function onEndDisfavorNotice (Profile $profile, Notice $notice)
        {
                // Only distribute local users' favor actions, remote users
                // will have already distributed theirs.
                if (!$profile->isLocal ()) {
                        return true;
                }

                $other = array ();
                try {
                        $other[] = Activitypub_profile::from_profile($notice->getProfile ());
                } catch (Exception $e) {
                        // Local user can be ignored
                }
                foreach ($notice->getAttentionProfiles() as $to_profile) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($to_profile);
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                }
                if ($notice->reply_to) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($notice->getParent ()->getProfile ());
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                        try {
                                $mentions = $notice->getParent ()->getAttentionProfiles ();
                                foreach ($mentions as $to_profile) {
                                        try {
                                                $other[] = Activitypub_profile::from_profile ($to_profile);
                                        } catch (Exception $e) {
                                                // Local user can be ignored
                                        }
                                }
                        } catch (NoParentNoticeException $e) {
                                // This is not a reply to something (has no parent)
                        } catch (NoResultException $e) {
                                // Parent author's profile not found! Complain louder?
                                common_log(LOG_ERR, "Parent notice's author not found: ".$e->getMessage());
                        }
                }

                $postman = new Activitypub_postman ($profile, $other);

                $postman->undo_like ($notice);

                return true;
        }

        /**
         * Notify remote users when their notices get deleted
         *
         * @return boolean hook flag
         */
        public function onEndDeleteOwnNotice ($user, $notice)
        {
                $profile = $user->getProfile ();

                // Only distribute local users' delete actions, remote users
                // will have already distributed theirs.
                if (!$profile->isLocal ()) {
                        return true;
                }

                $other = array ();

                foreach ($notice->getAttentionProfiles() as $to_profile) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($to_profile);
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                }
                if ($notice->reply_to) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($notice->getParent ()->getProfile ());
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                        try {
                                $mentions = $notice->getParent ()->getAttentionProfiles ();
                                foreach ($mentions as $to_profile) {
                                        try {
                                                $other[] = Activitypub_profile::from_profile ($to_profile);
                                        } catch (Exception $e) {
                                                // Local user can be ignored
                                        }
                                }
                        } catch (NoParentNoticeException $e) {
                                // This is not a reply to something (has no parent)
                        } catch (NoResultException $e) {
                                // Parent author's profile not found! Complain louder?
                                common_log(LOG_ERR, "Parent notice's author not found: ".$e->getMessage());
                        }
                }

                $postman = new Activitypub_postman ($profile, $other);
                $postman->delete ($notice);
                return true;
        }

        /**
         * Insert notifications for replies, mentions and repeats
         *
         * @return boolean hook flag
         */
        function onStartNoticeDistribute ($notice)
        {
                assert ($notice->id > 0);        // Ignore if not a valid notice

                $profile = Profile::getKV ($notice->profile_id);

                $other = array ();
                try {
                        $other[] = Activitypub_profile::from_profile($notice->getProfile ());
                } catch (Exception $e) {
                        // Local user can be ignored
                }
                foreach ($notice->getAttentionProfiles() as $to_profile) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($to_profile);
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                }

                // Is Announce
                if ($notice->isRepeat ()) {
                        $repeated_notice = Notice::getKV ('id', $notice->repeat_of);
                        if ($repeated_notice instanceof Notice) {
                                try {
                                        $other[] = Activitypub_profile::from_profile ($repeated_notice->getProfile ());
                                } catch (Exception $e) {
                                        // Local user can be ignored
                                }

                                $postman = new Activitypub_postman ($profile, $other);

                                // That was it
                                $postman->announce ($repeated_notice);
                                return true;
                        }
                }

                // Ignore for activity/non-post-verb notices
                if (method_exists ('ActivityUtils', 'compareVerbs')) {
                        $is_post_verb = ActivityUtils::compareVerbs ($notice->verb,
                                                                     array (ActivityVerb::POST));
                } else {
                        $is_post_verb = ($notice->verb == ActivityVerb::POST ? true : false);
                }
                if ($notice->source == 'activity' || !$is_post_verb) {
                        return true;
                }

                // Create
                if ($notice->reply_to) {
                        try {
                                $other[] = Activitypub_profile::from_profile ($notice->getParent ()->getProfile ());
                        } catch (Exception $e) {
                                // Local user can be ignored
                        }
                        try {
                                $mentions = $notice->getParent ()->getAttentionProfiles ();
                                foreach ($mentions as $to_profile) {
                                        try {
                                                $other[] = Activitypub_profile::from_profile ($to_profile);
                                        } catch (Exception $e) {
                                                // Local user can be ignored
                                        }
                                }
                        } catch (NoParentNoticeException $e) {
                                // This is not a reply to something (has no parent)
                        } catch (NoResultException $e) {
                                // Parent author's profile not found! Complain louder?
                                common_log(LOG_ERR, "Parent notice's author not found: ".$e->getMessage());
                        }
                }
                $postman = new Activitypub_postman ($profile, $other);

                // That was it
                $postman->create ($notice);
                return true;
        }
}

/**
 * Overwrites variables in URL-mapping
 */
class ActivityPubURLMapperOverwrite extends URLMapper
{
        static function overwrite_variable ($m, $path, $args, $paramPatterns, $newaction) {
                $mimes = [
                    'application/activity+json',
                    'application/ld+json',
                    'application/ld+json; profile="https://www.w3.org/ns/activitystreams"'
                ];

                if (in_array ($_SERVER["HTTP_ACCEPT"], $mimes) == false) {
                        return true;
                }

                $m->connect ($path, array('action' => $newaction), $paramPatterns);
                $regex = self::makeRegex($path, $paramPatterns);
                foreach ($m->variables as $n => $v) {
                        if ($v[1] == $regex) {
                                $m->variables[$n][0]['action'] = $newaction;
                        }
                }
        }
}

/**
 * Plugin return handler
 */
class ActivityPubReturn
{
        /**
         * Return a valid answer
         *
         * @param array $res
         * @return void
         */
        static function answer ($res)
        {
                header ('Content-Type: application/activity+json');
                echo json_encode ($res, JSON_UNESCAPED_SLASHES | (isset ($_GET["pretty"]) ? JSON_PRETTY_PRINT : null));
                exit;
        }

        /**
         * Return an error
         *
         * @param string $m
         * @param int32 $code
         * @return void
         */
        static function error ($m, $code = 500)
        {
                http_response_code ($code);
                header ('Content-Type: application/activity+json');
                $res[] = Activitypub_error::error_message_to_array ($m);
                echo json_encode ($res, JSON_UNESCAPED_SLASHES);
                exit;
        }
}
