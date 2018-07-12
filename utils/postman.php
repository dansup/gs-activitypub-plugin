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
         * @param Activitypub_profile $to array of destinataries
         */
        public function __construct ($from, $to)
        {
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
                $this->client = new HTTPClient ();
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                          "type"   => "Follow",
                          "actor"  => $this->actor->getUrl (),
                          "object" => $this->to[0]->getUrl ());
                $this->client->setBody (json_encode ($data));
                $response = $this->client->post ($this->to[0]->getInbox (), $this->headers);
        }

        /**
         * Send a Undo Follow notification to remote instance
         */
        public function undo_follow ()
        {
                $this->client = new HTTPClient ();
                $data = array ("@context" => "https://www.w3.org/ns/activitystreams",
                            "type"   => "Undo",
                            "actor"  => $this->actor->getUrl (),
                            "object" => array (
                                "type" => "Follow",
                                "object" => $this->to[0]->getUrl ()
                            )
                        );
                $this->client->setBody (json_encode ($data));
                $response = $this->client->post ($this->to[0]->getInbox (), $this->headers);
        }
}
