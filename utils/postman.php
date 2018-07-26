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
    private $to = [];
    private $client;
    private $headers;

    /**
     * Create a postman to deliver something to someone
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Profile of sender
     * @param Activitypub_profile $to array of destinataries
     */
    public function __construct($from, $to = [])
    {
        $this->client = new HTTPClient();
        $this->actor = $from;
        $this->to = $to;
        $this->headers = [];
        $this->headers[] = 'Accept: application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
        $this->headers[] = 'User-Agent: GNUSocialBot v0.1 - https://gnu.io/social';
    }

    /**
     * Send a follow notification to remote instance
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @throws Exception
     */
    public function follow()
    {
        $data = Activitypub_follow::follow_to_array($this->actor->getUrl(), $this->to[0]->getUrl());
        $this->client->setBody(json_encode($data));
        $res = $this->client->post($this->to[0]->get_inbox(), $this->headers);
        $res_body = json_decode($res->getBody());

        if ($res->isOk() || $res->getStatus() == 409) {
            $pending_list = new Activitypub_pending_follow_requests($this->actor->getID(), $this->to[0]->getID());
            if (! ($res->getStatus() == 409 || $res_body->type == "Accept")) {
                $pending_list->add();
                throw new Exception("Your follow request is pending acceptation.");
            }
            $pending_list->remove();
            return true;
        } elseif (isset($res_body[0]->error)) {
            throw new Exception($res_body[0]->error);
        }

        throw new Exception("An unknown error occurred.");
    }

    /**
     * Send a Undo Follow notification to remote instance
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     */
    public function undo_follow()
    {
        $data = Activitypub_undo::undo_to_array(
                         Activitypub_follow::follow_to_array(
                             $this->actor->getUrl(),
                                                              $this->to[0]->getUrl()
                         )
                        );
        $this->client->setBody(json_encode($data));
        $res = $this->client->post($this->to[0]->get_inbox(), $this->headers);
        $res_body = json_decode($res->getBody());

        if ($res->isOk() || $res->getStatus() == 409) {
            $pending_list = new Activitypub_pending_follow_requests($this->actor->getID(), $this->to[0]->getID());
            $pending_list->remove();
            return true;
        }
        if (isset($res_body[0]->error)) {
            throw new Exception($res_body[0]->error);
        }
        throw new Exception("An unknown error occurred.");
    }

    /**
     * Send a Like notification to remote instances holding the notice
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     */
    public function like($notice)
    {
        $data = Activitypub_like::like_to_array(
                    $this->actor->getUrl(),
                         Activitypub_notice::notice_to_array($notice)
                        );
        $this->client->setBody(json_encode($data));
        foreach ($this->to_inbox() as $inbox) {
            $this->client->post($inbox, $this->headers);
        }
    }

    /**
     * Send a Undo Like notification to remote instances holding the notice
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     */
    public function undo_like($notice)
    {
        $data = Activitypub_undo::undo_to_array(
                         Activitypub_like::like_to_array(
                             $this->actor->getUrl(),
                          Activitypub_notice::notice_to_array($notice)
                         )
                        );
        $this->client->setBody(json_encode($data));
        foreach ($this->to_inbox() as $inbox) {
            $this->client->post($inbox, $this->headers);
        }
    }

    /**
     * Send a Create notification to remote instances
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     */
    public function create($notice)
    {
        $data = Activitypub_create::create_to_array(
                    $notice->getUri(),
                                                             $this->actor->getUrl(),
                                                             Activitypub_notice::notice_to_array($notice)
                                                            );
        if (isset($notice->reply_to)) {
            $data["object"]["reply_to"] = $notice->getParent()->getUri();
        }
        $this->client->setBody(json_encode($data));
        foreach ($this->to_inbox() as $inbox) {
            $this->client->post($inbox, $this->headers);
        }
    }

    /**
     * Send a Announce notification to remote instances
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     */
    public function announce($notice)
    {
        $data = Activitypub_announce::announce_to_array(
                         $this->actor->getUrl(),
                         Activitypub_notice::notice_to_array($notice)
                        );
        $this->client->setBody(json_encode($data));
        foreach ($this->to_inbox() as $inbox) {
            $this->client->post($inbox, $this->headers);
        }
    }

    /**
     * Send a Delete notification to remote instances holding the notice
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @param Notice $notice
     */
    public function delete($notice)
    {
        $data = Activitypub_delete::delete_to_array(Activitypub_notice::notice_to_array($notice));
        $this->client->setBody(json_encode($data));
        $errors = array();
        foreach ($this->to_inbox() as $inbox) {
            $res = $this->client->post($inbox, $this->headers);
            if (!$res->isOk()) {
                $res_body = json_decode($res->getBody());
                if (isset($res_body[0]->error)) {
                    $errors[] = ($res_body[0]->error);
                    continue;
                }
                $errors[] = ("An unknown error occurred.");
            }
        }
        if (!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
    }

    /**
     * Clean list of inboxes to deliver messages
     *
     * @author Diogo Cordeiro <diogo@fc.up.pt>
     * @return array To Inbox URLs
     */
    private function to_inbox()
    {
        $to_inboxes = array();
        foreach ($this->to as $to_profile) {
            $to_inboxes[] = $to_profile->get_inbox();
        }

        return array_unique($to_inboxes);
    }
}
