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
 * @author    Daniel Supernault <danielsupernault@gmail.com>
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @copyright 2018 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */
if (!defined ('GNUSOCIAL')) {
        exit (1);
}

// Import required files by the plugin
require_once __DIR__ . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "discoveryhints.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "explorer.php";
require_once __DIR__ . DIRECTORY_SEPARATOR . "utils" . DIRECTORY_SEPARATOR . "postman.php";

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
         * Get remote user's ActivityPub_profile via a identifier
         * (https://www.w3.org/TR/activitypub/#obj-id)
         *
         * @author GNU Social
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param string $arg A remote user identifier
         * @return Activitypub_profile|null Valid profile in success | null otherwise
         */
        protected function pull_remote_profile ($arg)
        {
            if (preg_match ('!^((?:\w+\.)*\w+@(?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+)$!', $arg)) {
                    // webfinger lookup
                    try {
                            return Activitypub_profile::ensure_web_finger ($arg);
                    } catch (Exception $e) {
                            common_log(LOG_ERR, 'Webfinger lookup failed for ' .
                                                $arg . ': ' . $e->getMessage ());
                    }
            }

            // Look for profile URLs, with or without scheme:
            $urls = array ();
            if (preg_match ('!^https?://((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)+)$!', $arg)) {
                    $urls[] = $arg;
            }
            if (preg_match ('!^((?:\w+\.)*\w+(?:\w+\-\w+)*\.\w+(?:/\w+)+)$!', $arg)) {
                    $schemes = array ('http', 'https');
                    foreach ($schemes as $scheme) {
                            $urls[] = "$scheme://$arg";
                    }
            }

            foreach ($urls as $url) {
                    try {
                            return Activitypub_profile::get_from_uri ($url);
                    } catch (Exception $e) {
                            common_log(LOG_ERR, 'Profile lookup failed for ' .
                                                $arg . ': ' . $e->getMessage ());
                    }
                }
                return null;
        }

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
         * @return boolean hook true
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
         *
         * @return boolean hook true
         */
        function onCheckSchema ()
        {
            $schema = Schema::get ();
            $schema->ensureTable ('Activitypub_profile', Activitypub_profile::schemaDef());
            return true;
        }

            /********************************************************
             *                   Discovery Events                   *
             ********************************************************/

        /**
        * Webfinger matches: @user@example.com or even @user--one.george_orwell@1984.biz
        *
        * @author GNU Social
        * @param   string  $text       The text from which to extract webfinger IDs
        * @param   string  $preMention Character(s) that signals a mention ('@', '!'...)
        * @return  array   The matching IDs (without $preMention) and each respective position in the given string.
        */
        static function extractWebfingerIds ($text, $preMention='@')
        {
                $wmatches = array ();
                $result = preg_match_all ('/(?<!\S)'.preg_quote ($preMention, '/').'('.Nickname::WEBFINGER_FMT.')/',
                                $text,
                                $wmatches,
                                PREG_OFFSET_CAPTURE);
                if ($result === false) {
                        common_log (LOG_ERR, __METHOD__ . ': Error parsing webfinger IDs from text (preg_last_error=='.preg_last_error ().').');
                } elseif (count ($wmatches)) {
                        common_debug (sprintf ('Found %d matches for WebFinger IDs: %s', count ($wmatches), _ve ($wmatches)));
                }
                return $wmatches[1];
        }

        /**
         * Profile URL matches: @example.com/mublog/user
         *
         * @author GNU Social
         * @param   string  $text       The text from which to extract URL mentions
         * @param   string  $preMention Character(s) that signals a mention ('@', '!'...)
         * @return  array   The matching URLs (without @ or acct:) and each respective position in the given string.
         */
        static function extractUrlMentions ($text, $preMention='@')
        {
                $wmatches = array ();
                // In the regexp below we need to match / _before_ URL_REGEX_VALID_PATH_CHARS because it otherwise gets merged
                // with the TLD before (but / is in URL_REGEX_VALID_PATH_CHARS anyway, it's just its positioning that is important)
                $result = preg_match_all ('/(?:^|\s+)'.preg_quote ($preMention, '/').'('.URL_REGEX_DOMAIN_NAME.'(?:\/['.URL_REGEX_VALID_PATH_CHARS.']*)*)/',
                                $text,
                                $wmatches,
                                PREG_OFFSET_CAPTURE);
                if ($result === false) {
                        common_log (LOG_ERR, __METHOD__ . ': Error parsing profile URL mentions from text (preg_last_error=='.preg_last_error ().').');
                } elseif (count ($wmatches)) {
                        common_debug(sprintf('Found %d matches for profile URL mentions: %s', count ($wmatches), _ve ($wmatches)));
                }
                return $wmatches[1];
        }

        /**
         * Find any explicit remote mentions. Accepted forms:
         *   Webfinger: @user@example.com
         *   Profile link: @example.com/mublog/user
         *
         * @author GNU Social
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param Profile $sender
         * @param string $text input markup text
         * @param array &$mention in/out param: set of found mentions
         * @return boolean hook return value
         */
        function onEndFindMentions(Profile $sender, $text, &$mentions)
        {
                $matches = array();

                foreach (self::extractWebfingerIds($text, '@') as $wmatch) {
                        list($target, $pos) = $wmatch;
                        $this->log(LOG_INFO, "Checking webfinger person '$target'");
                        $profile = null;
                        try {
                                $aprofile = Activitypub_profile::ensure_web_finger($target);
                                $profile = $aprofile->local_profile();
                        } catch (Exception $e) {
                                $this->log(LOG_ERR, "Webfinger check failed: " . $e->getMessage());
                                continue;
                        }
                        assert ($profile instanceof Profile);

                        $displayName = !empty ($profile->nickname) && mb_strlen ($profile->nickname) < mb_strlen ($target)
                        ? $profile->getNickname ()   // TODO: we could do getBestName() or getFullname() here
                        : $target;
                        $url = $profile->getUri ();
                        if (!common_valid_http_url ($url)) {
                                $url = $profile->getUrl ();
                        }
                        $matches[$pos] = array('mentioned' => array ($profile),
                                               'type' => 'mention',
                                               'text' => $displayName,
                                               'position' => $pos,
                                               'length' => mb_strlen ($target),
                                               'url' => $url);
                }

                foreach (self::extractUrlMentions ($text) as $wmatch) {
                        list ($target, $pos) = $wmatch;
                        $schemes = array('https', 'http');
                        foreach ($schemes as $scheme) {
                                $url = "$scheme://$target";
                                $this->log(LOG_INFO, "Checking profile address '$url'");
                                try {
                                        $aprofile = Activitypub_profile::get_from_uri ($url);
                                        $profile = $aprofile->local_profile();
                                        $displayName = !empty ($profile->nickname) && mb_strlen ($profile->nickname) < mb_strlen ($target) ?
                                        $profile->nickname : $target;
                                        $matches[$pos] = array('mentioned' => array ($profile),
                                                               'type' => 'mention',
                                                               'text' => $displayName,
                                                               'position' => $pos,
                                                               'length' => mb_strlen ($target),
                                                               'url' => $profile->getUrl());
                                        break;
                                } catch (Exception $e) {
                                        $this->log(LOG_ERR, "Profile check failed: " . $e->getMessage());
                                }
                        }
                }

                foreach ($mentions as $i => $other) {
                        // If we share a common prefix with a local user, override it!
                        $pos = $other['position'];
                        if (isset ($matches[$pos])) {
                                $mentions[$i] = $matches[$pos];
                                unset ($matches[$pos]);
                        }
                }
                foreach ($matches as $mention) {
                        $mentions[] = $mention;
                }

                return true;
        }

        /**
         * Allow remote profile references to be used in commands:
         *   sub update@status.net
         *   whois evan@identi.ca
         *   reply http://identi.ca/evan hey what's up
         *
         * @author GNU Social
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param Command $command
         * @param string $arg
         * @param Profile &$profile
         * @return hook return code
         */
        function onStartCommandGetProfile ($command, $arg, &$profile)
        {
                try {
                        $aprofile = $this->pull_remote_profile ($arg);
                        $profile = $aprofile->local_profile();
                } catch (Exception $e) {
                        // No remote ActivityPub profile found
                        return true;
                }

                return false;
        }

        /**
         * Profile URI for remote profiles.
         *
         * @author GNU Social
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param Profile $profile
         * @param string $uri in/out
         * @return mixed hook return code
         */
        function onStartGetProfileUri ($profile, &$uri)
        {
                $aprofile = Activitypub_profile::getKV ('profile_id', $profile->id);
                if ($aprofile instanceof Activitypub_profile) {
                        $uri = $aprofile->get_uri ();
                        return false;
                }
                return true;
        }


        /**
         * Dummy string on AccountProfileBlock stating that ActivityPub is active
         * this is more of a placeholder for eventual useful stuff ._.
         *
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @return void
         */
        function onEndShowAccountProfileBlock (HTMLOutputter $out, Profile $profile)
        {
                if ($profile->isLocal()) {
                        return true;
                }
                try {
                        $aprofile = Activitypub_profile::getKV ('profile_id', $profile->id);
                } catch (NoResultException $e) {
                        // Not a remote ActivityPub_profile! Maybe some other network
                        // that has imported a non-local user (e.g.: OStatus)?
                        return true;
                }

                $out->elementStart('dl', 'entity_tags activitypub_profile');
                $out->element('dt', null, _m('ActivityPub'));
                $out->element('dd', null, _m('Active'));
                $out->elementEnd('dl');
        }

        /**
         * Profile from URI.
         *
         * @author GNU Social
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param string $uri
         * @param Profile &$profile in/out param: Profile got from URI
         * @return mixed hook return code
         */
        function onStartGetProfileFromURI ($uri, &$profile)
        {
                // Don't want to do Web-based discovery on our own server,
                // so we check locally first. This duplicates the functionality
                // in the Profile class, since the plugin always runs before
                // that local lookup, but since we return false it won't run double.

                $user = User::getKV ('uri', $uri);
                if ($user instanceof User) {
                        $profile = $user->getProfile();
                        return false;
                } else {
                        $group = User_group::getKV ('uri', $uri);
                        if ($group instanceof User_group) {
                                $profile = $group->getProfile ();
                                return false;
                        }
                }

                // Now, check remotely
                try {
                        $aprofile = Activitypub_profile::get_from_uri ($uri);
                        $profile = $aprofile->local_profile ();
                        return false;
                } catch (Exception $e) {
                        return true; // It's not an ActivityPub profile as far as we know, continue event handling
                }
        }

            /********************************************************
             *                    Delivery Events                   *
             ********************************************************/

        /**
         * Having established a remote subscription, send a notification to the
         * remote ActivityPub profile's endpoint.
         *
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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

        /**
         * Override the "from ActivityPub" bit in notice lists to link to the
         * original post and show the domain it came from.
         *
         * @author Diogo Cordeiro <diogo@fc.up.pt>
         * @param Notice in $notice
         * @param string out &$name
         * @param string out &$url
         * @param string out &$title
         * @return mixed hook return code
         */
        function onStartNoticeSourceLink ($notice, &$name, &$url, &$title)
        {
            // If we don't handle this, keep the event handler going
            if (!in_array ($notice->source, array('ActivityPub', 'share'))) {
                    return true;
            }

            try {
                    $url = $notice->getUrl();
                    // If getUrl() throws exception, $url is never set

                    $bits = parse_url($url);
                    $domain = $bits['host'];
                    if (substr($domain, 0, 4) == 'www.') {
                        $name = substr($domain, 4);
                    } else {
                        $name = $domain;
                    }

                    // TRANS: Title. %s is a domain name.
                    $title = sprintf(_m('Sent from %s via ActivityPub'), $domain);

                    // Abort event handler, we have a name and URL!
                    return false;
            } catch (InvalidUrlException $e) {
                    // This just means we don't have the notice source data
                    return true;
            }
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
         * @author Diogo Cordeiro <diogo@fc.up.pt>
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
