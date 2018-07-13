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
if (!defined('GNUSOCIAL')) {
        exit(1);
}

/**
 * ActivityPub Feed
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class apFeedAction extends ManagedAction
{
        protected $needLogin = false;
        protected $canPost = true;

        var $page = 1;
        var $count = 20;
        var $max_id = 0;
        var $since_id = 0;

        /**
         * Handle the Actor Inbox request
         *
         * @return void
         */
        protected function handle ()
        {
                $this->showJsonTimeline($this->getNotices());
        }

        /**
         * Get notices
         *
         * @return array notices
         */
         function getNotices()
         {
                $notices = array ();

                $stream = new PublicNoticeStream (null);

                $notice = $stream->getNotices (($this->page - 1) * $this->count,
                                              $this->count,
                                              $this->since_id, $this->max_id);

                $notices = $notice->fetchAll ();

                NoticeList::prefill ($notices);

                return $notices;
        }

        function showJsonTimeline ($notice)
        {
                header ('Content-Type: application/json; charset=utf-8');

                $statuses = array ();

                if (is_array ($notice)) {
                        //FIXME: make everything calling showJsonTimeline use only Notice objects
                        $ids = array ();
                        foreach($notice as $n) {
                                $ids[] = $n->getID ();
                        }
                        $notice = Notice::multiGet ('id', $ids);
                }

                while ($notice->fetch ()) {
                        try {
                                $twitter_status = $this->twitterStatusArray ($notice);
                                array_push ($statuses, $twitter_status);
                        } catch (Exception $e) {
                                common_log (LOG_ERR, $e->getMessage ());
                                continue;
                        }
                }

                $this->showJsonObjects ($statuses);
        }

        function showJsonObjects ($objects)
        {
                $json_objects = json_encode ($objects);
                if ($json_objects === false) {
                        $this-> clientError(_('JSON encoding failed. Error: ').json_last_error_msg ());
                } else {
                        print $json_objects;
                }
        }

        function twitterStatusArray ($notice, $include_user = true)
        {
                $base = $this->twitterSimpleStatusArray ($notice, $include_user);

                // FIXME: MOVE TO SHARE PLUGIN
                if (!empty ($notice->repeat_of)) {
                        $original = Notice::getKV ('id', $notice->repeat_of);
                        if ($original instanceof Notice) {
                                $orig_array = $this->twitterSimpleStatusArray($original, $include_user);
                                $base['retweeted_status'] = $orig_array;
                        }
                }

                return $base;
        }

        function twitterSimpleStatusArray ($notice, $include_user = true)
        {
                $profile = $notice->getProfile ();

                $twitter_status = array ();
                $twitter_status['text'] = $notice->content;
                $twitter_status['truncated'] = false;
                $twitter_status['created_at'] = self::dateTwitter ($notice->created);
                try {
                        // We could just do $notice->reply_to but maybe the future holds a
                        // different story for parenting.
                        $parent = $notice->getParent ();
                        $in_reply_to = $parent->id;
                } catch (NoParentNoticeException $e) {
                        $in_reply_to = null;
                } catch (NoResultException $e) {
                        // the in_reply_to message has probably been deleted
                        $in_reply_to = null;
                }
                $twitter_status['in_reply_to_status_id'] = $in_reply_to;

                $source = null;
                $source_link = null;

                $ns = $notice->getSource ();
                if ($ns instanceof Notice_source) {
                        $source = $ns->code;
                        if (!empty ($ns->url)) {
                                $source_link = $ns->url;
                                if (!empty ($ns->name)) {
                                        $source = $ns->name;
                                }
                        }
                }

                $twitter_status['uri'] = $notice->getUri ();
                $twitter_status['source'] = $source;
                $twitter_status['source_link'] = $source_link;
                $twitter_status['id'] = intval ($notice->id);

                $replier_profile = null;

                if ($notice->reply_to) {
                        $reply = Notice::getKV (intval ($notice->reply_to));
                        if ($reply) {
                                $replier_profile = $reply->getProfile ();
                        }
                }

                $twitter_status['in_reply_to_user_id'] =
                    ($replier_profile) ? intval ($replier_profile->id) : null;
                $twitter_status['in_reply_to_screen_name'] =
                    ($replier_profile) ? $replier_profile->nickname : null;

                try {
                        $notloc = Notice_location::locFromStored ($notice);
                        // This is the format that GeoJSON expects stuff to be in
                        $twitter_status['geo'] = array ('type' => 'Point',
                                                        'coordinates' => array (
                                                            (float) $notloc->lat,
                                                            (float) $notloc->lon));
                } catch (ServerException $e) {
                        $twitter_status['geo'] = null;
                }

                // Enclosures
                $attachments = $notice->attachments ();

                if (!empty ($attachments)) {

                        $twitter_status['attachments'] = array ();

                        foreach ($attachments as $attachment) {
                                try {
                                        $enclosure_o = $attachment->getEnclosure ();
                                        $enclosure = array();
                                        $enclosure['url'] = $enclosure_o->url;
                                        $enclosure['mimetype'] = $enclosure_o->mimetype;
                                        $enclosure['size'] = $enclosure_o->size;
                                        $twitter_status['attachments'][] = $enclosure;
                                } catch (ServerException $e) {
                                        // There was not enough metadata available
                                }
                        }
                }

                if ($include_user && $profile) {
                        // Don't get notice (recursive!)
                        $twitter_user = $this->twitterUserArray($profile, false);
                        $twitter_status['user'] = $twitter_user;
                }
                // StatusNet-specific

                $twitter_status['statusnet_html'] = $notice->getRendered();
                $twitter_status['statusnet_conversation_id'] = intval($notice->conversation);

                // The event call to handle NoticeSimpleStatusArray lets plugins add data to the output array
                Event::handle('NoticeSimpleStatusArray',
                              array ($notice, &$twitter_status, $this->scoped,
                                    array ('include_user' => $include_user)));

                return $twitter_status;
        }

        static function dateTwitter($dt)
        {
                $dateStr = date ('d F Y H:i:s', strtotime ($dt));
                $d = new DateTime ($dateStr, new DateTimeZone ('UTC'));
                $d->setTimezone (new DateTimeZone(common_timezone()));
                return $d->format ('D M d H:i:s O Y');
        }

        function twitterUserArray ($profile, $get_notice = false)
        {
                $twitter_user = array ();

                try {
                        $user = $profile->getUser ();
                } catch (NoSuchUserException $e) {
                        $user = null;
                }

                $twitter_user['id'] = $profile->getID ();
                $twitter_user['name'] = $profile->getBestName ();
                $twitter_user['screen_name'] = $profile->getNickname ();
                $twitter_user['location'] = $profile->location;
                $twitter_user['description'] = $profile->getDescription ();

                // TODO: avatar url template (example.com/user/avatar?size={x}x{y})
                $twitter_user['profile_image_url'] = Avatar::urlByProfile($profile, AVATAR_STREAM_SIZE);
                $twitter_user['profile_image_url_https'] = $twitter_user['profile_image_url'];

                // START introduced by qvitter API, not necessary for StatusNet API
                $twitter_user['profile_image_url_profile_size'] = Avatar::urlByProfile($profile, AVATAR_PROFILE_SIZE);
                try {
                        $avatar = Avatar::getUploaded ($profile);
                        $origurl = $avatar->displayUrl ();
                } catch(Exception $e) {
                        $origurl = $twitter_user['profile_image_url_profile_size'];
                }
                $twitter_user['profile_image_url_original'] = $origurl;

                $twitter_user['groups_count'] = $profile->getGroupCount();
                foreach (array('linkcolor', 'backgroundcolor') as $key) {
                        $twitter_user[$key] =  Profile_prefs::getConfigData($profile, 'theme', $key);
                }
                // END introduced by qvitter API, not necessary for StatusNet API

                $twitter_user['url'] = ($profile->homepage) ? $profile->homepage : null;
                $twitter_user['protected'] = (!empty($user) && $user->private_stream) ? true : false;
                $twitter_user['followers_count'] = $profile->subscriberCount();

                // Note: some profiles don't have an associated user

                $twitter_user['friends_count'] = $profile->subscriptionCount ();

                $twitter_user['created_at'] = self::dateTwitter($profile->created);

                $timezone = 'UTC';

                if (!empty($user) && $user->timezone) {
                        $timezone = $user->timezone;
                }

                $t = new DateTime;
                $t->setTimezone (new DateTimeZone ($timezone));

                $twitter_user['utc_offset']     = $t->format('Z');
                $twitter_user['time_zone']      = $timezone;
                $twitter_user['statuses_count'] = $profile->noticeCount();

                // Is the requesting user following this user?
                // These values might actually also mean "unknown". Ambiguity issues?
                $twitter_user['following']          = false;
                $twitter_user['statusnet_blocking'] = false;
                $twitter_user['notifications']      = false;

                if ($this->scoped instanceof Profile) {
                        try {
                                $sub = Subscription::getSubscription($this->scoped, $profile);
                                // Notifications on?
                                $twitter_user['following'] = true;
                                $twitter_user['notifications'] = ($sub->jabber || $sub->sms);
                        } catch (NoResultException $e) {
                                // well, the values are already false...
                        }
                        $twitter_user['statusnet_blocking'] = $this->scoped->hasBlocked($profile);
                }

                if ($get_notice) {
                        $notice = $profile->getCurrentNotice ();
                        if ($notice instanceof Notice) {
                                // don't get user!
                                $twitter_user['status'] = $this->twitterStatusArray($notice, false);
                        }
                }
                // StatusNet-specific

                $twitter_user['statusnet_profile_url'] = $profile->profileurl;

                // The event call to handle NoticeSimpleStatusArray lets plugins add data to the output array
                Event::handle('TwitterUserArray', array ($profile, &$twitter_user, $this->scoped, array()));

                return $twitter_user;
        }
}
