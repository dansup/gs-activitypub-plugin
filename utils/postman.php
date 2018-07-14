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
 * ActivityPub's own Postman
 *
 * Standard workflow expects that we send an Explorer to find out destinataries'
 * inbox address. Then we send our postman to deliver whatever we want to send them.
 *
 * @category  Plugin
 * @package   GNUsocial
 * @author    Diogo Cordeiro <diogo@fc.up.pt>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://www.gnu.org/software/social/
 */
class Activitypub_postman
{
        private $actor;
        private $to = array ();
        private $client;
        private $headers;

        /**
         * Create a postman to deliver something to someone
         *
         * @param Profile of sender
         * @param Activitypub_profile $to array of destinataries
         */
        public function __construct ($from, $to = array ())
        {
                $this->client = new HTTPClient ();
                $this->actor = $from;
                $this->to = $to;
                $this->headers = array();
                $this->headers[] = 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
                $this->headers[] = 'User-Agent: GNUSocialBot v0.1 - https://gnu.io/social';
        }

        /**
         * Send a follow notification to remote instance
         */
        public function follow ()
        {
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                          "type"   => "Follow",
                          "actor"  => $this->actor->getUrl (),
                          "object" => $this->to[0]->getUrl ());
                $this->client->setBody (json_encode ($data));
                $this->client->post ($this->to[0]->get_inbox (), $this->headers);
        }

        /**
         * Send a Undo Follow notification to remote instance
         */
        public function undo_follow ()
        {
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                            "type"   => "Undo",
                            "actor"  => $this->actor->getUrl (),
                            "object" => array (
                                "type" => "Follow",
                                "object" => $this->to[0]->getUrl ()
                            )
                        );
                $this->client->setBody (json_encode ($data));
                $this->client->post ($this->to[0]->get_inbox (), $this->headers);
        }

        /**
         * Send a Like notification to remote instances holding the notice
         *
         * @param Notice $notice
         */
        public function like ($notice)
        {
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                          "type"   => "Like",
                          "actor"  => $this->actor->getUrl (),
                          "object" => $notice->getUrl ());
                $this->client->setBody (json_encode ($data));
                foreach ($this->to_inbox () as $inbox) {
                        $this->client->post ($inbox, $this->headers);
                }
        }

        /**
         * Send a Undo Like notification to remote instances holding the notice
         *
         * @param Notice $notice
         */
        public function undo_like ($notice)
        {
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                            "type"   => "Undo",
                            "actor"  => $this->actor->getUrl (),
                            "object" => array (
                                "type"   => "Like",
                                "object" => $notice->getUrl ()
                            )
                        );
                $this->client->setBody (json_encode ($data));
                foreach ($this->to_inbox () as $inbox) {
                        $this->client->post ($inbox, $this->headers);
                }
        }

        /**
         * Send a Delete notification to remote instances holding the notice
         *
         * @param Notice $notice
         */
        public function delete ($notice)
        {
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                            "type"   => "Delete",
                            "actor"  => $this->actor->getUrl (),
                            "object" => $notice->getUrl ()
                        );
                $this->client->setBody (json_encode ($data));
                foreach ($this->to_inbox () as $inbox) {
                        $this->client->post ($inbox, $this->headers);
                }
        }

        /**
         * Clean list of inboxes to deliver messages
         *
         * @return array To Inbox URLs
         */
        private function to_inbox ()
        {
                $to_inboxes = array ();
                foreach ($this->to as $to_profile) {
                        $to_inboxes[] = $to_profile->get_inbox ();
                }

                return array_unique ($to_inboxes);
        }
}
